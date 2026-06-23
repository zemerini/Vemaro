<?php
declare(strict_types=1);

/*
 * Vemaro – E-Mail-Hilfsfunktion
 * Autor: Leard Mucolli
 *
 * Behandelt den Versand von E-Mails über PHP mail() mit einer
 * lokalen Ausfallsicherung (Email-Logging) für das Testen mit XAMPP.
 */

/**
 * Sendet eine E-Mail sicher und loggt sie lokal, falls die Umgebung lokal ist oder mail() fehlschlägt.
 *
 * @param string $to Empfänger-E-Mail
 * @param string $subject Betreffzeile (bereits encodiert)
 * @param string $body E-Mail-Inhalt (Text oder Multipart)
 * @param string $headers E-Mail-Header
 * @return bool True bei erfolgreichem Versand oder im lokalen Testmodus
 */
function sendMailSecure(string $to, string $subject, string $body, string $headers): bool
{
    // 1. Prüfen, ob wir uns in einer lokalen Testumgebung befinden (XAMPP)
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    $httpHost = $_SERVER['HTTP_HOST'] ?? '';
    
    $isLocal = in_array($remoteAddr, ['127.0.0.1', '::1']) || 
               str_contains($httpHost, 'localhost') || 
               str_contains($httpHost, '127.0.0.1');

    // 2. Mail-Versuch
    $sent = false;
    if (!$isLocal) {
        $sent = @mail($to, $subject, $body, $headers);
    }

    // 3. Wenn lokal oder mail() fehlschlägt -> E-Mail lokal protokollieren
    if (!$sent || $isLocal) {
        $logDir = dirname(__DIR__) . '/uploads/.emails';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        // Sicherheitsmaßnahmen für den E-Mail-Log-Ordner
        if (is_dir($logDir)) {
            if (!is_file($logDir . '/index.html')) {
                @file_put_contents($logDir . '/index.html', '');
            }
            if (!is_file($logDir . '/.htaccess')) {
                @file_put_contents($logDir . '/.htaccess', "Deny from all\n");
            }
        }

        $logFile = $logDir . '/emails_' . date('Y-m') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $statusStr = $isLocal ? 'LOKALER TEST (SIMULIERT)' : 'FEHLGESCHLAGEN (mail() lieferte false)';

        // Log-Eintrag aufbauen
        $logEntry = "========================================================================\n";
        $logEntry .= sprintf("ZEITPUNKT:   %s\nSTATUS:      %s\n", $timestamp, $statusStr);
        $logEntry .= sprintf("EMPFÄNGER:   %s\nBETREFF:     %s\n", $to, $subject);
        $logEntry .= "--------------------------- HEADER -------------------------------------\n";
        $logEntry .= trim($headers) . "\n";
        $logEntry .= "--------------------------- INHALT -------------------------------------\n";
        $logEntry .= $body . "\n";
        $logEntry .= "========================================================================\n\n";

        @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    // Für das lokale Testen geben wir dem Client immer "true" zurück, damit der UI-Flow fortgesetzt werden kann.
    if ($isLocal) {
        return true;
    }

    return $sent;
}
