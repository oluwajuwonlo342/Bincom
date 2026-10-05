<?php
// JSON endpoints for the chained combo boxes
require 'config.php';
header('Content-Type: application/json');
try {
    $a = $_GET['action'] ?? '';
    if ($a === 'wards') {
        $st = db()->prepare('SELECT ward_id, ward_name FROM ward WHERE lga_id = ? ORDER BY ward_name');
        $st->execute([$_GET['lga_id'] ?? 0]);
        echo json_encode($st->fetchAll());
    } elseif ($a === 'pus') {
        $st = db()->prepare('SELECT uniqueid, polling_unit_name, polling_unit_number FROM polling_unit
                             WHERE lga_id = ? AND ward_id = ? ORDER BY polling_unit_name');
        $st->execute([$_GET['lga_id'] ?? 0, $_GET['ward_id'] ?? 0]);
        echo json_encode($st->fetchAll());
    } elseif ($a === 'pu_results') {
        $st = db()->prepare('SELECT party_abbreviation, party_score FROM announced_pu_results
                             WHERE polling_unit_uniqueid = ? ORDER BY party_score DESC');
        $st->execute([$_GET['pu'] ?? 0]);
        echo json_encode($st->fetchAll());
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
    }
} catch (Throwable $ex) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
