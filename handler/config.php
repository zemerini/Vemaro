<?php
declare(strict_types=1);

/*
 * Vemaro – Globale Konfiguration
 * Autor: Leard Mucolli
 * 
 * Enthält Datenbank-Zugangsdaten und das Passwort für das Admin-Portal.
 * Diese Datei wird sowohl lokal (XAMPP) als auch in der Produktion (IONOS) verwendet.
 */

// Datenbank-Konfiguration
define('DB_HOST', 'localhost');
define('DB_NAME', 'vemaro_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Admin-Passwortschutz (Standard-Passwort: Vemaro2026Admin!)
// Dieses Passwort wird gehasht gespeichert. Um ein neues Passwort zu generieren,
// verwenden Sie das Skript 'generate-hash.php'.
define('ADMIN_PASSWORD_HASH', '$2y$12$tHJBlihyxKLV8YeajWUJ3u/dbnfim/LzmFL1jRF6ZXuczLQ6IrQm.');
