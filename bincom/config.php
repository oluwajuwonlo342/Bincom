<?php

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'bincomphptest');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('STATE_ID', 25);

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function get_lgas(): array {
    $st = db()->prepare('SELECT lga_id, lga_name FROM lga WHERE state_id = ? ORDER BY lga_name');
    $st->execute([STATE_ID]);
    return $st->fetchAll();
}

function page_start(string $title, string $active): void {
    $links = ['index.php' => 'Home', 'q1.php' => 'Q1: Polling Unit Result', 'q2.php' => 'Q2: LGA Summed Result', 'q3.php' => 'Q3: Add Polling Unit'];
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($title) . ' | Bincom Elections</title><link rel="stylesheet" href="style.css"></head><body>';
    echo '<header><h1>Bincom Election Results</h1><nav>';
    foreach ($links as $href => $label) {
        echo '<a href="' . $href . '"' . ($href === $active ? ' class="active"' : '') . '>' . e($label) . '</a>';
    }
    echo '</nav></header><main><h2>' . e($title) . '</h2>';
}
function page_end(): void { echo '</main><script src="app.js"></script></body></html>'; }
