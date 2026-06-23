<?php
declare(strict_types=1);

/*
 * Vemaro – CSRF-Token-Generator
 * Autor: Leard Mucolli
 *
 * Generiert ein CSRF-Token und gibt es als JSON zurück.
 * Unterstützt verschiedene Formulare über den ?form= Parameter:
 *   - ?form=application  → Session-Key: csrf_token (Standard)
 *   - ?form=contact      → Session-Key: csrf_token_contact
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');

session_start();

$form = trim((string)($_GET['form'] ?? 'application'));
$token = bin2hex(random_bytes(32));

$allowedForms = [
    'application' => 'csrf_token',
    'contact'     => 'csrf_token_contact',
];

$sessionKey = $allowedForms[$form] ?? 'csrf_token';
$_SESSION[$sessionKey] = $token;

echo json_encode(['token' => $token], JSON_UNESCAPED_UNICODE);
