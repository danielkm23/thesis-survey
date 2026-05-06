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

$pdo = db();

if ($table === 'task_responses') {
    $hasSelectedResponseOptionColumn = false;
    $hasCustomResponseTextColumn = false;
    try {
        $selectedResponseOptionCheck = $pdo->query("SHOW COLUMNS FROM task_responses LIKE 'selected_response_option'");
        $hasSelectedResponseOptionColumn = $selectedResponseOptionCheck !== false && $selectedResponseOptionCheck->fetch() !== false;
        $customResponseTextCheck = $pdo->query("SHOW COLUMNS FROM task_responses LIKE 'custom_response_text'");
        $hasCustomResponseTextColumn = $customResponseTextCheck !== false && $customResponseTextCheck->fetch() !== false;
    } catch (Throwable $e) {
        $hasSelectedResponseOptionColumn = false;
        $hasCustomResponseTextColumn = false;
    }

    $selectedResponseOptionFallback = "CASE
        WHEN LOCATE('selected_response_option=', tr.active_reflection) > 0
            THEN TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(tr.active_reflection, 'selected_response_option=', -1), '\n', 1))
        ELSE NULL
    END";
    $customResponseTextFallback = "CASE
        WHEN LOCATE('custom_response_text=', tr.active_reflection) > 0
            THEN TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(tr.active_reflection, 'custom_response_text=', -1), '\n', 1))
        ELSE NULL
    END";

    $selectedResponseOptionSelect = $hasSelectedResponseOptionColumn
        ? 'COALESCE(tr.selected_response_option, ' . $selectedResponseOptionFallback . ') AS selected_response_option'
        : $selectedResponseOptionFallback . ' AS selected_response_option';
    $customResponseTextSelect = $hasCustomResponseTextColumn
        ? 'COALESCE(tr.custom_response_text, ' . $customResponseTextFallback . ') AS custom_response_text'
        : $customResponseTextFallback . ' AS custom_response_text';

    $stmt = $pdo->query(
        'SELECT
            tr.id,
            tr.participant_id,
            p.condition_name,
            tr.task_number,
            tr.ai_correct,
            tr.reliance_choice,
            tr.final_response,
            tr.confidence,
            tr.verification_intention,
            ' . $selectedResponseOptionSelect . ',
            ' . $customResponseTextSelect . ',
            tr.task_started_at,
            tr.task_submitted_at,
            tr.duration_seconds,
            tr.short_time_flag,
            tr.active_reflection
         FROM task_responses tr
         LEFT JOIN participants p ON p.id = tr.participant_id
         ORDER BY tr.id ASC'
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->query('SELECT * FROM ' . $table . ' ORDER BY id ASC');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

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
