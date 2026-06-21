<?php
declare(strict_types=1);

/*
 * Vemaro – Datenbankverbindung
 *
 * Stellt die Verbindung zur MySQL-Datenbank via PDO bereit.
 * Fällt bei Verbindungsfehlern stumm zurück (liefert null und schreibt in den PHP-Error-Log),
 * damit die Website bei fehlender DB-Einrichtung nicht abstürzt.
 */

require_once __DIR__ . '/config.php';

/**
 * Gibt eine aktive PDO-Verbindung zurück oder null bei Fehlern.
 */
function getDbConnection(): ?PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    try {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        // Fehler protokollieren, aber die Ausführung nicht abbrechen, damit Fallbacks greifen
        error_log('Datenbankverbindung fehlgeschlagen: ' . $e->getMessage());
        return null;
    }
}
