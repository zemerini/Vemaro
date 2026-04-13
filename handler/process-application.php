<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, false, 'Nur POST-Anfragen sind erlaubt.');
}

$name = trim((string)($_POST['name'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));
$jobId = trim((string)($_POST['jobId'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));
$privacyConsent = (string)($_POST['privacyConsent'] ?? '');

if ($name === '' || $email === '' || $jobId === '' || $privacyConsent === '') {
    respond(400, false, 'Bitte alle Pflichtfelder ausfuellen.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(400, false, 'Bitte eine gueltige E-Mail-Adresse angeben.');
}

if (!isset($_FILES['cv']) || !is_array($_FILES['cv'])) {
    respond(400, false, 'Bitte einen Lebenslauf hochladen.');
}

$file = $_FILES['cv'];
$uploadError = (int)$file['error'];
$appMaxSize = 5 * 1024 * 1024;
$iniUploadMax = iniSizeToBytes((string)ini_get('upload_max_filesize'));
$iniPostMax = iniSizeToBytes((string)ini_get('post_max_size'));
$serverLimit = minPositive($iniUploadMax, $iniPostMax);
$effectiveMax = $serverLimit > 0 ? min($appMaxSize, $serverLimit) : $appMaxSize;

if ($uploadError !== UPLOAD_ERR_OK) {
    $message = uploadErrorMessage($uploadError, $effectiveMax, $serverLimit);
    $status = ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) ? 413 : 400;
    respond($status, false, $message);
}

$tmpPath = (string)$file['tmp_name'];
$fileSize = (int)$file['size'];
if ($fileSize <= 0 || $fileSize > $effectiveMax) {
    respond(400, false, 'Datei ungueltig oder groesser als ' . formatBytes($effectiveMax) . '.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = (string)$finfo->file($tmpPath);
$allowedMimes = [
    'application/pdf' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/png' => 'png'
];

if (!array_key_exists($mimeType, $allowedMimes)) {
    respond(400, false, 'Nur PDF, JPG und PNG sind erlaubt.');
}

$uploadDir = dirname(__DIR__) . '/uploads/applications';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
    respond(500, false, 'Upload-Ordner konnte nicht erstellt werden.');
}

cleanupOldFiles($uploadDir, 30);

$safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '', mb_strtolower($name));
if ($safeBase === null || $safeBase === '') {
    $safeBase = 'bewerbung';
}

$targetFile = sprintf(
    '%s/%s_%s.%s',
    $uploadDir,
    date('Ymd_His'),
    $safeBase . '_' . bin2hex(random_bytes(4)),
    $allowedMimes[$mimeType]
);

if (!move_uploaded_file($tmpPath, $targetFile)) {
    respond(500, false, 'Lebenslauf konnte nicht gespeichert werden.');
}

$to = 'bewerbung@vemaro.de';
$subject = 'Neue Bewerbung - ' . $name . ' (' . $jobId . ')';
$boundary = 'vemaro_' . md5((string)microtime(true));

$headers = [];
$headers[] = 'From: Vemaro Bewerbung <bewerbung@vemaro.de>';
$headers[] = 'Reply-To: ' . $email;
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';

$bodyText = [];
$bodyText[] = 'Neue Bewerbung ueber die Karriere-Seite';
$bodyText[] = '';
$bodyText[] = 'Name: ' . $name;
$bodyText[] = 'E-Mail: ' . $email;
$bodyText[] = 'Telefon: ' . ($phone !== '' ? $phone : '-');
$bodyText[] = 'Stelle: ' . $jobId;
$bodyText[] = '';
$bodyText[] = 'Nachricht / Anschreiben:';
$bodyText[] = $message !== '' ? $message : '-';

$mailBody = '';
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

$sent = mail($to, encodeHeader($subject), $mailBody, implode("\r\n", $headers));

if (!$sent) {
    respond(500, false, 'Bewerbung konnte nicht gesendet werden. Bitte spaeter erneut versuchen.');
}

$autoReplySubject = 'Ihre Bewerbung bei Vemaro';
$autoReplyText = "Guten Tag " . $name . ",\n\n";
$autoReplyText .= "vielen Dank fuer Ihre Bewerbung. Wir haben Ihre Unterlagen erhalten und melden uns schnellstmoeglich bei Ihnen.\n\n";
$autoReplyText .= "Freundliche Gruesse\nVemaro";

@mail($email, encodeHeader($autoReplySubject), $autoReplyText, 'From: Vemaro <bewerbung@vemaro.de>');

respond(200, true, 'Bewerbung erfolgreich gesendet.');

function cleanupOldFiles(string $dir, int $days): void
{
    $threshold = time() - ($days * 86400);
    $files = @scandir($dir);
    if (!is_array($files)) {
        return;
    }

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $path = $dir . '/' . $file;
        if (!is_file($path)) {
            continue;
        }

        $mtime = @filemtime($path);
        if ($mtime !== false && $mtime < $threshold) {
            @unlink($path);
        }
    }
}

function respond(int $status, bool $success, string $message): void
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
            return 'Datei zu gross. Der Server ist aktuell auf ' . formatBytes($serverLimit) . ' begrenzt. Bitte Serverlimit erhoehen oder kleinere Datei nutzen.';
        }
        return 'Datei zu gross. Bitte maximal ' . formatBytes($effectiveMax) . ' hochladen.';
    }

    if ($code === UPLOAD_ERR_PARTIAL) {
        return 'Upload unvollstaendig. Bitte erneut versuchen.';
    }

    if ($code === UPLOAD_ERR_NO_FILE) {
        return 'Bitte einen Lebenslauf hochladen.';
    }

    if ($code === UPLOAD_ERR_NO_TMP_DIR) {
        return 'Serverfehler: Temporaerer Upload-Ordner fehlt.';
    }

    if ($code === UPLOAD_ERR_CANT_WRITE) {
        return 'Serverfehler: Datei konnte nicht gespeichert werden.';
    }

    if ($code === UPLOAD_ERR_EXTENSION) {
        return 'Upload wurde durch eine Servererweiterung blockiert.';
    }

    return 'Der Dateiupload ist fehlgeschlagen.';
}

function iniSizeToBytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }

    $unit = strtolower(substr($value, -1));
    $number = (float)$value;

    if ($unit === 'g') {
        return (int)round($number * 1024 * 1024 * 1024);
    }

    if ($unit === 'm') {
        return (int)round($number * 1024 * 1024);
    }

    if ($unit === 'k') {
        return (int)round($number * 1024);
    }

    return (int)$number;
}

function minPositive(int $a, int $b): int
{
    if ($a <= 0) {
        return $b;
    }
    if ($b <= 0) {
        return $a;
    }
    return min($a, $b);
}

function formatBytes(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / (1024 * 1024), 1, '.', '') . ' MB';
    }

    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 0, '.', '') . ' KB';
    }

    return $bytes . ' B';
}
