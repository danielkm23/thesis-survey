<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/analysis.php';
require_once __DIR__ . '/../app/document_inspection_export.php';

$dashboardSessionKey = 'dashboard_authenticated';
if (session_get($dashboardSessionKey) !== true) {
    http_response_code(403);
    exit('Forbidden.');
}

function ai_literacy_item_export_rows(PDO $pdo, bool $includeTestParticipants = false): array
{
    $items = [
        1 => 'AI-generated responses can sound convincing even when they are inaccurate.',
        2 => 'When information from an AI system is important, it is worth checking its accuracy.',
        3 => 'AI systems may produce information without relying on verified or reliable sources.',
        4 => 'Even when an AI response appears clear, it may still be incomplete or uncertain.',
    ];

    $sql = 'SELECT
            p.id AS participant_id,
            ps.ai_lit_1,
            ps.ai_lit_2,
            ps.ai_lit_3,
            ps.ai_lit_4
         FROM participants p
         INNER JOIN (
            SELECT ps1.*
            FROM postsurvey_responses ps1
            INNER JOIN (
                SELECT participant_id, MAX(id) AS latest_id
                FROM postsurvey_responses
                GROUP BY participant_id
            ) latest ON latest.latest_id = ps1.id
         ) ps ON ps.participant_id = p.id';

    if (!$includeTestParticipants) {
        $sql .= ' WHERE p.participant_code NOT LIKE :test_prefix';
    }
    $sql .= ' ORDER BY p.id ASC';

    $stmt = $pdo->prepare($sql);
    if (!$includeTestParticipants) {
        $testPrefix = defined('TEST_PARTICIPANT_PREFIX') ? (string) TEST_PARTICIPANT_PREFIX : 'TEST-';
        $stmt->bindValue(':test_prefix', $testPrefix . '%', PDO::PARAM_STR);
    }
    $stmt->execute();

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $exportRow = [
            'participant_id' => (string) ($row['participant_id'] ?? ''),
        ];
        foreach ($items as $itemNumber => $itemText) {
            $exportRow['ai_lit_' . $itemNumber . '_item'] = $itemText;
            $exportRow['ai_lit_' . $itemNumber . '_score'] = (string) ($row['ai_lit_' . $itemNumber] ?? '');
        }
        $rows[] = $exportRow;
    }

    return $rows;
}

$allowedTables = [
    'participants',
    'task_responses',
    'document_events',
    'postsurvey_responses',
    'raffle_entries',
    'analysis_task_level',
    'analysis_participant_summary',
    'document_inspection_data',
    'ai_literacy_items',
];

$table = (string) ($_GET['table'] ?? '');
$includeTestParticipants = ((string) ($_GET['include_test'] ?? '0')) === '1';
if (!in_array($table, $allowedTables, true)) {
    http_response_code(400);
    exit('Invalid table.');
}

$pdo = db();

if ($table === 'analysis_task_level') {
    $rows = analysis_task_level($pdo, $includeTestParticipants);
} elseif ($table === 'analysis_participant_summary') {
    $rows = analysis_participant_summary($pdo, $includeTestParticipants);
} elseif ($table === 'document_inspection_data') {
    $rows = document_inspection_export_rows($pdo, $includeTestParticipants);
} elseif ($table === 'ai_literacy_items') {
    $rows = ai_literacy_item_export_rows($pdo, $includeTestParticipants);
} elseif ($table === 'task_responses') {
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
    fputcsv($output, array_keys($rows[0]), ',', '"', '\\');
    foreach ($rows as $row) {
        fputcsv($output, $row, ',', '"', '\\');
    }
} else {
    fputcsv($output, ['message'], ',', '"', '\\');
    fputcsv($output, ['No data found'], ',', '"', '\\');
}

fclose($output);
