<?php
declare(strict_types=1);

/*
 * Vemaro – Kontaktformular-Handler
 *
 * Sendet die Kontaktanfrage per E-Mail an kontakt@vemaro.de
 * und schickt dem Absender eine automatische Bestätigung.
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
$csrfToken = trim((string) ($_POST['csrf_token'] ?? ''));
if (
    $csrfToken === '' ||
    !isset($_SESSION['csrf_token_contact']) ||
    !hash_equals($_SESSION['csrf_token_contact'], $csrfToken)
) {
    respond(403, false, 'Sicherheitstoken ungültig. Bitte die Seite neu laden und erneut versuchen.');
}
unset($_SESSION['csrf_token_contact']);

/* ───── 3. Rate-Limiting ───── */
$rateLimitDir = dirname(__DIR__) . '/uploads/.ratelimit';
if (!is_dir($rateLimitDir)) {
    @mkdir($rateLimitDir, 0755, true);
}
$clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
$rateLimitFile = $rateLimitDir . '/contact_' . md5($clientIp) . '.json';
$rateLimit = loadRateLimit($rateLimitFile);
$now = time();
$windowSeconds = 600;
$maxRequests = 5;

$rateLimit = array_filter($rateLimit, function (int $ts) use ($now, $windowSeconds): bool {
    return ($now - $ts) < $windowSeconds;
});

if (count($rateLimit) >= $maxRequests) {
    respond(429, false, 'Zu viele Anfragen. Bitte warten Sie einige Minuten.');
}

$rateLimit[] = $now;
saveRateLimit($rateLimitFile, $rateLimit);

/* ───── 4. Eingaben validieren ───── */
$name = sanitizeInput((string) ($_POST['name'] ?? ''));
$email = sanitizeInput((string) ($_POST['email'] ?? ''));
$phone = sanitizeInput((string) ($_POST['phone'] ?? ''));
$service = sanitizeInput((string) ($_POST['service'] ?? ''));
$message = sanitizeInput((string) ($_POST['message'] ?? ''));
$consent = (string) ($_POST['privacyConsent'] ?? '');

if ($name === '' || $email === '' || $message === '' || $consent === '') {
    respond(400, false, 'Bitte alle Pflichtfelder ausfüllen.');
}

if (mb_strlen($name) > 200) {
    respond(400, false, 'Name ist zu lang.');
}
if (mb_strlen($email) > 254) {
    respond(400, false, 'E-Mail-Adresse ist zu lang.');
}
if (mb_strlen($phone) > 30) {
    respond(400, false, 'Telefonnummer ist zu lang.');
}
if (mb_strlen($service) > 100) {
    respond(400, false, 'Ungültige Auswahl.');
}
if (mb_strlen($message) > 5000) {
    respond(400, false, 'Nachricht ist zu lang (max. 5000 Zeichen).');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(400, false, 'Bitte eine gültige E-Mail-Adresse angeben.');
}

if (containsHeaderInjection($email) || containsHeaderInjection($name)) {
    respond(400, false, 'Ungültige Zeichen in der Eingabe erkannt.');
}

/* ───── 5. Service-Label zuordnen ───── */
$serviceLabels = [
    'kassenkraefte' => 'Kassenkräfte',
    'warenverraeumung' => 'Warenverräumung',
    'lagerhelfer' => 'Lagerhelfer',
    'reinigungskraefte' => 'Reinigungskräfte',
    'sonstiges' => 'Sonstiges',
];
$serviceLabel = $serviceLabels[$service] ?? ($service !== '' ? $service : 'Nicht angegeben');

/* ───── 6. E-Mail senden ───── */
$to = 'kontakt@vemaro.de';
$subject = 'Kontaktanfrage - ' . mb_substr($name, 0, 80);

$headers = [];
$headers[] = 'From: Vemaro Kontakt <kontakt@vemaro.de>';
$headers[] = 'Reply-To: ' . sanitizeEmailHeader($email);
$headers[] = 'Content-Type: text/plain; charset=UTF-8';
$headers[] = 'MIME-Version: 1.0';

$body = [];
$body[] = 'Neue Kontaktanfrage über die Website';
$body[] = '========================================';
$body[] = '';
$body[] = 'Name:     ' . $name;
$body[] = 'E-Mail:   ' . $email;
$body[] = 'Telefon:  ' . ($phone !== '' ? $phone : '-');
$body[] = 'Leistung: ' . $serviceLabel;
$body[] = '';
$body[] = 'Nachricht:';
$body[] = '----------------------------------------';
$body[] = $message;

$sent = @mail(
    $to,
    encodeHeader($subject),
    implode("\r\n", $body),
    implode("\r\n", $headers)
);

if (!$sent) {
    respond(500, false, 'Nachricht konnte nicht gesendet werden. Bitte später erneut versuchen.');
}

/* ───── 7. Bestätigungsmail ───── */
$autoReplyBody = "Guten Tag " . mb_substr($name, 0, 100) . ",\n\n";
$autoReplyBody .= "vielen Dank für Ihre Anfrage. Wir haben Ihre Nachricht erhalten und melden uns schnellstmöglich bei Ihnen.\n\n";
$autoReplyBody .= "Freundliche Grüße\nVemaro";

@mail(
    sanitizeEmailHeader($email),
    encodeHeader('Ihre Anfrage bei Vemaro'),
    $autoReplyBody,
    'From: Vemaro <kontakt@vemaro.de>'
);

respond(200, true, 'Nachricht erfolgreich gesendet.');


/* ════════════════════════════════════════════════════════════════
   HILFSFUNKTIONEN
   ════════════════════════════════════════════════════════════════ */

function containsHeaderInjection(string $value): bool
{
    return (bool) preg_match('/[\r\n\x00]/', $value);
}

function sanitizeEmailHeader(string $value): string
{
    return str_replace(["\r", "\n", "\x00"], '', $value);
}

function sanitizeInput(string $value): string
{
    return trim(str_replace("\x00", '', $value));
}

function loadRateLimit(string $file): array
{
    if (!is_file($file))
        return [];
    $data = @file_get_contents($file);
    if ($data === false)
        return [];
    $decoded = json_decode($data, true);
    return is_array($decoded) ? $decoded : [];
}

function saveRateLimit(string $file, array $timestamps): void
{
    @file_put_contents($file, json_encode(array_values($timestamps)), LOCK_EX);
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
