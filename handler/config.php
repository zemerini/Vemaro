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
define('DB_HOST', 'db5020764072.hosting-data.io');
define('DB_NAME', 'dbs15819810');
define('DB_USER', 'dbu2315954');
define('DB_PASS', 'Sivanatham123!');

// Admin-Passwortschutz (Standard-Passwort: Vemaro2026Admin!)
// Dieses Passwort wird gehasht gespeichert. Um ein neues Passwort zu generieren,
// verwenden Sie das Skript 'generate-hash.php'.
define('ADMIN_PASSWORD_HASH', '$2y$12$tHJBlihyxKLV8YeajWUJ3u/dbnfim/LzmFL1jRF6ZXuczLQ6IrQm.');
