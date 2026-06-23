<?php
declare(strict_types=1);

/*
 * Vemaro – Bewerbungs-Upload-Handler (Gehärtet für IONOS-Hosting)
 * Autor: Leard Mucolli
 *
 * Sicherheitsmaßnahmen:
 *  1. Nur POST erlaubt
 *  2. CSRF-Token-Prüfung (per Session)
 *  3. Rate-Limiting (IP-basiert, Dateisystem)
 *  4. Strenge Eingabe-Validierung & Sanitization
 *  5. MIME-Type-Prüfung + Magic-Byte-Validierung
 *  6. Sichere Dateinamen (random, keine User-Eingabe im Pfad)
 *  7. E-Mail-Header-Injection-Schutz
 *  8. Dateiberechtigungen 0644
 *  9. Automatisches Cleanup alter Dateien (30 Tage)
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

/* ───── 1. Nur POST ───── */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, false, 'Nur POST-Anfragen sind erlaubt.');
}

/* ───── 2. CSRF-Token prüfen ───── */
session_start();
$csrfToken = trim((string)($_POST['csrf_token'] ?? ''));
if (
    $csrfToken === '' ||
    !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrfToken)
) {
    respond(403, false, 'Sicherheitstoken ungültig. Bitte die Seite neu laden und erneut versuchen.');
}
// Token nach Verwendung invalidieren (One-Time-Use)
unset($_SESSION['csrf_token']);

/* ───── 3. Rate-Limiting (IP-basiert) ───── */
$rateLimitDir = __DIR__ . '/../uploads/.ratelimit';
if (!is_dir($rateLimitDir)) {
    @mkdir($rateLimitDir, 0755, true);
}
$clientIp = getClientIp();
$rateLimitFile = $rateLimitDir . '/' . md5($clientIp) . '.json';
$rateLimit = loadRateLimit($rateLimitFile);
$now = time();
$windowSeconds = 600; // 10 Minuten
$maxRequests = 5;     // Max 5 Bewerbungen pro 10 Min

// Alte Einträge entfernen
$rateLimit = array_filter($rateLimit, function (int $ts) use ($now, $windowSeconds): bool {
    return ($now - $ts) < $windowSeconds;
});

if (count($rateLimit) >= $maxRequests) {
    respond(429, false, 'Zu viele Anfragen. Bitte warten Sie einige Minuten und versuchen Sie es erneut.');
}

// Aktuellen Request registrieren
$rateLimit[] = $now;
saveRateLimit($rateLimitFile, $rateLimit);

/* ───── 4. Eingabe-Validierung ───── */
$name           = sanitizeInput((string)($_POST['name'] ?? ''));
$email          = sanitizeInput((string)($_POST['email'] ?? ''));
$phone          = sanitizeInput((string)($_POST['phone'] ?? ''));
$jobId          = sanitizeInput((string)($_POST['jobId'] ?? ''));
$message        = sanitizeInput((string)($_POST['message'] ?? ''));
$privacyConsent = (string)($_POST['privacyConsent'] ?? '');

// Pflichtfelder
if ($name === '' || $email === '' || $jobId === '' || $privacyConsent === '') {
    respond(400, false, 'Bitte alle Pflichtfelder ausfüllen.');
}

// Längen-Begrenzung
if (mb_strlen($name) > 200) {
    respond(400, false, 'Name ist zu lang (max. 200 Zeichen).');
}
if (mb_strlen($email) > 254) {
    respond(400, false, 'E-Mail-Adresse ist zu lang.');
}
if (mb_strlen($phone) > 30) {
    respond(400, false, 'Telefonnummer ist zu lang.');
}
if (mb_strlen($jobId) > 100) {
    respond(400, false, 'Ungültige Stellenreferenz.');
}
if (mb_strlen($message) > 5000) {
    respond(400, false, 'Nachricht ist zu lang (max. 5000 Zeichen).');
}

// E-Mail validieren
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(400, false, 'Bitte eine gültige E-Mail-Adresse angeben.');
}

// E-Mail Header-Injection verhindern
if (containsHeaderInjection($email) || containsHeaderInjection($name)) {
    respond(400, false, 'Ungültige Zeichen in der Eingabe erkannt.');
}

/* ───── 5. Datei-Upload prüfen ───── */
if (!isset($_FILES['cv']) || !is_array($_FILES['cv'])) {
    respond(400, false, 'Bitte einen Lebenslauf hochladen.');
}

$file = $_FILES['cv'];
$uploadError = (int)$file['error'];
$appMaxSize = 5 * 1024 * 1024;
$iniUploadMax = iniSizeToBytes((string)ini_get('upload_max_filesize'));
$iniPostMax   = iniSizeToBytes((string)ini_get('post_max_size'));
$serverLimit  = minPositive($iniUploadMax, $iniPostMax);
$effectiveMax = $serverLimit > 0 ? min($appMaxSize, $serverLimit) : $appMaxSize;

if ($uploadError !== UPLOAD_ERR_OK) {
    $errMsg = uploadErrorMessage($uploadError, $effectiveMax, $serverLimit);
    $status = ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) ? 413 : 400;
    respond($status, false, $errMsg);
}

$tmpPath  = (string)$file['tmp_name'];
$fileSize = (int)$file['size'];

// Sicherstellen, dass es sich um einen echten Upload handelt
if (!is_uploaded_file($tmpPath)) {
    respond(400, false, 'Ungültiger Upload.');
}

if ($fileSize <= 0 || $fileSize > $effectiveMax) {
    respond(400, false, 'Datei ungültig oder größer als ' . formatBytes($effectiveMax) . '.');
}

/* ───── 6. MIME-Type + Magic-Byte Prüfung ───── */
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = (string)$finfo->file($tmpPath);
$allowedMimes = [
    'application/pdf' => 'pdf',
    'image/jpeg'      => 'jpg',
    'image/png'       => 'png'
];

if (!array_key_exists($mimeType, $allowedMimes)) {
    respond(400, false, 'Nur PDF, JPG und PNG sind erlaubt.');
}

// Magic-Byte-Validierung (zusätzlich zu MIME)
if (!validateMagicBytes($tmpPath, $mimeType)) {
    respond(400, false, 'Die Datei scheint beschädigt oder manipuliert zu sein.');
}

/* ───── 7. Datei speichern ───── */
$uploadDir = dirname(__DIR__) . '/uploads/applications';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
    respond(500, false, 'Upload-Ordner konnte nicht erstellt werden.');
}

// Alte Dateien aufräumen (30 Tage)
cleanupOldFiles($uploadDir, 30);

// Sicherer Dateiname: Nur Datum + Zufall, KEIN User-Input im Dateinamen
$targetFile = sprintf(
    '%s/%s_%s.%s',
    $uploadDir,
    date('Ymd_His'),
    bin2hex(random_bytes(8)),
    $allowedMimes[$mimeType]
);

if (!move_uploaded_file($tmpPath, $targetFile)) {
    respond(500, false, 'Lebenslauf konnte nicht gespeichert werden.');
}

// Dateiberechtigung setzen (nur lesen/schreiben für Owner)
@chmod($targetFile, 0644);

/* ───── 8. E-Mail senden ───── */
$to       = 'info@vemaro.de';
$subject  = 'Neue Bewerbung - ' . mb_substr($name, 0, 80) . ' (' . mb_substr($jobId, 0, 40) . ')';
$boundary = 'vemaro_' . bin2hex(random_bytes(16));

$headers   = [];
$headers[] = 'From: Vemaro Bewerbung <info@vemaro.de>';
$headers[] = 'Reply-To: ' . sanitizeEmailHeader($email);
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';

$bodyText   = [];
$bodyText[] = 'Neue Bewerbung über die Karriere-Seite';
$bodyText[] = '';
$bodyText[] = 'Name:     ' . $name;
$bodyText[] = 'E-Mail:   ' . $email;
$bodyText[] = 'Telefon:  ' . ($phone !== '' ? $phone : '-');
$bodyText[] = 'Stelle:   ' . $jobId;
$bodyText[] = '';
$bodyText[] = 'Nachricht / Anschreiben:';
$bodyText[] = $message !== '' ? $message : '-';

$mailBody  = '';
$mailBody .= '--' . $boundary . "\r\n";
$mailBody .= "Content-Type: text/plain; charset=UTF-8\r\n";
$mailBody .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
$mailBody .= implode("\r\n", $bodyText) . "\r\n\r\n";

$attachmentContent = (string)file_get_contents($targetFile);
$mailBody .= '--' . $boundary . "\r\n";
$mailBody .= 'Content-Type: ' . $mimeType . '; name="' . basename($targetFile) . '"' . "\r\n";
$mailBody .= "Content-Transfer-Encoding: base64\r\n";
$mailBody .= 'Content-Disposition: attachment; filename="' . basename($targetFile) . '"' . "\r\n\r\n";
$mailBody .= chunk_split(base64_encode($attachmentContent));
$mailBody .= '--' . $boundary . "--\r\n";

require_once __DIR__ . '/mail-helper.php';
$sent = sendMailSecure($to, encodeHeader($subject), $mailBody, implode("\r\n", $headers));

if (!$sent) {
    respond(500, false, 'Bewerbung konnte nicht gesendet werden. Bitte später erneut versuchen.');
}

/* ───── 9. Bestätigungsmail ───── */
$autoReplySubject = 'Ihre Bewerbung bei Vemaro';
$autoReplyBody  = "Guten Tag " . mb_substr($name, 0, 100) . ",\n\n";
$autoReplyBody .= "vielen Dank für Ihre Bewerbung. Wir haben Ihre Unterlagen erhalten und melden uns schnellstmöglich bei Ihnen.\n\n";
$autoReplyBody .= "Freundliche Grüße\nVemaro";

sendMailSecure(
    sanitizeEmailHeader($email),
    encodeHeader($autoReplySubject),
    $autoReplyBody,
    'From: Vemaro <info@vemaro.de>'
);

respond(200, true, 'Bewerbung erfolgreich gesendet.');


/* ════════════════════════════════════════════════════════════════
   HILFSFUNKTIONEN
   ════════════════════════════════════════════════════════════════ */

/**
 * Prüft die ersten Bytes der Datei auf korrekte Magic Bytes.
 */
function validateMagicBytes(string $path, string $expectedMime): bool
{
    $handle = @fopen($path, 'rb');
    if (!$handle) return false;

    $header = (string)fread($handle, 8);
    fclose($handle);

    if (strlen($header) < 4) return false;

    switch ($expectedMime) {
        case 'application/pdf':
            // PDF startet mit %PDF
            return str_starts_with($header, '%PDF');

        case 'image/jpeg':
            // JPEG startet mit FF D8 FF
            return (
                ord($header[0]) === 0xFF &&
                ord($header[1]) === 0xD8 &&
                ord($header[2]) === 0xFF
            );

        case 'image/png':
            // PNG: 89 50 4E 47 0D 0A 1A 0A
            return (
                strlen($header) >= 8 &&
                ord($header[0]) === 0x89 &&
                ord($header[1]) === 0x50 &&
                ord($header[2]) === 0x4E &&
                ord($header[3]) === 0x47
            );

        default:
            return false;
    }
}

/**
 * Prüft auf Header-Injection-Versuche in Eingaben.
 */
function containsHeaderInjection(string $value): bool
{
    return (bool)preg_match('/[\r\n\x00]/', $value);
}

/**
 * Sanitize E-Mail-Header-Werte (entfernt CRLF-Injection).
 */
function sanitizeEmailHeader(string $value): string
{
    return str_replace(["\r", "\n", "\x00"], '', $value);
}

/**
 * Bereinigt und kürzt User-Eingaben.
 */
function sanitizeInput(string $value): string
{
    // Null-Bytes entfernen
    $value = str_replace("\x00", '', $value);
    return trim($value);
}

/**
 * Ermittelt die Client-IP (berücksichtigt Proxies).
 */
function getClientIp(): string
{
    // Nur $_SERVER['REMOTE_ADDR'] verwenden (sicher)
    // X-Forwarded-For ist manipulierbar und wird bewusst NICHT genutzt
    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/**
 * Lädt Rate-Limit-Daten aus Datei.
 */
function loadRateLimit(string $file): array
{
    if (!is_file($file)) return [];
    $data = @file_get_contents($file);
    if ($data === false) return [];
    $decoded = json_decode($data, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Speichert Rate-Limit-Daten in Datei.
 */
function saveRateLimit(string $file, array $timestamps): void
{
    @file_put_contents($file, json_encode(array_values($timestamps)), LOCK_EX);
}

/**
 * Löscht Dateien älter als $days Tage.
 */
function cleanupOldFiles(string $dir, int $days): void
{
    $threshold = time() - ($days * 86400);
    $files = @scandir($dir);
    if (!is_array($files)) return;

    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || $file === '.htaccess' || $file === 'index.html') {
            continue;
        }

        $path = $dir . '/' . $file;
        if (!is_file($path)) continue;

        $mtime = @filemtime($path);
        if ($mtime !== false && $mtime < $threshold) {
            @unlink($path);
        }
    }
}

function respond(int $status, bool $success, string $message): never
{
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function encodeHeader(string $value): string
{
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function uploadErrorMessage(int $code, int $effectiveMax, int $serverLimit): string
{
    if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
        if ($serverLimit > 0 && $serverLimit < (5 * 1024 * 1024)) {
            return 'Datei zu groß. Der Server ist aktuell auf ' . formatBytes($serverLimit) . ' begrenzt. Bitte Serverlimit erhöhen oder kleinere Datei nutzen.';
        }
        return 'Datei zu groß. Bitte maximal ' . formatBytes($effectiveMax) . ' hochladen.';
    }
    if ($code === UPLOAD_ERR_PARTIAL)      return 'Upload unvollständig. Bitte erneut versuchen.';
    if ($code === UPLOAD_ERR_NO_FILE)      return 'Bitte einen Lebenslauf hochladen.';
    if ($code === UPLOAD_ERR_NO_TMP_DIR)   return 'Serverfehler: Temporärer Upload-Ordner fehlt.';
    if ($code === UPLOAD_ERR_CANT_WRITE)   return 'Serverfehler: Datei konnte nicht gespeichert werden.';
    if ($code === UPLOAD_ERR_EXTENSION)    return 'Upload wurde durch eine Servererweiterung blockiert.';
    return 'Der Dateiupload ist fehlgeschlagen.';
}

function iniSizeToBytes(string $value): int
{
    $value = trim($value);
    if ($value === '') return 0;

    $unit   = strtolower(substr($value, -1));
    $number = (float)$value;

    if ($unit === 'g') return (int)round($number * 1024 * 1024 * 1024);
    if ($unit === 'm') return (int)round($number * 1024 * 1024);
    if ($unit === 'k') return (int)round($number * 1024);

    return (int)$number;
}

function minPositive(int $a, int $b): int
{
    if ($a <= 0) return $b;
    if ($b <= 0) return $a;
    return min($a, $b);
}

function formatBytes(int $bytes): string
{
    if ($bytes >= 1024 * 1024) return number_format($bytes / (1024 * 1024), 1, '.', '') . ' MB';
    if ($bytes >= 1024)        return number_format($bytes / 1024, 0, '.', '') . ' KB';
    return $bytes . ' B';
}
