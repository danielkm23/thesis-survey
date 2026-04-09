<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/db.php';

$dashboardSessionKey = 'dashboard_authenticated';
if (session_get($dashboardSessionKey) !== true) {
    http_response_code(403);
    exit('Forbidden.');
}

$allowedTables = [
    'participants',
    'task_responses',
    'document_events',
    'postsurvey_responses',
];

$table = (string) ($_GET['table'] ?? '');
if (!in_array($table, $allowedTables, true)) {
    http_response_code(400);
    exit('Invalid table. Use one of: participants, task_responses, document_events, postsurvey_responses');
}

$stmt = db()->query('SELECT * FROM ' . $table . ' ORDER BY id ASC');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $table . '_' . date('Ymd_His') . '.csv"');

$output = fopen('php://output', 'wb');
if ($output === false) {
    http_response_code(500);
    exit('Could not start CSV export.');
}

if (!empty($rows)) {
    fputcsv($output, array_keys($rows[0]));
    foreach ($rows as $row) {
        fputcsv($output, $row);
    }
} else {
    fputcsv($output, ['message']);
    fputcsv($output, ['No data found']);
}

fclose($output);
