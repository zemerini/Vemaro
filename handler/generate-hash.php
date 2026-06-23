<?php
declare(strict_types=1);

/*
 * Vemaro – Passwort-Hasher Utility
 * Autor: Leard Mucolli
 *
 * Verwenden Sie dieses Skript, um einen sicheren Hash für ein neues Admin-Passwort zu generieren.
 * Kopieren Sie den generierten Code in die 'handler/config.php' unter 'ADMIN_PASSWORD_HASH'.
 *
 * WICHTIG: Löschen Sie dieses Skript nach der Verwendung vom Server!
 */

$passwordToHash = 'Vemaro2026Admin!'; // Tragen Sie hier Ihr Wunschpasswort ein

$hash = password_hash($passwordToHash, PASSWORD_DEFAULT);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Vemaro – Passwort Hasher</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #050506;
            color: #EDEDEF;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.5);
            max-width: 600px;
            width: 100%;
        }
        h2 { color: #3b82f6; margin-top: 0; }
        pre {
            background: #020203;
            padding: 15px;
            border-radius: 6px;
            overflow-x: auto;
            border: 1px solid rgba(255, 255, 255, 0.05);
            color: #34d399;
            font-size: 14px;
        }
        .warning {
            color: #f87171;
            font-weight: bold;
            margin-top: 20px;
        }
    </style>
    <link rel="icon" type="image/png" href="../Bilder/Logo.png">
</head>
<body>
    <div class="card">
        <h2>Passwort-Hash Generator</h2>
        <p>Passwort: <code><?php echo htmlspecialchars($passwordToHash); ?></code></p>
        <p>Kopieren Sie den folgenden Hash in Ihre <strong>handler/config.php</strong>:</p>
        <pre>define('ADMIN_PASSWORD_HASH', '<?php echo htmlspecialchars($hash); ?>');</pre>
        <p class="warning">Sicherheitshinweis: Löschen Sie dieses Skript (generate-hash.php) umgehend nach der Verwendung vom Server!</p>
    </div>
</body>
</html>
