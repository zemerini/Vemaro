<?php
declare(strict_types=1);

/*
 * Vemaro – Job-Feed API
 * Autor: Leard Mucolli
 * 
 * Gibt alle aktiven Jobs aus der Datenbank als JSON zurück.
 * Falls die Datenbank noch nicht eingerichtet ist oder ausfällt,
 * wird automatisch 'jobs.json' geladen, um Ausfälle der Karriere-Seite zu verhindern.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . '/handler/db.php';

// Hilfsfunktion zur Ausgabe statischer Jobs als Fallback
function serveFallbackJobs(): void
{
    $fallbackFile = __DIR__ . '/jobs.json';
    if (is_file($fallbackFile)) {
        $content = @file_get_contents($fallbackFile);
        if ($content !== false) {
            echo $content;
            exit;
        }
    }
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Datenbankverbindung herstellen
$db = getDbConnection();
if ($db === null) {
    serveFallbackJobs();
}

// 2. Jobs aus der Datenbank abfragen
try {
    $stmt = $db->query('SELECT * FROM jobs ORDER BY created_at DESC');
    $rows = $stmt->fetchAll();
    
    $formattedJobs = [];
    foreach ($rows as $row) {
        $formattedJobs[] = [
            'id'              => $row['id'],
            'title'           => $row['title'],
            'location'        => $row['location'],
            'workdays'        => $row['workdays'],
            'startDate'       => $row['startDate'],
            'employmentTypes' => json_decode((string)$row['employmentTypes'], true) ?? [],
            'description'     => $row['description'],
            'tasks'           => json_decode((string)$row['tasks'], true) ?? [],
            'requirements'    => json_decode((string)$row['requirements'], true) ?? [],
            'benefits'        => json_decode((string)$row['benefits'], true) ?? [],
        ];
    }
    
    echo json_encode($formattedJobs, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    // Im Fehlerfall Log schreiben und Fallback-Jobs ausliefern
    error_log('Fehler beim Laden der Stellenangebote: ' . $e->getMessage());
    serveFallbackJobs();
}
