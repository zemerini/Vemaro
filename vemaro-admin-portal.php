<?php
declare(strict_types=1);

/*
 * Vemaro – Passwortgeschütztes Admin-Portal (Gehärtet & Sicher)
 *
 * Ermöglicht das Hinzufügen und Löschen von Stellenangeboten in der MySQL-Datenbank.
 * Komplett geschützt durch ein Session-basiertes Login und CSRF-Tokens.
 */

// 1. Session starten mit sicheren Einstellungen
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/handler/config.php';
require_once __DIR__ . '/handler/db.php';

// 2. CSRF-Token initialisieren
if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['admin_csrf_token'];

// 3. Login-Status prüfen
$isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$loginError = '';

// Login-Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    // CSRF prüfen
    $postCsrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrfToken, $postCsrf)) {
        $loginError = 'Sicherheitstoken ungültig. Bitte laden Sie die Seite neu.';
    } else {
        $password = (string)($_POST['password'] ?? '');
        if (password_verify($password, ADMIN_PASSWORD_HASH)) {
            $_SESSION['admin_logged_in'] = true;
            $isLoggedIn = true;
            // Token regenerieren nach Login zur Sicherheit
            $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
            $csrfToken = $_SESSION['admin_csrf_token'];
        } else {
            $loginError = 'Falsches Passwort. Bitte versuchen Sie es erneut.';
        }
    }
}

// Logout verarbeiten
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
    header('Location: vemaro-admin-portal.php');
    exit;
}

// 4. Datenbankverbindung prüfen
$db = getDbConnection();
$dbOnline = ($db !== null);

$actionError = '';
$actionSuccess = '';

// Wenn eingeloggt und DB online, POST-Aktionen (Hinzufügen, Löschen) verarbeiten
if ($isLoggedIn && $dbOnline && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // CSRF prüfen
    $postCsrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrfToken, $postCsrf)) {
        $actionError = 'Sicherheitstoken ungültig.';
    } else {
        $action = $_POST['action'];

        // STELLE LÖSCHEN
        if ($action === 'delete') {
            $jobId = trim((string)($_POST['job_id'] ?? ''));
            if ($jobId !== '') {
                try {
                    $stmt = $db->prepare('DELETE FROM jobs WHERE id = :id');
                    $stmt->execute(['id' => $jobId]);
                    if ($stmt->rowCount() > 0) {
                        $actionSuccess = 'Die Stelle ' . htmlspecialchars($jobId) . ' wurde erfolgreich gelöscht.';
                    } else {
                        $actionError = 'Die Stelle konnte nicht gefunden werden.';
                    }
                } catch (PDOException $e) {
                    $actionError = 'Fehler beim Löschen: ' . htmlspecialchars($e->getMessage());
                }
            } else {
                $actionError = 'Ungültige Stellen-ID.';
            }
        }

        // STELLE HINZUFÜGEN
        if ($action === 'add') {
            $jobId           = strtoupper(trim((string)($_POST['job_id'] ?? '')));
            $title           = trim((string)($_POST['title'] ?? ''));
            $location        = trim((string)($_POST['location'] ?? ''));
            $workdays        = trim((string)($_POST['workdays'] ?? ''));
            $startDate       = trim((string)($_POST['startDate'] ?? ''));
            $description     = trim((string)($_POST['description'] ?? ''));
            
            // Checkboxen für Anstellungsart
            $typesInput      = $_POST['employmentTypes'] ?? [];
            $employmentTypes = is_array($typesInput) ? array_map('trim', $typesInput) : [];

            // Textareas (eine Zeile pro Punkt)
            $tasksText        = trim((string)($_POST['tasks'] ?? ''));
            $requirementsText = trim((string)($_POST['requirements'] ?? ''));
            $benefitsText     = trim((string)($_POST['benefits'] ?? ''));

            // Konvertieren der Textareas in Arrays
            $tasks        = $tasksText !== '' ? array_filter(array_map('trim', explode("\n", $tasksText))) : [];
            $requirements = $requirementsText !== '' ? array_filter(array_map('trim', explode("\n", $requirementsText))) : [];
            $benefits     = $benefitsText !== '' ? array_filter(array_map('trim', explode("\n", $benefitsText))) : [];

            // Validierung
            if ($jobId === '' || $title === '' || $location === '' || $workdays === '' || $startDate === '' || $description === '' || empty($employmentTypes)) {
                $actionError = 'Bitte füllen Sie alle Pflichtfelder aus und wählen Sie mindestens eine Anstellungsart.';
            } elseif (!preg_match('/^[A-Z0-9\-]{3,50}$/', $jobId)) {
                $actionError = 'Die Referenz-ID darf nur aus Großbuchstaben, Zahlen und Bindestrichen bestehen (z.B. JOB-WV-001) und muss 3 bis 50 Zeichen lang sein.';
            } else {
                try {
                    // Prüfen, ob ID bereits vergeben ist
                    $stmtCheck = $db->prepare('SELECT COUNT(*) FROM jobs WHERE id = :id');
                    $stmtCheck->execute(['id' => $jobId]);
                    if ((int)$stmtCheck->fetchColumn() > 0) {
                        $actionError = 'Eine Stelle mit der Referenz-ID ' . htmlspecialchars($jobId) . ' existiert bereits.';
                    } else {
                        // In DB einfügen
                        $stmtInsert = $db->prepare('
                            INSERT INTO jobs (id, title, location, workdays, startDate, employmentTypes, description, tasks, requirements, benefits)
                            VALUES (:id, :title, :location, :workdays, :startDate, :employmentTypes, :description, :tasks, :requirements, :benefits)
                        ');
                        $stmtInsert->execute([
                            'id'              => $jobId,
                            'title'           => $title,
                            'location'        => $location,
                            'workdays'        => $workdays,
                            'startDate'       => $startDate,
                            'employmentTypes' => json_encode($employmentTypes, JSON_UNESCAPED_UNICODE),
                            'description'     => $description,
                            'tasks'           => json_encode(array_values($tasks), JSON_UNESCAPED_UNICODE),
                            'requirements'    => json_encode(array_values($requirements), JSON_UNESCAPED_UNICODE),
                            'benefits'        => json_encode(array_values($benefits), JSON_UNESCAPED_UNICODE),
                        ]);
                        $actionSuccess = 'Die Stelle "' . htmlspecialchars($title) . '" wurde erfolgreich hinzugefügt!';
                        
                        // Formularwerte leeren
                        $_POST = [];
                    }
                } catch (PDOException $e) {
                    $actionError = 'Fehler beim Speichern: ' . htmlspecialchars($e->getMessage());
                }
            }
        }
    }
}

// 5. Jobs laden, falls eingeloggt und DB online
$jobsList = [];
if ($isLoggedIn && $dbOnline) {
    try {
        $stmt = $db->query('SELECT * FROM jobs ORDER BY created_at DESC');
        $jobsList = $stmt->fetchAll();
    } catch (PDOException $e) {
        $actionError = 'Stellen konnten nicht geladen werden: ' . htmlspecialchars($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vemaro Admin-Portal – Stellenverwaltung</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    
    <style>
        /* Override Scroll Reveal opacity for Admin Portal since script.js is not loaded */
        .glass-card {
            opacity: 1 !important;
            transform: none !important;
        }

        /* AI Button Hover Effect */
        #openAiPromptBtn:hover {
            transform: scale(1.08) rotate(3deg);
            box-shadow: 0 0 20px rgba(var(--accent-rgb), 0.6);
            border-color: var(--accent-bright) !important;
        }

        /* Custom Modal Backdrop */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 2000;
            background: rgba(2, 2, 3, 0.85);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            display: flex;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity var(--transition-smooth);
            pointer-events: none;
        }

        .modal-backdrop.show {
            opacity: 1;
            pointer-events: auto;
        }

        /* Modal Card */
        .modal-card {
            max-width: 480px;
            width: calc(100% - 32px);
            padding: 40px 32px;
            text-align: center;
            transform: translateY(20px) scale(0.95);
            transition: transform var(--transition-spring);
            box-shadow: var(--glass-shadow-lg);
            background: linear-gradient(to bottom, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.02));
        }

        .modal-backdrop.show .modal-card {
            transform: translateY(0) scale(1);
        }

        /* Modal Elements */
        .modal-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 24px;
            border: 1px solid rgba(239, 68, 68, 0.25);
            box-shadow: 0 0 20px rgba(239, 68, 68, 0.2);
        }

        .modal-icon svg {
            width: 32px;
            height: 32px;
        }

        .modal-title {
            font-family: var(--font-heading);
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 12px;
            color: #ffffff;
        }

        .modal-text {
            font-size: 0.95rem;
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 32px;
        }

        .modal-actions {
            display: flex;
            gap: 16px;
            justify-content: center;
        }

        .modal-btn {
            padding: 12px 24px;
            font-size: 0.95rem;
            min-width: 120px;
            border-radius: var(--radius-sm);
        }

        /* Spezifische Styling-Erweiterungen für das Admin-Portal */
        .admin-container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 120px 24px 80px;
            position: relative;
            z-index: 1;
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .db-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 99px;
            font-size: 0.85rem;
            font-weight: 500;
            border: 1px solid var(--border);
        }

        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        .status-online {
            background-color: #34d399;
            box-shadow: 0 0 8px #10b981;
        }

        .status-offline {
            background-color: #f87171;
            box-shadow: 0 0 8px #ef4444;
        }

        .admin-grid {
            display: grid;
            grid-template-columns: 1.3fr 1fr;
            gap: 30px;
            align-items: start;
        }

        @media (max-width: 992px) {
            .admin-grid {
                grid-template-columns: 1fr;
            }
        }

        .admin-card {
            padding: 32px;
            margin-bottom: 30px;
        }

        .admin-title {
            font-family: var(--font-heading);
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 24px;
            color: #ffffff;
            border-bottom: 1px solid var(--border);
            padding-bottom: 12px;
        }

        .login-card {
            max-width: 450px;
            margin: 150px auto 0;
            padding: 40px;
            text-align: center;
        }

        .login-logo {
            margin: 0 auto 30px;
            filter: brightness(0) invert(1);
            height: 60px;
            width: auto;
        }

        .alert {
            padding: 14px 20px;
            border-radius: var(--radius-sm);
            margin-bottom: 24px;
            font-size: 0.95rem;
            line-height: 1.5;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #a7f3d0;
        }

        .job-table-wrap {
            overflow-x: auto;
        }

        .job-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.95rem;
        }

        .job-table th, .job-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
        }

        .job-table th {
            font-family: var(--font-heading);
            font-weight: 600;
            color: #ffffff;
            background: rgba(255, 255, 255, 0.02);
        }

        .job-table tbody tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        .btn-delete {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            font-weight: 600;
            border: 1px solid rgba(239, 68, 68, 0.3);
            cursor: pointer;
            transition: all var(--transition-fast);
        }

        .btn-delete:hover {
            background: #ef4444;
            color: #ffffff;
            border-color: #ef4444;
            transform: scale(1.03);
        }

        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-top: 8px;
            margin-bottom: 16px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .checkbox-item input {
            cursor: pointer;
            accent-color: var(--accent);
            width: 18px;
            height: 18px;
        }

        .textarea-hint {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: -6px;
            margin-bottom: 12px;
            display: block;
        }

        .db-troubleshoot {
            margin-top: 20px;
            padding: 20px;
            background: rgba(239, 68, 68, 0.05);
            border-radius: var(--radius-md);
            border: 1px dashed rgba(239, 68, 68, 0.3);
            font-size: 0.9rem;
        }

        .db-troubleshoot h4 {
            color: #f87171;
            margin-top: 0;
            margin-bottom: 10px;
        }

        .db-troubleshoot ol {
            padding-left: 20px;
            margin-bottom: 0;
            line-height: 1.6;
        }

        .logout-link {
            padding: 10px 20px;
            border-radius: var(--radius-sm);
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            font-size: 0.9rem;
            font-weight: 600;
            transition: all var(--transition-fast);
        }

        .logout-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
            border-color: rgba(255, 255, 255, 0.2);
        }

        .btn-full {
            width: 100%;
            justify-content: center;
        }
    </style>
</head>
<body>

    <!-- Animated Mesh Background -->
    <div class="mesh-bg" aria-hidden="true">
        <div class="mesh-blob blob-1"></div>
        <div class="mesh-blob blob-2"></div>
        <div class="mesh-blob blob-3"></div>
        <div class="mesh-blob blob-4"></div>
    </div>

    <?php if (!$isLoggedIn): ?>
        <!-- LOGIN SCREEN -->
        <main>
            <div class="glass-card login-card">
                <div class="card-shine"></div>
                <img src="Bilder/Logo.png" alt="Vemaro Logo" class="login-logo">
                
                <h1 style="font-family: var(--font-heading); font-size: 1.5rem; margin-bottom: 24px;">Admin-Portal Login</h1>

                <?php if ($loginError !== ''): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($loginError); ?></div>
                <?php endif; ?>

                <form method="post" action="vemaro-admin-portal.php" novalidate>
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    
                    <div class="form-group" style="text-align: left; margin-bottom: 24px;">
                        <label for="adminPassword">Passwort eingeben</label>
                        <input type="password" id="adminPassword" name="password" placeholder="••••••••••••" required autofocus style="width: 100%;">
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-full">Einloggen</button>
                </form>
            </div>
        </main>
    <?php else: ?>
        <!-- ADMIN DASHBOARD -->
        <header>
            <nav class="glass-nav" id="navbar" aria-label="Admin Navigation" style="position: absolute;">
                <div class="nav-container">
                    <a href="index.html" class="nav-logo" aria-label="Vemaro Startseite">
                        <img src="Bilder/Logo.png" alt="Vemaro Logo" class="nav-logo-img" width="120" height="48">
                    </a>
                    <span style="font-weight: 700; font-family: var(--font-heading); font-size: 1.1rem; color: #ffffff;">Admin-Portal</span>
                    <a href="vemaro-admin-portal.php?action=logout" class="logout-link">Abmelden</a>
                </div>
            </nav>
        </header>

        <main class="admin-container">
            <div class="admin-header">
                <div>
                    <h1 style="font-family: var(--font-heading); font-size: 2.2rem; font-weight: 800; margin-bottom: 8px;">Stellenangebote verwalten</h1>
                    <p style="color: var(--text-secondary); margin: 0;">Hier können Sie neue Jobs anlegen und bestehende Angebote löschen.</p>
                </div>
                <div>
                    <div class="db-status">
                        <span class="status-indicator <?php echo $dbOnline ? 'status-online' : 'status-offline'; ?>"></span>
                        <span><?php echo $dbOnline ? 'Mit Datenbank verbunden' : 'Keine Datenbankverbindung'; ?></span>
                    </div>
                </div>
            </div>

            <?php if ($actionError !== ''): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($actionError); ?></div>
            <?php endif; ?>
            <?php if ($actionSuccess !== ''): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($actionSuccess); ?></div>
            <?php endif; ?>

            <?php if (!$dbOnline): ?>
                <!-- FEHLENDE DATENBANK-VERBINDUNG -->
                <div class="glass-card admin-card" style="border-color: rgba(239, 68, 68, 0.3);">
                    <div class="card-shine"></div>
                    <h2 class="admin-title" style="color: #f87171; border-color: rgba(239, 68, 68, 0.2);">Datenbank offline</h2>
                    <p>Das Admin-Portal benötigt eine aktive MySQL-Datenbank. Bitte führen Sie folgende Schritte durch:</p>
                    <div class="db-troubleshoot">
                        <h4>Fehlerbehebung für lokale Entwicklung (XAMPP):</h4>
                        <ol>
                            <li>Starten Sie <strong>Apache</strong> und <strong>MySQL</strong> im XAMPP Control Panel.</li>
                            <li>Öffnen Sie <strong>MySQL Workbench</strong> und importieren Sie die Datei <a href="file:///Users/leardmucolli/Desktop/LUANA/Vemaro/schema.sql" style="color: #3b82f6; text-decoration: underline;">schema.sql</a>.</li>
                            <li>Überprüfen Sie die Konfiguration in <a href="file:///Users/leardmucolli/Desktop/LUANA/Vemaro/handler/config.php" style="color: #3b82f6; text-decoration: underline;">handler/config.php</a> (DB_HOST, DB_NAME, DB_USER, DB_PASS).</li>
                        </ol>
                        <h4 style="margin-top: 15px;">Fehlerbehebung für Produktion (IONOS):</h4>
                        <ol>
                            <li>Erstellen Sie im IONOS Kundencenter eine neue MySQL-Datenbank.</li>
                            <li>Tragen Sie die von IONOS vergebenen Zugangsdaten in <a href="file:///Users/leardmucolli/Desktop/LUANA/Vemaro/handler/config.php" style="color: #3b82f6; text-decoration: underline;">handler/config.php</a> ein.</li>
                            <li>Importieren Sie die Tabellenstruktur aus <a href="file:///Users/leardmucolli/Desktop/LUANA/Vemaro/schema.sql" style="color: #3b82f6; text-decoration: underline;">schema.sql</a> über phpMyAdmin von IONOS.</li>
                        </ol>
                    </div>
                </div>
            <?php else: ?>
                <!-- ADMIN-OBERFLÄCHE -->
                <div class="admin-grid">
                    
                    <!-- LINKSEITE: AKTUELLE JOBS -->
                    <div class="glass-card admin-card">
                        <div class="card-shine"></div>
                        <h2 class="admin-title">Aktive Stellenangebote</h2>
                        
                        <?php if (empty($jobsList)): ?>
                            <p style="color: var(--text-secondary); text-align: center; padding: 40px 0;">Aktuell sind keine Stellen in der Datenbank vorhanden.</p>
                        <?php else: ?>
                            <div class="job-table-wrap">
                                <table class="job-table">
                                    <thead>
                                        <tr>
                                            <th>Referenz-ID</th>
                                            <th>Titel</th>
                                            <th>Ort</th>
                                            <th style="text-align: right;">Aktion</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($jobsList as $job): ?>
                                            <tr>
                                                <td style="font-weight: 600; color: var(--accent-bright);"><?php echo htmlspecialchars($job['id']); ?></td>
                                                <td><strong><?php echo htmlspecialchars($job['title']); ?></strong></td>
                                                <td style="color: var(--text-secondary); font-size: 0.88rem;"><?php echo htmlspecialchars(mb_strimwidth($job['location'], 0, 45, '...')); ?></td>
                                                <td style="text-align: right;">
                                                    <form class="delete-job-form" method="post" action="vemaro-admin-portal.php">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                        <input type="hidden" name="job_id" value="<?php echo htmlspecialchars($job['id']); ?>">
                                                        <button type="button" class="btn-delete delete-trigger-btn" data-job-id="<?php echo htmlspecialchars($job['id']); ?>">Löschen</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- RECHTSEITE: NEUE STELLE ERSTELLEN -->
                    <div class="glass-card admin-card">
                        <div class="card-shine"></div>
                        <h2 class="admin-title" style="display: flex; justify-content: space-between; align-items: center;">
                            <span>Stelle hinzufügen</span>
                            <button type="button" class="ai-prompt-trigger-btn" id="openAiPromptBtn" title="KI-Prompt Generator" style="background-image: url('Bilder/ai_button.jpg'); background-repeat: no-repeat; background-position: center center; background-size: cover; width: 64px; height: 64px; border-radius: 50%; border: 2px solid rgba(var(--accent-rgb), 0.40); cursor: pointer; transition: all var(--transition-smooth); box-shadow: 0 0 14px rgba(var(--accent-rgb), 0.25); padding: 0;">
                            </button>
                        </h2>
                        
                        <form class="contact-form" method="post" action="vemaro-admin-portal.php" novalidate>
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="jobId">Referenz-ID *</label>
                                    <input type="text" id="jobId" name="job_id" placeholder="z. B. JOB-WV-001" required value="<?php echo htmlspecialchars($_POST['job_id'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="title">Job-Titel *</label>
                                    <input type="text" id="title" name="title" placeholder="z. B. Warenverräumer (m/w/d)" required value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="location">Einsatzort *</label>
                                    <input type="text" id="location" name="location" placeholder="z. B. Bremen und Umgebung" required value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="workdays">Arbeitstage *</label>
                                    <input type="text" id="workdays" name="workdays" placeholder="z. B. Mo-Sa" required value="<?php echo htmlspecialchars($_POST['workdays'] ?? 'Mo-Sa'); ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="startDate">Startdatum *</label>
                                    <input type="text" id="startDate" name="startDate" placeholder="z. B. ab sofort" required value="<?php echo htmlspecialchars($_POST['startDate'] ?? 'ab sofort'); ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Anstellungsarten *</label>
                                <div class="checkbox-group">
                                    <?php
                                    $defaultTypes = ['Minijob', 'Teilzeit', 'Werkstudent', 'Vollzeit'];
                                    $selectedTypes = $_POST['employmentTypes'] ?? ['Minijob', 'Teilzeit', 'Werkstudent', 'Vollzeit'];
                                    foreach ($defaultTypes as $type):
                                        $checked = in_array($type, $selectedTypes) ? 'checked' : '';
                                    ?>
                                        <label class="checkbox-item">
                                            <input type="checkbox" name="employmentTypes[]" value="<?php echo $type; ?>" <?php echo $checked; ?>>
                                            <span><?php echo $type; ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="description">Kurzbeschreibung *</label>
                                <textarea id="description" name="description" placeholder="Beschreiben Sie die Stelle in 2-3 Sätzen..." rows="3" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="tasks">Aufgaben (Ein Punkt pro Zeile) *</label>
                                <textarea id="tasks" name="tasks" placeholder="Verräumen von Waren&#10;Pflege der Regalflächen&#10;Kontrolle von Frische" rows="4" required><?php echo htmlspecialchars($_POST['tasks'] ?? ''); ?></textarea>
                                <span class="textarea-hint">Drücken Sie ENTER für einen neuen Aufzählungspunkt.</span>
                            </div>

                            <div class="form-group">
                                <label for="requirements">Anforderungen (Ein Punkt pro Zeile) *</label>
                                <textarea id="requirements" name="requirements" placeholder="Teamfähigkeit und Zuverlässigkeit&#10;Körperliche Fitness&#10;Sehr gute Deutschkenntnisse" rows="4" required><?php echo htmlspecialchars($_POST['requirements'] ?? ''); ?></textarea>
                                <span class="textarea-hint">Drücken Sie ENTER für einen neuen Aufzählungspunkt.</span>
                            </div>

                            <div class="form-group">
                                <label for="benefits">Vorteile / Wir bieten (Ein Punkt pro Zeile) *</label>
                                <textarea id="benefits" name="benefits" placeholder="Faire und pünktliche Bezahlung&#10;Unbefristeter Arbeitsvertrag&#10;Persönlicher Ansprechpartner" rows="4" required><?php echo htmlspecialchars($_POST['benefits'] ?? ''); ?></textarea>
                                <span class="textarea-hint">Drücken Sie ENTER für einen neuen Aufzählungspunkt.</span>
                            </div>

                            <button type="submit" class="btn btn-primary btn-full" style="margin-top: 10px;">Stelle anlegen</button>
                        </form>
                    </div>

                </div>
            <?php endif; ?>
        </main>
    <?php endif; ?>

    <!-- Custom Modal for Delete Confirmation -->
    <div id="deleteConfirmModal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="modalTitle" aria-describedby="modalDesc">
        <div class="glass-card modal-card">
            <div class="card-shine"></div>
            <div class="modal-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
            </div>
            <h3 class="modal-title" id="modalTitle">Stelle löschen?</h3>
            <p class="modal-text" id="modalDesc">Möchten Sie die Stelle <strong id="deleteJobIdText" style="color: var(--accent-bright);"></strong> wirklich unwiderruflich löschen?</p>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary modal-btn" id="cancelDeleteBtn">Abbrechen</button>
                <button type="button" class="btn btn-primary modal-btn" id="confirmDeleteBtn" style="background: linear-gradient(135deg, #ef4444, #dc2626); border: none; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);">Löschen</button>
            </div>
        </div>
    </div>

    <!-- Custom Modal for AI Prompt Generator -->
    <div id="aiPromptModal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="aiModalTitle" aria-describedby="aiModalDesc">
        <div class="glass-card modal-card" style="max-width: 600px;">
            <div class="card-shine"></div>
            <h3 class="modal-title" id="aiModalTitle">KI-Prompt Generator</h3>
            <p class="modal-text" id="aiModalDesc" style="margin-bottom: 20px;">Geben Sie einen Jobtitel ein, um einen perfekt optimierten Prompt für ChatGPT, Gemini & Co. zu generieren.</p>
            
            <div class="form-group" style="text-align: left; margin-bottom: 20px;">
                <label for="aiJobInput">Gewünschter Jobtitel *</label>
                <input type="text" id="aiJobInput" placeholder="z. B. Kommissionierer (m/w/d)" style="width: 100%;">
            </div>

            <div class="form-group" style="text-align: left; margin-bottom: 20px;">
                <label for="aiPromptOutput">Generierter Prompt</label>
                <textarea id="aiPromptOutput" readonly style="width: 100%; height: 240px; font-family: monospace; font-size: 0.85rem; padding: 12px; background: #020203; color: #34d399; border: 1px solid rgba(255,255,255,0.1); border-radius: var(--radius-sm); resize: none;"></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary modal-btn" id="closeAiModalBtn">Schließen</button>
                <button type="button" class="btn btn-primary modal-btn" id="copyAiPromptBtn" style="background: var(--accent); border: none;">Prompt kopieren</button>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- Deletion Modal ---
        var deleteButtons = document.querySelectorAll('.delete-trigger-btn');
        var deleteModal = document.getElementById('deleteConfirmModal');
        var jobIdText = document.getElementById('deleteJobIdText');
        var cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
        var confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        var activeForm = null;

        deleteButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                var jobId = button.getAttribute('data-job-id');
                activeForm = button.closest('form');
                jobIdText.textContent = jobId;
                deleteModal.classList.add('show');
            });
        });

        function closeDeleteModal() {
            deleteModal.classList.remove('show');
            activeForm = null;
        }

        cancelDeleteBtn.addEventListener('click', closeDeleteModal);

        // --- AI Prompt Modal ---
        var aiModal = document.getElementById('aiPromptModal');
        var openAiBtn = document.getElementById('openAiPromptBtn');
        var closeAiBtn = document.getElementById('closeAiModalBtn');
        var copyAiBtn = document.getElementById('copyAiPromptBtn');
        var aiJobInput = document.getElementById('aiJobInput');
        var aiPromptOutput = document.getElementById('aiPromptOutput');

        function getAiPromptText(jobTitle) {
            var title = jobTitle.trim() || '[Jobtitel]';
            return 'Agiere als professioneller HR-Recruiter. Generiere ein detailliertes Stellenangebot für den Job: "' + title + '" im exakten Format wie folgt:\n\n' +
                '[Job-Titel]\n' +
                title + '\n\n' +
                '[Kurzbeschreibung]\n' +
                '(Schreibe eine Kurzbeschreibung der Stelle in 2-3 Sätzen für den Einstiegstext)\n\n' +
                '[Aufgaben]\n' +
                '(Liste die wichtigsten 5-6 Aufgaben für diese Stelle auf, jeweils in einer neuen Zeile, ohne Aufzählungszeichen oder Gedankenstriche)\n\n' +
                '[Anforderungen]\n' +
                '(Liste die wichtigsten 5-6 Anforderungen/Qualifikationen auf, jeweils in einer neuen Zeile, ohne Aufzählungszeichen oder Gedankenstriche)\n\n' +
                '[Vorteile]\n' +
                '(Liste die wichtigsten 5-6 Vorteile/Benefits für den Mitarbeiter auf, jeweils in einer neuen Zeile, ohne Aufzählungszeichen oder Gedankenstriche)\n\n' +
                'Wichtig: Antworte ausschließlich in diesem Format. Schreibe keine zusätzliche Einleitung, kein "Gerne" oder Ähnliches, sondern fange sofort mit dem Format an, da ich es direkt in eine Datenbank einpflegen möchte. Trenne die Blöcke durch eine Leerzeile.';
        }

        if (openAiBtn && aiModal) {
            openAiBtn.addEventListener('click', function() {
                aiModal.classList.add('show');
                aiJobInput.value = '';
                aiPromptOutput.value = getAiPromptText('');
                setTimeout(function() {
                    aiJobInput.focus();
                }, 100);
            });
        }

        function closeAiModal() {
            if (aiModal) {
                aiModal.classList.remove('show');
            }
        }

        if (closeAiBtn) {
            closeAiBtn.addEventListener('click', closeAiModal);
        }

        if (aiJobInput) {
            aiJobInput.addEventListener('input', function() {
                aiPromptOutput.value = getAiPromptText(aiJobInput.value);
            });
        }

        if (copyAiBtn && aiPromptOutput) {
            copyAiBtn.addEventListener('click', function() {
                aiPromptOutput.select();
                aiPromptOutput.setSelectionRange(0, 99999);
                navigator.clipboard.writeText(aiPromptOutput.value).then(function() {
                    var originalText = copyAiBtn.textContent;
                    copyAiBtn.textContent = 'Kopiert ✓';
                    copyAiBtn.style.background = 'linear-gradient(135deg, #34d399, #10b981)';
                    setTimeout(function() {
                        copyAiBtn.textContent = originalText;
                        copyAiBtn.style.background = '';
                    }, 2000);
                });
            });
        }

        // --- Global backdrop/escape closures ---
        window.addEventListener('click', function(e) {
            if (e.target === deleteModal) {
                closeDeleteModal();
            }
            if (e.target === aiModal) {
                closeAiModal();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (deleteModal.classList.contains('show')) {
                    closeDeleteModal();
                }
                if (aiModal && aiModal.classList.contains('show')) {
                    closeAiModal();
                }
            }
        });

        confirmDeleteBtn.addEventListener('click', function() {
            if (activeForm) {
                activeForm.submit();
            }
        });
    });
    </script>

</body>
</html>
