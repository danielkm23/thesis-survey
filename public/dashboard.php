<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/analysis.php';

/**
 * Temporary lightweight password gate for internal dashboard access.
 * Default password is "DASHBOARD" and can be overridden via DASHBOARD_PASSWORD.
 */
$dashboardSessionKey = 'dashboard_authenticated';
$expectedPassword = env_or_default('DASHBOARD_PASSWORD', 'DASHBOARD');
$dashboardCsrfSessionKey = 'dashboard_csrf_token';

if (isset($_GET['logout'])) {
    unset($_SESSION[$dashboardSessionKey]);
    redirect('/dashboard/');
}

$authError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && session_get($dashboardSessionKey) !== true) {
    $providedPassword = (string) ($_POST['dashboard_password'] ?? '');
    if (hash_equals($expectedPassword, $providedPassword)) {
        session_set($dashboardSessionKey, true);
        redirect('/dashboard/');
    }
    $authError = 'Invalid password.';
}

if (session_get($dashboardSessionKey) !== true) {
    $pageTitle = 'Dashboard Access';
    require __DIR__ . '/../views/header.php';
    ?>
    <main class="max-w-md mx-auto px-4 py-12">
        <section class="bg-white shadow rounded-xl p-6">
            <h1 class="text-xl font-semibold text-slate-800 mb-2">Dashboard Access</h1>
            <p class="text-sm text-slate-600 mb-4">Enter the dashboard password to continue.</p>
            <?php if ($authError !== null): ?>
                <p class="mb-3 text-sm text-red-600"><?= e($authError) ?></p>
            <?php endif; ?>
            <form method="post" action="/dashboard/" class="space-y-4">
                <div>
                    <label for="dashboard_password" class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                    <input
                        id="dashboard_password"
                        name="dashboard_password"
                        type="password"
                        required
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    >
                </div>
                <button
                    type="submit"
                    class="w-full accent-bg accent-bg-hover text-white font-medium px-4 py-2 rounded-lg transition"
                >
                    Open Dashboard
                </button>
            </form>
        </section>
    </main>
    <?php
    require __DIR__ . '/../views/footer.php';
    exit;
}

if (session_get($dashboardCsrfSessionKey) === null) {
    session_set($dashboardCsrfSessionKey, bin2hex(random_bytes(16)));
}
$dashboardCsrfToken = (string) session_get($dashboardCsrfSessionKey, '');
$flashSuccess = (string) ($_SESSION['dashboard_flash_success'] ?? '');
$flashError = (string) ($_SESSION['dashboard_flash_error'] ?? '');
unset($_SESSION['dashboard_flash_success'], $_SESSION['dashboard_flash_error']);

/**
 * Convert UTC datetime strings to Europe/Amsterdam for dashboard display.
 * Storage remains unchanged in the database.
 */
function format_dashboard_datetime(string $value): string
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return $value;
    }

    try {
        $utc = new DateTimeZone('UTC');
        $amsterdam = new DateTimeZone('Europe/Amsterdam');
        $dt = new DateTimeImmutable($trimmed, $utc);
        return $dt->setTimezone($amsterdam)->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return $value;
    }
}

function is_close_enough(float $value, float $target, float $tolerance = 0.01): bool
{
    return abs($value - $target) <= $tolerance;
}

function int_in_range_or_null(mixed $value, int $min, int $max): ?int
{
    if ($value === null) {
        return null;
    }
    $validated = filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => $min, 'max_range' => $max],
    ]);
    if ($validated === false) {
        return null;
    }
    return (int) $validated;
}

/**
 * Builds participant-level rows for the Data for analysis dashboard/export.
 */
function build_analysis_data_for_analysis_rows(
    array $participantRows,
    array $taskRowsByParticipant,
    array $postsurveyByParticipant
): array {
    $rows = [];
    foreach ($participantRows as $participantRow) {
        $participantId = (int) ($participantRow['participant_id'] ?? 0);
        if ($participantId <= 0) {
            continue;
        }

        $task1 = $taskRowsByParticipant[$participantId][1] ?? null;
        $task2 = $taskRowsByParticipant[$participantId][2] ?? null;
        $postRow = $postsurveyByParticipant[$participantId] ?? null;

        $aiLiteracyPoints = null;
        if (is_array($postRow)) {
            $aiLitValues = [
                int_in_range_or_null($postRow['ai_lit_1'] ?? null, 1, 5),
                int_in_range_or_null($postRow['ai_lit_2'] ?? null, 1, 5),
                int_in_range_or_null($postRow['ai_lit_3'] ?? null, 1, 5),
                int_in_range_or_null($postRow['ai_lit_4'] ?? null, 1, 5),
            ];
            $hasAllAiLit = !in_array(null, $aiLitValues, true);
            if ($hasAllAiLit) {
                $aiLiteracyPoints = (float) array_sum($aiLitValues);
            }
        }

        $crtCorrectCount = null;
        if (is_array($postRow)) {
            $crt1 = isset($postRow['crt_1']) ? (float) $postRow['crt_1'] : null;
            $crt2 = isset($postRow['crt_2']) ? (float) $postRow['crt_2'] : null;
            $crt3 = isset($postRow['crt_3']) ? (float) $postRow['crt_3'] : null;
            if ($crt1 !== null && $crt2 !== null && $crt3 !== null) {
                $crtCorrectCount = 0.0;
                if (is_close_enough($crt1, 0.05)) {
                    $crtCorrectCount += 1.0;
                }
                if (is_close_enough($crt2, 5.0)) {
                    $crtCorrectCount += 1.0;
                }
                if (is_close_enough($crt3, 47.0)) {
                    $crtCorrectCount += 1.0;
                }
            }
        }

        $task1Duration = is_array($task1) && ($task1['duration_seconds'] ?? null) !== null
            ? (float) $task1['duration_seconds']
            : null;
        $task2Duration = is_array($task2) && ($task2['duration_seconds'] ?? null) !== null
            ? (float) $task2['duration_seconds']
            : null;
        $postsurveyDuration = is_array($postRow) && ($postRow['duration_seconds'] ?? null) !== null
            ? (float) $postRow['duration_seconds']
            : null;

        $totalSurveyDuration = null;
        $startedAtRaw = (string) ($participantRow['started_at'] ?? '');
        $postsurveySubmittedAtRaw = is_array($postRow) ? (string) ($postRow['submitted_at'] ?? '') : '';
        $startedTs = $startedAtRaw !== '' ? strtotime($startedAtRaw) : false;
        $submittedTs = $postsurveySubmittedAtRaw !== '' ? strtotime($postsurveySubmittedAtRaw) : false;
        if ($startedTs !== false && $submittedTs !== false && $submittedTs >= $startedTs) {
            $totalSurveyDuration = (float) ($submittedTs - $startedTs);
        } elseif ($task1Duration !== null || $task2Duration !== null || $postsurveyDuration !== null) {
            $totalSurveyDuration = (float) (($task1Duration ?? 0.0) + ($task2Duration ?? 0.0) + ($postsurveyDuration ?? 0.0));
        }

        $calibrationErrorSum = 0.0;
        $calibrationErrorCount = 0;
        $totalDocTimeSecSum = 0.0;
        $hasAnyTotalDocTime = false;
        foreach ([$task1, $task2] as $taskRowForAggregate) {
            if (!is_array($taskRowForAggregate)) {
                continue;
            }
            if (($taskRowForAggregate['confidence'] ?? null) !== null && ($taskRowForAggregate['final_decision_correct'] ?? null) !== null) {
                $normalizedConfidence = ((float) $taskRowForAggregate['confidence']) / 5.0;
                $correctness = (float) $taskRowForAggregate['final_decision_correct'];
                $calibrationErrorSum += abs($normalizedConfidence - $correctness);
                $calibrationErrorCount++;
            }
            if (($taskRowForAggregate['total_document_view_time_sec'] ?? null) !== null) {
                $totalDocTimeSecSum += (float) $taskRowForAggregate['total_document_view_time_sec'];
                $hasAnyTotalDocTime = true;
            }
        }
        $calibrationScore = $calibrationErrorCount > 0
            ? round(1.0 - ($calibrationErrorSum / $calibrationErrorCount), 4)
            : null;
        $avgConfidence = $participantRow['avg_confidence'] ?? null;
        $avgDocsOpened = $participantRow['avg_docs_opened'] ?? null;
        $totalDocTimeSec = $hasAnyTotalDocTime ? round($totalDocTimeSecSum, 3) : null;

        $rows[] = [
            'participant_id' => $participantId,
            'participant_code' => (string) ($participantRow['participant_code'] ?? ''),
            'condition_name' => (string) ($participantRow['condition_name'] ?? ''),
            'prolific' => (string) ($participantRow['prolific'] ?? 'no'),
            'finished_survey' => analysis_participant_finished_survey(
                (int) ($participantRow['tasks_completed'] ?? 0),
                $participantRow['serious_effort'] ?? null,
                $participantRow['completed_at'] ?? null
            ),
            'task1_reliance_choice' => is_array($task1) ? (string) ($task1['reliance_choice'] ?? '') : '',
            'task1_decision_correct' => is_array($task1) ? ($task1['final_decision_correct'] ?? null) : null,
            'task1_confidence' => is_array($task1) ? ($task1['confidence'] ?? null) : null,
            'task1_relevant_doc_opened' => is_array($task1) ? ($task1['relevant_document_opened'] ?? null) : null,
            'task1_number_docs_opened' => is_array($task1) ? ($task1['number_documents_opened'] ?? null) : null,
            'task1_docs_opened_any' => is_array($task1)
                ? ((((int) ($task1['number_documents_opened'] ?? 0)) > 0) ? 1 : 0)
                : null,
            'task1_total_doc_view_time_sec' => is_array($task1)
                ? (($task1['total_document_view_time_sec'] ?? null) !== null ? (float) $task1['total_document_view_time_sec'] : null)
                : null,
            'task2_reliance_choice' => is_array($task2) ? (string) ($task2['reliance_choice'] ?? '') : '',
            'task2_decision_correct' => is_array($task2) ? ($task2['final_decision_correct'] ?? null) : null,
            'task2_confidence' => is_array($task2) ? ($task2['confidence'] ?? null) : null,
            'task2_relevant_doc_opened' => is_array($task2) ? ($task2['relevant_document_opened'] ?? null) : null,
            'task2_number_docs_opened' => is_array($task2) ? ($task2['number_documents_opened'] ?? null) : null,
            'task2_docs_opened_any' => is_array($task2)
                ? ((((int) ($task2['number_documents_opened'] ?? 0)) > 0) ? 1 : 0)
                : null,
            'task2_total_doc_view_time_sec' => is_array($task2)
                ? (($task2['total_document_view_time_sec'] ?? null) !== null ? (float) $task2['total_document_view_time_sec'] : null)
                : null,
            'calibration_score' => $calibrationScore,
            'avg_confidence' => $avgConfidence,
            'avg_docs_opened' => $avgDocsOpened,
            'total_doc_time_sec' => $totalDocTimeSec,
            'ai_literacy' => $aiLiteracyPoints === null ? null : number_format($aiLiteracyPoints, 2),
            'crt_score' => $crtCorrectCount === null ? null : number_format($crtCorrectCount, 2),
            'task_clarity' => $participantRow['instructions_clarity'] ?? null,
            'notice_cue' => $participantRow['instruction_notice'] ?? null,
            'task_realism' => $participantRow['task_realism'] ?? null,
            'ai_experience' => $participantRow['ai_experience'] ?? null,
            'age' => $participantRow['age'] ?? null,
            'gender' => $participantRow['gender'] ?? null,
            'education' => $participantRow['education'] ?? null,
            'task1_duration_seconds' => $task1Duration,
            'task2_duration_seconds' => $task2Duration,
            'postsurvey_duration_seconds' => $postsurveyDuration,
            'total_survey_duration_seconds' => $totalSurveyDuration,
        ];
    }

    return $rows;
}

function extract_reflection_value(string $reflection, string $key): ?string
{
    $needle = $key . '=';
    $pos = strpos($reflection, $needle);
    if ($pos === false) {
        return null;
    }
    $valueStart = $pos + strlen($needle);
    $remaining = substr($reflection, $valueStart);
    if ($remaining === false) {
        return null;
    }
    $line = strtok($remaining, "\r\n");
    if ($line === false) {
        return null;
    }
    $trimmed = trim($line);
    return $trimmed === '' ? null : $trimmed;
}

function condition_sort_weight(string $condition): int
{
    $order = [
        'control' => 0,
        'passive' => 1,
        'active' => 2,
    ];
    return $order[$condition] ?? 99;
}

/**
 * Sort condition labels using the study sequence control -> passive -> active.
 * Unknown labels are placed after known conditions in alphabetical order.
 */
function sort_condition_names(array $conditionNames): array
{
    usort($conditionNames, static function (string $a, string $b): int {
        $aWeight = condition_sort_weight($a);
        $bWeight = condition_sort_weight($b);
        if ($aWeight !== $bWeight) {
            return $aWeight <=> $bWeight;
        }
        return strcmp($a, $b);
    });
    return $conditionNames;
}

/**
 * Sort associative arrays keyed by condition using study sequence.
 */
function sort_condition_keyed_array(array $rowsByCondition): array
{
    uksort($rowsByCondition, static function (string $a, string $b): int {
        $aWeight = condition_sort_weight($a);
        $bWeight = condition_sort_weight($b);
        if ($aWeight !== $bWeight) {
            return $aWeight <=> $bWeight;
        }
        return strcmp($a, $b);
    });
    return $rowsByCondition;
}

function ensure_dashboard_trash_table(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS dashboard_trash (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entity_type VARCHAR(50) NOT NULL,
            source_table VARCHAR(64) NOT NULL,
            source_id INT UNSIGNED NULL,
            payload_json LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            deleted_at DATETIME NOT NULL,
            INDEX idx_dashboard_trash_deleted_at (deleted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function ensure_manual_response_correctness_column(PDO $pdo): void
{
    if (analysis_column_exists($pdo, 'task_responses', 'manual_response_correctness')) {
        return;
    }
    // Add at table end to support schemas that do not contain response_correctness yet.
    $pdo->exec('ALTER TABLE task_responses ADD COLUMN manual_response_correctness TINYINT(1) NULL');
}

function insert_row_with_values(PDO $pdo, string $table, array $row): void
{
    if ($row === []) {
        return;
    }
    $columns = array_keys($row);
    $quotedColumns = array_map(
        static fn (string $column): string => '`' . str_replace('`', '``', $column) . '`',
        $columns
    );
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $quotedColumns) . ') VALUES (' . $placeholders . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($row));
}

function normalize_int_id_list(mixed $value): array
{
    if (!is_array($value)) {
        return [];
    }
    $ids = [];
    foreach ($value as $rawId) {
        $id = filter_var($rawId, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($id !== false && $id !== null) {
            $ids[] = (int) $id;
        }
    }
    return array_values(array_unique($ids));
}

function move_row_to_trash(
    PDO $pdo,
    string $deleteTable,
    int $deleteRowId,
    array $allowedDataTables,
    string $deletedAt
): int {
    if (!in_array($deleteTable, $allowedDataTables, true)) {
        throw new RuntimeException('Invalid delete table.');
    }

    if ($deleteTable === 'participants') {
        $participantStmt = $pdo->prepare('SELECT * FROM participants WHERE id = :id LIMIT 1');
        $participantStmt->execute([':id' => $deleteRowId]);
        $participantRow = $participantStmt->fetch();
        if (!$participantRow) {
            throw new RuntimeException('Participant not found.');
        }
        $taskRowsStmt = $pdo->prepare('SELECT * FROM task_responses WHERE participant_id = :participant_id ORDER BY id ASC');
        $taskRowsStmt->execute([':participant_id' => $deleteRowId]);
        $taskRows = $taskRowsStmt->fetchAll();

        $eventRowsStmt = $pdo->prepare('SELECT * FROM document_events WHERE participant_id = :participant_id ORDER BY id ASC');
        $eventRowsStmt->execute([':participant_id' => $deleteRowId]);
        $eventRows = $eventRowsStmt->fetchAll();

        $postsurveyRowsStmt = $pdo->prepare('SELECT * FROM postsurvey_responses WHERE participant_id = :participant_id ORDER BY id ASC');
        $postsurveyRowsStmt->execute([':participant_id' => $deleteRowId]);
        $postsurveyRows = $postsurveyRowsStmt->fetchAll();

        $raffleRowsStmt = $pdo->prepare('SELECT * FROM raffle_entries WHERE participant_id = :participant_id ORDER BY id ASC');
        $raffleRowsStmt->execute([':participant_id' => $deleteRowId]);
        $raffleRows = $raffleRowsStmt->fetchAll();

        $trashPayload = json_encode([
            'participant' => $participantRow,
            'task_responses' => $taskRows,
            'document_events' => $eventRows,
            'postsurvey_responses' => $postsurveyRows,
            'raffle_entries' => $raffleRows,
        ]);
        if ($trashPayload === false) {
            throw new RuntimeException('Failed to prepare participant trash payload.');
        }

        $insertTrashStmt = $pdo->prepare(
            'INSERT INTO dashboard_trash (entity_type, source_table, source_id, payload_json, created_at, deleted_at)
                VALUES (:entity_type, :source_table, :source_id, :payload_json, :created_at, :deleted_at)'
        );
        $insertTrashStmt->execute([
            ':entity_type' => 'participant_bundle',
            ':source_table' => 'participants',
            ':source_id' => $deleteRowId,
            ':payload_json' => $trashPayload,
            ':created_at' => $deletedAt,
            ':deleted_at' => $deletedAt,
        ]);

        $deleteDocumentEventsStmt = $pdo->prepare('DELETE FROM document_events WHERE participant_id = :participant_id');
        $deleteTaskResponsesStmt = $pdo->prepare('DELETE FROM task_responses WHERE participant_id = :participant_id');
        $deletePostsurveyStmt = $pdo->prepare('DELETE FROM postsurvey_responses WHERE participant_id = :participant_id');
        $deleteRaffleEntriesStmt = $pdo->prepare('DELETE FROM raffle_entries WHERE participant_id = :participant_id');
        $deleteParticipantStmt = $pdo->prepare('DELETE FROM participants WHERE id = :id');

        $deleteDocumentEventsStmt->execute([':participant_id' => $deleteRowId]);
        $deleteTaskResponsesStmt->execute([':participant_id' => $deleteRowId]);
        $deletePostsurveyStmt->execute([':participant_id' => $deleteRowId]);
        $deleteRaffleEntriesStmt->execute([':participant_id' => $deleteRowId]);
        $deleteParticipantStmt->execute([':id' => $deleteRowId]);
        return (int) $deleteParticipantStmt->rowCount();
    }

    $rowStmt = $pdo->prepare('SELECT * FROM ' . $deleteTable . ' WHERE id = :id LIMIT 1');
    $rowStmt->execute([':id' => $deleteRowId]);
    $row = $rowStmt->fetch();
    if (!$row) {
        throw new RuntimeException('Row not found.');
    }

    $trashPayload = json_encode([
        'row' => $row,
    ]);
    if ($trashPayload === false) {
        throw new RuntimeException('Failed to prepare row trash payload.');
    }

    $insertTrashStmt = $pdo->prepare(
        'INSERT INTO dashboard_trash (entity_type, source_table, source_id, payload_json, created_at, deleted_at)
            VALUES (:entity_type, :source_table, :source_id, :payload_json, :created_at, :deleted_at)'
    );
    $insertTrashStmt->execute([
        ':entity_type' => 'single_row',
        ':source_table' => $deleteTable,
        ':source_id' => $deleteRowId,
        ':payload_json' => $trashPayload,
        ':created_at' => $deletedAt,
        ':deleted_at' => $deletedAt,
    ]);

    $deleteStmt = $pdo->prepare('DELETE FROM ' . $deleteTable . ' WHERE id = :id');
    $deleteStmt->execute([':id' => $deleteRowId]);
    return (int) $deleteStmt->rowCount();
}

function restore_trash_item(PDO $pdo, int $trashId, array $allowedDataTables): void
{
    $trashStmt = $pdo->prepare('SELECT * FROM dashboard_trash WHERE id = :id LIMIT 1');
    $trashStmt->execute([':id' => $trashId]);
    $trashRow = $trashStmt->fetch();
    if (!$trashRow) {
        throw new RuntimeException('Trash item not found.');
    }

    $payload = json_decode((string) $trashRow['payload_json'], true);
    if (!is_array($payload)) {
        throw new RuntimeException('Trash payload is invalid.');
    }

    $entityType = (string) $trashRow['entity_type'];
    $sourceTable = (string) $trashRow['source_table'];
    if ($entityType === 'single_row') {
        if (!in_array($sourceTable, $allowedDataTables, true)) {
            throw new RuntimeException('Unsupported source table for restore.');
        }
        $row = $payload['row'] ?? null;
        if (!is_array($row) || !isset($row['id'])) {
            throw new RuntimeException('Row payload missing required fields.');
        }
        insert_row_with_values($pdo, $sourceTable, $row);
    } elseif ($entityType === 'participant_bundle') {
        $participantRow = $payload['participant'] ?? null;
        if (!is_array($participantRow) || !isset($participantRow['id'])) {
            throw new RuntimeException('Participant payload missing required fields.');
        }
        insert_row_with_values($pdo, 'participants', $participantRow);

        $taskRows = $payload['task_responses'] ?? [];
        if (is_array($taskRows)) {
            foreach ($taskRows as $taskRow) {
                if (is_array($taskRow) && isset($taskRow['id'])) {
                    insert_row_with_values($pdo, 'task_responses', $taskRow);
                }
            }
        }

        $eventRows = $payload['document_events'] ?? [];
        if (is_array($eventRows)) {
            foreach ($eventRows as $eventRow) {
                if (is_array($eventRow) && isset($eventRow['id'])) {
                    insert_row_with_values($pdo, 'document_events', $eventRow);
                }
            }
        }

        $postsurveyRows = $payload['postsurvey_responses'] ?? [];
        if (is_array($postsurveyRows)) {
            foreach ($postsurveyRows as $postsurveyRow) {
                if (is_array($postsurveyRow) && isset($postsurveyRow['id'])) {
                    insert_row_with_values($pdo, 'postsurvey_responses', $postsurveyRow);
                }
            }
        }

        $raffleRows = $payload['raffle_entries'] ?? [];
        if (is_array($raffleRows)) {
            foreach ($raffleRows as $raffleRow) {
                if (is_array($raffleRow) && isset($raffleRow['id'])) {
                    insert_row_with_values($pdo, 'raffle_entries', $raffleRow);
                }
            }
        }
    } else {
        throw new RuntimeException('Unsupported trash entity type.');
    }

    $deleteTrashStmt = $pdo->prepare('DELETE FROM dashboard_trash WHERE id = :id');
    $deleteTrashStmt->execute([':id' => $trashId]);
}

$pdo = db();
$trashRows = [];
ensure_dashboard_trash_table($pdo);
$currentTab = (string) ($_GET['tab'] ?? 'overview');
if (!in_array($currentTab, [
    'overview',
    'condition_results',
    'calibration',
    'inspection',
    'participants_analysis',
    'task_level_analysis',
    'task_level_analysis_all',
    'data_for_analysis',
    'data_for_analysis_all',
    'data',
    'participant',
    'trash',
], true)) {
    $currentTab = 'overview';
}

$allowedDataTables = [
    'participants',
    'task_responses',
    'document_events',
    'postsurvey_responses',
    'raffle_entries',
];
$includeTestParticipants = ((string) ($_GET['include_test'] ?? '0')) === '1';
$includeTestQuery = $includeTestParticipants ? '&include_test=1' : '';
$selectedTable = (string) ($_GET['table'] ?? 'participants');
if (!in_array($selectedTable, $allowedDataTables, true)) {
    $selectedTable = 'participants';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dashboard_action'])) {
    $dashboardAction = (string) ($_POST['dashboard_action'] ?? '');
    if ($dashboardAction === 'code_other_response') {
        $submittedCsrfToken = (string) ($_POST['csrf_token'] ?? '');
        if (!hash_equals($dashboardCsrfToken, $submittedCsrfToken)) {
            $_SESSION['dashboard_flash_error'] = 'Invalid security token. Please refresh and try again.';
            redirect('/dashboard/?tab=overview');
        }

        $participantId = filter_var($_POST['participant_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $taskNumber = filter_var($_POST['task_number'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $manualResponseCorrectness = filter_var($_POST['manual_response_correctness'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => 1],
        ]);
        $returnUrl = (string) ($_POST['return_url'] ?? '/dashboard/?tab=overview');
        if (!str_starts_with($returnUrl, '/dashboard')) {
            $returnUrl = '/dashboard/?tab=overview';
        }

        if (
            $participantId === false
            || $participantId === null
            || $taskNumber === false
            || $taskNumber === null
            || $manualResponseCorrectness === false
            || $manualResponseCorrectness === null
        ) {
            $_SESSION['dashboard_flash_error'] = 'Invalid coding request.';
            redirect($returnUrl);
        }

        try {
            ensure_manual_response_correctness_column($pdo);
            $hasSelectedOptionKeyColumn = analysis_column_exists($pdo, 'task_responses', 'selected_option_key');
            $selectedOptionKeySelect = $hasSelectedOptionKeyColumn ? 'selected_option_key' : 'NULL AS selected_option_key';
            $rowStmt = $pdo->prepare(
                'SELECT ' . $selectedOptionKeySelect . ', active_reflection
                 FROM task_responses
                 WHERE participant_id = :participant_id AND task_number = :task_number
                 LIMIT 1'
            );
            $rowStmt->execute([
                ':participant_id' => (int) $participantId,
                ':task_number' => (int) $taskNumber,
            ]);
            $row = $rowStmt->fetch();
            if (!$row) {
                throw new RuntimeException('Task response not found.');
            }

            $selectedOptionKey = null;
            if ($hasSelectedOptionKeyColumn && isset($row['selected_option_key']) && $row['selected_option_key'] !== null) {
                $selectedOptionKey = trim((string) $row['selected_option_key']);
            }
            if ($selectedOptionKey === null || $selectedOptionKey === '') {
                $selectedOptionKey = extract_reflection_value((string) ($row['active_reflection'] ?? ''), 'selected_option_key');
            }
            if ($selectedOptionKey !== 'other') {
                throw new RuntimeException('Manual coding is only available for responses selected as "other".');
            }

            $updateStmt = $pdo->prepare(
                'UPDATE task_responses
                 SET manual_response_correctness = :manual_response_correctness
                 WHERE participant_id = :participant_id AND task_number = :task_number'
            );
            $updateStmt->execute([
                ':manual_response_correctness' => (int) $manualResponseCorrectness,
                ':participant_id' => (int) $participantId,
                ':task_number' => (int) $taskNumber,
            ]);

            $_SESSION['dashboard_flash_success'] = 'Saved manual coding for participant ' . (int) $participantId
                . ', task ' . (int) $taskNumber . '.';
        } catch (Throwable $e) {
            $_SESSION['dashboard_flash_error'] = 'Manual coding failed: ' . $e->getMessage();
        }
        redirect($returnUrl);
    }

    if ($dashboardAction === 'delete_row' || $dashboardAction === 'bulk_move_to_trash') {
        $submittedCsrfToken = (string) ($_POST['csrf_token'] ?? '');
        if (!hash_equals($dashboardCsrfToken, $submittedCsrfToken)) {
            $_SESSION['dashboard_flash_error'] = 'Invalid security token. Please refresh and try again.';
            redirect('/dashboard/?tab=data&table=' . urlencode($selectedTable));
        }

        $deleteTable = (string) ($_POST['table'] ?? '');
        $deleteIds = [];
        if ($dashboardAction === 'delete_row') {
            $singleId = filter_var($_POST['row_id'] ?? null, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);
            if ($singleId !== false && $singleId !== null) {
                $deleteIds[] = (int) $singleId;
            }
        } else {
            $deleteIds = normalize_int_id_list($_POST['selected_row_ids'] ?? []);
        }
        $returnUrl = (string) ($_POST['return_url'] ?? '/dashboard/?tab=data');
        if (!str_starts_with($returnUrl, '/dashboard')) {
            $returnUrl = '/dashboard/?tab=data';
        }

        if (!in_array($deleteTable, $allowedDataTables, true) || empty($deleteIds)) {
            $_SESSION['dashboard_flash_error'] = 'Invalid delete request.';
            redirect($returnUrl);
        }

        try {
            $deletedRows = 0;
            $pdo->beginTransaction();
            $deletedAt = date('Y-m-d H:i:s');
            foreach ($deleteIds as $deleteRowId) {
                $deletedRows += move_row_to_trash($pdo, $deleteTable, $deleteRowId, $allowedDataTables, $deletedAt);
            }
            $pdo->commit();

            if ($deletedRows > 0) {
                $_SESSION['dashboard_flash_success'] = 'Moved ' . $deletedRows . ' row(s) from ' . $deleteTable . ' to trash.';
            } else {
                $_SESSION['dashboard_flash_error'] = 'No row was deleted. It may already be removed.';
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['dashboard_flash_error'] = 'Delete failed: ' . $e->getMessage();
        }

        redirect($returnUrl);
    }

    if (
        $dashboardAction === 'restore_trash'
        || $dashboardAction === 'purge_trash'
        || $dashboardAction === 'bulk_restore_trash'
        || $dashboardAction === 'bulk_purge_trash'
    ) {
        $submittedCsrfToken = (string) ($_POST['csrf_token'] ?? '');
        if (!hash_equals($dashboardCsrfToken, $submittedCsrfToken)) {
            $_SESSION['dashboard_flash_error'] = 'Invalid security token. Please refresh and try again.';
            redirect('/dashboard/?tab=trash');
        }

        $trashIds = [];
        if ($dashboardAction === 'restore_trash' || $dashboardAction === 'purge_trash') {
            $singleTrashId = filter_var($_POST['trash_id'] ?? null, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);
            if ($singleTrashId !== false && $singleTrashId !== null) {
                $trashIds[] = (int) $singleTrashId;
            }
        } else {
            $trashIds = normalize_int_id_list($_POST['selected_trash_ids'] ?? []);
        }
        if (empty($trashIds)) {
            $_SESSION['dashboard_flash_error'] = 'Invalid trash item.';
            redirect('/dashboard/?tab=trash');
        }

        if ($dashboardAction === 'purge_trash' || $dashboardAction === 'bulk_purge_trash') {
            $purged = 0;
            $purgeStmt = $pdo->prepare('DELETE FROM dashboard_trash WHERE id = :id');
            foreach ($trashIds as $trashId) {
                $purgeStmt->execute([':id' => $trashId]);
                $purged += (int) $purgeStmt->rowCount();
            }
            $_SESSION['dashboard_flash_success'] = $purged > 0
                ? 'Permanently deleted ' . $purged . ' trash item(s).'
                : 'Trash item(s) not found.';
            redirect('/dashboard/?tab=trash');
        }

        try {
            $pdo->beginTransaction();
            $restored = 0;
            foreach ($trashIds as $trashId) {
                restore_trash_item($pdo, $trashId, $allowedDataTables);
                $restored++;
            }
            $pdo->commit();
            $_SESSION['dashboard_flash_success'] = 'Restored ' . $restored . ' trash item(s) successfully.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['dashboard_flash_error'] = 'Restore failed: ' . $e->getMessage();
        }
        redirect('/dashboard/?tab=trash');
    }
}

$dataPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
if ($dataPage === false || $dataPage === null) {
    $dataPage = 1;
}

$rowsPerPage = 100;
$dataTotalRows = 0;
$dataTotalPages = 1;
$dataOffset = 0;
$dataColumns = [];
$dataRows = [];
$sortColumn = 'id';
$sortDirection = 'desc';
$fullRawCombinedColumns = [];
$fullRawCombinedRows = [];

if ($currentTab === 'data') {
    $columnsStmt = $pdo->query('SHOW COLUMNS FROM ' . $selectedTable);
    foreach ($columnsStmt->fetchAll() as $columnRow) {
        $dataColumns[] = (string) $columnRow['Field'];
    }
    $hasNativeProlificColumn = in_array('prolific', $dataColumns, true);
    $needsComputedProlificColumn = $selectedTable === 'participants' && !$hasNativeProlificColumn;
    if ($needsComputedProlificColumn) {
        $dataColumns[] = 'prolific';
    }

    if (!in_array('id', $dataColumns, true) && !empty($dataColumns)) {
        $sortColumn = $dataColumns[0];
    }
    $requestedSortColumn = (string) ($_GET['sort'] ?? $sortColumn);
    if (in_array($requestedSortColumn, $dataColumns, true)) {
        $sortColumn = $requestedSortColumn;
    }
    $requestedDirection = strtolower((string) ($_GET['dir'] ?? 'desc'));
    $sortDirection = $requestedDirection === 'asc' ? 'asc' : 'desc';

    $countStmt = $pdo->query('SELECT COUNT(*) AS total FROM ' . $selectedTable);
    $countRow = $countStmt->fetch();
    $dataTotalRows = (int) ($countRow['total'] ?? 0);
    $dataTotalPages = max(1, (int) ceil($dataTotalRows / $rowsPerPage));
    $dataPage = min($dataPage, $dataTotalPages);
    $dataOffset = ($dataPage - 1) * $rowsPerPage;

    $rowsSql = 'SELECT * FROM ' . $selectedTable;
    if ($needsComputedProlificColumn) {
        $rowsSql = 'SELECT p.*, CASE
                WHEN (p.id BETWEEN 103 AND 139) OR (p.id BETWEEN 164 AND 182) THEN \'yes\'
                WHEN p.study_participation_code IS NOT NULL AND CHAR_LENGTH(TRIM(p.study_participation_code)) >= 20 THEN \'yes\'
                ELSE \'no\'
            END AS prolific
            FROM participants p';
    }
    if ($needsComputedProlificColumn && $sortColumn === 'prolific') {
        $rowsSql .= ' ORDER BY prolific ' . strtoupper($sortDirection);
    } else {
        $rowsSql .= ' ORDER BY `' . $sortColumn . '` ' . strtoupper($sortDirection);
    }
    $rowsSql .= ' LIMIT :limit OFFSET :offset';
    $rowsStmt = $pdo->prepare($rowsSql);
    $rowsStmt->bindValue(':limit', $rowsPerPage, PDO::PARAM_INT);
    $rowsStmt->bindValue(':offset', $dataOffset, PDO::PARAM_INT);
    $rowsStmt->execute();
    $dataRows = $rowsStmt->fetchAll();
}

if ($currentTab === 'full_raw_data') {
    $participantColumns = [];
    $participantColumnsStmt = $pdo->query('SHOW COLUMNS FROM participants');
    foreach ($participantColumnsStmt->fetchAll() as $columnRow) {
        $participantColumns[(string) $columnRow['Field']] = true;
    }

    $taskResponseColumns = [];
    $taskResponseColumnsStmt = $pdo->query('SHOW COLUMNS FROM task_responses');
    foreach ($taskResponseColumnsStmt->fetchAll() as $columnRow) {
        $taskResponseColumns[(string) $columnRow['Field']] = true;
    }

    $postsurveyColumns = [];
    $postsurveyColumnsStmt = $pdo->query('SHOW COLUMNS FROM postsurvey_responses');
    foreach ($postsurveyColumnsStmt->fetchAll() as $columnRow) {
        $postsurveyColumns[(string) $columnRow['Field']] = true;
    }

    $raffleColumns = [];
    try {
        $raffleColumnsStmt = $pdo->query('SHOW COLUMNS FROM raffle_entries');
        foreach ($raffleColumnsStmt->fetchAll() as $columnRow) {
            $raffleColumns[(string) $columnRow['Field']] = true;
        }
    } catch (Throwable $e) {
        $raffleColumns = [];
    }

    $taskFields = [
        'selected_response_option',
        'selected_option_key',
        'selected_display_letter',
        'final_response',
        'custom_response_text',
        'response_correctness',
        'manual_response_correctness',
        'manual_code_required',
        'confidence',
        'reliance_choice',
        'verification_intention',
        'task_started_at',
        'task_submitted_at',
        'duration_seconds',
        'short_time_flag',
        'relevant_document_opened',
        'number_documents_opened',
        'total_document_view_time_ms',
        'relevant_document_view_time_ms',
        'active_reflection',
    ];

    $postsurveyFields = [
        'ai_lit_1',
        'ai_lit_2',
        'ai_lit_3',
        'ai_lit_4',
        'serious_effort',
        'instructions_clarity',
        'instruction_notice',
        'task_realism',
        'crt_1',
        'crt_2',
        'crt_3',
        'ai_experience',
        'age',
        'gender',
        'education',
        'duration_seconds',
        'short_time_flag',
        'submitted_at',
    ];

    $fullRawProlificSql = isset($participantColumns['prolific'])
        ? 'p.prolific'
        : "CASE
            WHEN (p.id BETWEEN 103 AND 139) OR (p.id BETWEEN 164 AND 182) THEN 'yes'
            WHEN p.study_participation_code IS NOT NULL AND CHAR_LENGTH(TRIM(p.study_participation_code)) >= 20 THEN 'yes'
            ELSE 'no'
          END";
    $fullRawSelectParts = [
        'p.id AS participant_id',
        'p.participant_code',
        'p.condition_name',
        'p.started_at',
        'p.completed_at',
        (isset($participantColumns['study_participation_code']) ? 'p.study_participation_code' : 'NULL') . ' AS study_participation_code',
        $fullRawProlificSql . ' AS prolific',
    ];

    foreach ($taskFields as $field) {
        $fullRawSelectParts[] = (isset($taskResponseColumns[$field]) ? 'tr1.' . $field : 'NULL')
            . ' AS task1_' . $field;
        $fullRawSelectParts[] = (isset($taskResponseColumns[$field]) ? 'tr2.' . $field : 'NULL')
            . ' AS task2_' . $field;
    }

    foreach ($postsurveyFields as $field) {
        $fullRawSelectParts[] = (isset($postsurveyColumns[$field]) ? 'ps.' . $field : 'NULL')
            . ' AS postsurvey_' . $field;
    }

    $fullRawSelectParts[] = (isset($raffleColumns['email']) ? 're.email' : 'NULL') . ' AS raffle_email';
    $fullRawSelectParts[] = (isset($raffleColumns['created_at']) ? 're.created_at' : 'NULL') . ' AS raffle_created_at';

    $fullRawSql = 'SELECT
        ' . implode(",
        ", $fullRawSelectParts) . '
    FROM participants p
    LEFT JOIN (
        SELECT tr.*
        FROM task_responses tr
        INNER JOIN (
            SELECT participant_id, MAX(id) AS max_id
            FROM task_responses
            WHERE task_number = 1
            GROUP BY participant_id
        ) latest_tr1 ON latest_tr1.max_id = tr.id
    ) tr1 ON tr1.participant_id = p.id
    LEFT JOIN (
        SELECT tr.*
        FROM task_responses tr
        INNER JOIN (
            SELECT participant_id, MAX(id) AS max_id
            FROM task_responses
            WHERE task_number = 2
            GROUP BY participant_id
        ) latest_tr2 ON latest_tr2.max_id = tr.id
    ) tr2 ON tr2.participant_id = p.id
    LEFT JOIN (
        SELECT ps1.*
        FROM postsurvey_responses ps1
        INNER JOIN (
            SELECT participant_id, MAX(id) AS max_id
            FROM postsurvey_responses
            GROUP BY participant_id
        ) latest_ps ON latest_ps.max_id = ps1.id
    ) ps ON ps.participant_id = p.id
    LEFT JOIN (
        SELECT re1.*
        FROM raffle_entries re1
        INNER JOIN (
            SELECT participant_id, MAX(id) AS max_id
            FROM raffle_entries
            GROUP BY participant_id
        ) latest_re ON latest_re.max_id = re1.id
    ) re ON re.participant_id = p.id
    ' . ($includeTestParticipants ? '' : 'WHERE p.participant_code NOT LIKE ' . $pdo->quote(TEST_PARTICIPANT_PREFIX . '%')) . '
    ORDER BY p.id DESC';

    $fullRawCombinedRows = $pdo->query($fullRawSql)->fetchAll();
    $fullRawCombinedColumns = !empty($fullRawCombinedRows)
        ? array_values(array_keys($fullRawCombinedRows[0]))
        : [
            'participant_id',
            'participant_code',
            'condition_name',
            'started_at',
            'completed_at',
            'study_participation_code',
            'prolific',
            'task1_selected_option_key',
            'task2_selected_option_key',
            'postsurvey_ai_experience',
            'postsurvey_submitted_at',
        ];
}

if ($currentTab === 'trash') {
    $trashStmt = $pdo->query(
        'SELECT id, entity_type, source_table, source_id, deleted_at
         FROM dashboard_trash
         ORDER BY id DESC
         LIMIT 500'
    );
    $trashRows = $trashStmt->fetchAll();
}

/**
 * Participants + completion snapshot.
 */
$participantSummaryStmt = $pdo->query(
    'SELECT
        COUNT(*) AS total_respondents,
        SUM(CASE WHEN completed_at IS NOT NULL THEN 1 ELSE 0 END) AS completed_respondents
     FROM participants'
    . ($includeTestParticipants ? '' : ' WHERE participant_code NOT LIKE ' . $pdo->quote(TEST_PARTICIPANT_PREFIX . '%'))
);
$participantSummary = $participantSummaryStmt->fetch() ?: [
    'total_respondents' => 0,
    'completed_respondents' => 0,
];

$raffleEntriesCount = 0;
try {
    $raffleEntriesCountStmt = $pdo->query('SELECT COUNT(*) AS total_entries FROM raffle_entries');
    $raffleEntriesCountRow = $raffleEntriesCountStmt->fetch();
    $raffleEntriesCount = (int) ($raffleEntriesCountRow['total_entries'] ?? 0);
} catch (Throwable $e) {
    // Keep overview available if raffle table does not yet exist.
    $raffleEntriesCount = 0;
}

/**
 * Per-condition participant counts and completion.
 */
$conditionCountsStmt = $pdo->query(
    'SELECT
        condition_name,
        COUNT(*) AS respondents,
        SUM(CASE WHEN completed_at IS NOT NULL THEN 1 ELSE 0 END) AS completed
     FROM participants'
    . ($includeTestParticipants ? '' : ' WHERE participant_code NOT LIKE ' . $pdo->quote(TEST_PARTICIPANT_PREFIX . '%'))
    . '
     GROUP BY condition_name
     ORDER BY condition_name'
);
$conditionRows = $conditionCountsStmt->fetchAll();

$conditionNames = [];
$respondentsByCondition = [];
$completedByCondition = [];
$completionByCondition = [];
foreach ($conditionRows as $row) {
    $condition = (string) $row['condition_name'];
    $respondents = (int) $row['respondents'];
    $completed = (int) $row['completed'];
    $conditionNames[] = $condition;
    $respondentsByCondition[$condition] = $respondents;
    $completedByCondition[$condition] = $completed;
    $completionByCondition[$condition] = $respondents > 0
        ? ($completed / $respondents) * 100.0
        : 0.0;
}
$conditionNames = sort_condition_names($conditionNames);
$respondentsByCondition = sort_condition_keyed_array($respondentsByCondition);
$completedByCondition = sort_condition_keyed_array($completedByCondition);
$completionByCondition = sort_condition_keyed_array($completionByCondition);

/**
 * Average unique documents opened per participant by condition.
 */
$avgDocsOpenedByCondition = [];
try {
    $avgDocsStmt = $pdo->query(
        'SELECT
            p.condition_name,
            AVG(COALESCE(doc_counts.docs_opened, 0)) AS avg_docs_opened
         FROM participants p
         LEFT JOIN (
            SELECT
                participant_id,
                COUNT(DISTINCT task_number, document_key) AS docs_opened
            FROM document_events
            WHERE event_type = \'open\'
            GROUP BY participant_id
         ) AS doc_counts
           ON doc_counts.participant_id = p.id
         ' . ($includeTestParticipants ? '' : 'WHERE p.participant_code NOT LIKE ' . $pdo->quote(TEST_PARTICIPANT_PREFIX . '%')) . '
         GROUP BY p.condition_name'
    );
    foreach ($avgDocsStmt->fetchAll() as $row) {
        $avgDocsOpenedByCondition[(string) $row['condition_name']] = (float) $row['avg_docs_opened'];
    }
} catch (Throwable $e) {
    // Keep dashboard loadable even if SQL mode differs in production.
    $avgDocsOpenedByCondition = [];
}

/**
 * Average inspection time (seconds) from document close events by condition.
 */
$avgInspectStmt = $pdo->query(
    'SELECT
        p.condition_name,
        AVG(de.view_ms) / 1000.0 AS avg_inspection_seconds
     FROM participants p
     JOIN document_events de ON de.participant_id = p.id
     WHERE de.event_type = \'close\'
       AND de.view_ms IS NOT NULL
       ' . ($includeTestParticipants ? '' : 'AND p.participant_code NOT LIKE ' . $pdo->quote(TEST_PARTICIPANT_PREFIX . '%')) . '
     GROUP BY p.condition_name'
);
$avgInspectionSecondsByCondition = [];
foreach ($avgInspectStmt->fetchAll() as $row) {
    $avgInspectionSecondsByCondition[(string) $row['condition_name']] = (float) $row['avg_inspection_seconds'];
}

/**
 * Load task configuration to determine "relevant document" per task.
 */
$tasks = require __DIR__ . '/../data/tasks.php';
$relevantDocumentByTask = [];
foreach ($tasks as $taskNumber => $taskConfig) {
    $documents = $taskConfig['documents'] ?? [];
    if (!is_array($documents)) {
        continue;
    }
    foreach ($documents as $doc) {
        if (!empty($doc['relevant']) && isset($doc['key'])) {
            $relevantDocumentByTask[(int) $taskNumber] = (string) $doc['key'];
            break;
        }
    }
}

$participantDetailId = filter_input(INPUT_GET, 'participant_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$participantDetail = null;
$participantTaskRows = [];
$participantEventRows = [];
$participantPostsurvey = null;
$participantTaskRowsDetailed = [];
$participantDocSummary = [
    'open_events' => 0,
    'close_events' => 0,
    'avg_view_seconds' => 0.0,
    'opened_relevant_pct' => 0.0,
];
$participantDerived = [
    'decision_correct_count' => 0,
    'decision_total' => 0,
    'decision_correct_pct' => 0.0,
    'avg_confidence' => 0.0,
    'correct_total_4tasks' => 0,
    'correct_rate_4tasks' => 0.0,
    'avg_docs_opened_4tasks' => 0.0,
    'avg_inspection_time_4tasks' => 0.0,
    'relevant_doc_open_rate_4tasks' => 0.0,
    'doc_clicks_total_4tasks' => 0,
    'tasks_observed' => 0,
    'ai_literacy_raw' => null,
    'ai_literacy_max' => 20, // 4 items × 5 (Likert 1–5)
    'crt_correct_count' => null,
    'crt_total' => 3,
    'crt_score_pct' => null,
];

if ($currentTab === 'participant' && $participantDetailId !== false && $participantDetailId !== null) {
    $participantStmt = $pdo->prepare('SELECT * FROM participants WHERE id = :id');
    $participantStmt->execute([':id' => $participantDetailId]);
    $participantDetail = $participantStmt->fetch() ?: null;
    if ($participantDetail !== null && !array_key_exists('prolific', $participantDetail)) {
        $participantIdForProlific = (int) ($participantDetail['id'] ?? 0);
        $hasStudyCode = mb_strlen(trim((string) ($participantDetail['study_participation_code'] ?? ''))) >= 20;
        $participantDetail['prolific'] = (
            ($participantIdForProlific >= 103 && $participantIdForProlific <= 139)
            || ($participantIdForProlific >= 164 && $participantIdForProlific <= 182)
            || $hasStudyCode
        ) ? 'yes' : 'no';
    }

    if ($participantDetail !== null) {
        $participantTaskStmt = $pdo->prepare(
            'SELECT *
             FROM task_responses
             WHERE participant_id = :id
             ORDER BY task_number ASC, id ASC'
        );
        $participantTaskStmt->execute([':id' => $participantDetailId]);
        $participantTaskRows = $participantTaskStmt->fetchAll();

        $participantEventsStmt = $pdo->prepare(
            'SELECT *
             FROM document_events
             WHERE participant_id = :id
             ORDER BY event_time ASC, id ASC'
        );
        $participantEventsStmt->execute([':id' => $participantDetailId]);
        $participantEventRows = $participantEventsStmt->fetchAll();

        $participantPostsurveyStmt = $pdo->prepare(
            'SELECT *
             FROM postsurvey_responses
             WHERE participant_id = :id
             ORDER BY id DESC
             LIMIT 1'
        );
        $participantPostsurveyStmt->execute([':id' => $participantDetailId]);
        $participantPostsurvey = $participantPostsurveyStmt->fetch() ?: null;

        $openEvents = 0;
        $closeEvents = 0;
        $viewMsSum = 0;
        $viewMsCount = 0;
        $openedRelevantTasks = 0;
        $relevantTaskOpportunities = 0;
        $participantConfidenceSum = 0.0;
        $participantConfidenceCount = 0;
        $participantDecisionCorrectCount = 0;
        $participantDecisionTotal = 0;
        $openedKeysByTask = [];
        $openCountsByTask = [];
        $viewMsByTask = [];

        foreach ($participantEventRows as $eventRow) {
            $eventType = (string) $eventRow['event_type'];
            $taskNumber = (int) $eventRow['task_number'];
            $documentKey = (string) $eventRow['document_key'];
            if ($eventType === 'open') {
                $openEvents++;
                if (!isset($openedKeysByTask[$taskNumber])) {
                    $openedKeysByTask[$taskNumber] = [];
                }
                $openedKeysByTask[$taskNumber][$documentKey] = true;
                if (!isset($openCountsByTask[$taskNumber])) {
                    $openCountsByTask[$taskNumber] = 0;
                }
                $openCountsByTask[$taskNumber]++;
            }
            if ($eventType === 'close') {
                $closeEvents++;
                if ($eventRow['view_ms'] !== null) {
                    $viewMs = (int) $eventRow['view_ms'];
                    $viewMsSum += $viewMs;
                    $viewMsCount++;
                    if (!isset($viewMsByTask[$taskNumber])) {
                        $viewMsByTask[$taskNumber] = 0;
                    }
                    $viewMsByTask[$taskNumber] += $viewMs;
                }
            }
        }

        $docsOpenedSum = 0.0;
        $inspectionSecondsSum = 0.0;
        $docClicksSum = 0;

        foreach ($participantTaskRows as $taskRow) {
            $taskNumber = (int) $taskRow['task_number'];
            $uniqueDocsOpened = count($openedKeysByTask[$taskNumber] ?? []);
            $inspectionSecondsTotal = ((float) ($viewMsByTask[$taskNumber] ?? 0)) / 1000.0;
            $docClicksTotal = (int) ($openCountsByTask[$taskNumber] ?? 0);
            $relevantDocOpenedValue = null;
            if (!isset($relevantDocumentByTask[$taskNumber])) {
                // Continue with correctness/confidence derivation even if relevant doc unknown.
            } else {
                $relevantTaskOpportunities++;
                $relevantKey = $relevantDocumentByTask[$taskNumber];
                $hasOpenedRelevant = isset($openedKeysByTask[$taskNumber][$relevantKey]);
                $relevantDocOpenedValue = $hasOpenedRelevant;
                if ($hasOpenedRelevant) {
                    $openedRelevantTasks++;
                }
            }

            $aiCorrect = (int) $taskRow['ai_correct'];
            $relianceChoice = (string) $taskRow['reliance_choice'];
            $manualResponseCorrectness = isset($taskRow['manual_response_correctness']) && $taskRow['manual_response_correctness'] !== null
                ? (int) $taskRow['manual_response_correctness']
                : null;
            if ($manualResponseCorrectness === null) {
                $manualFromReflection = extract_reflection_value((string) ($taskRow['active_reflection'] ?? ''), 'manual_response_correctness');
                $manualResponseCorrectness = $manualFromReflection !== null && $manualFromReflection !== ''
                    ? (int) $manualFromReflection
                    : null;
            }
            $responseCorrectness = isset($taskRow['response_correctness']) && $taskRow['response_correctness'] !== null
                ? (int) $taskRow['response_correctness']
                : null;
            if ($responseCorrectness === null) {
                $responseFromReflection = extract_reflection_value((string) ($taskRow['active_reflection'] ?? ''), 'response_correctness');
                $responseCorrectness = $responseFromReflection !== null && $responseFromReflection !== ''
                    ? (int) $responseFromReflection
                    : null;
            }
            $finalDecisionCorrect = $manualResponseCorrectness ?? $responseCorrectness;
            if ($finalDecisionCorrect === null) {
                $isCorrectDecision = ($aiCorrect === 1 && $relianceChoice !== 'did_not_use')
                    || ($aiCorrect === 0 && $relianceChoice === 'did_not_use');
            } else {
                $isCorrectDecision = $finalDecisionCorrect === 1;
            }

            $participantDecisionTotal++;
            if ($isCorrectDecision) {
                $participantDecisionCorrectCount++;
            }

            $participantConfidenceSum += (float) ((int) $taskRow['confidence']);
            $participantConfidenceCount++;
            $docsOpenedSum += $uniqueDocsOpened;
            $inspectionSecondsSum += $inspectionSecondsTotal;
            $docClicksSum += $docClicksTotal;

            $taskRow['_decision_correct'] = $isCorrectDecision ? 'Yes' : 'No';
            $taskRow['_docs_opened_unique'] = $uniqueDocsOpened;
            $taskRow['_inspection_time_total_seconds'] = $inspectionSecondsTotal;
            $taskRow['_doc_clicks_total'] = $docClicksTotal;
            $taskRow['_relevant_doc_opened'] = $relevantDocOpenedValue === null
                ? 'N/A'
                : ($relevantDocOpenedValue ? 'Yes' : 'No');
            $participantTaskRowsDetailed[] = $taskRow;
        }

        $participantDocSummary = [
            'open_events' => $openEvents,
            'close_events' => $closeEvents,
            'avg_view_seconds' => $viewMsCount > 0 ? ($viewMsSum / $viewMsCount) / 1000.0 : 0.0,
            'opened_relevant_pct' => $relevantTaskOpportunities > 0
                ? ($openedRelevantTasks / $relevantTaskOpportunities) * 100.0
                : 0.0,
        ];

        $participantDerived['decision_correct_count'] = $participantDecisionCorrectCount;
        $participantDerived['decision_total'] = $participantDecisionTotal;
        $participantDerived['decision_correct_pct'] = $participantDecisionTotal > 0
            ? ($participantDecisionCorrectCount / $participantDecisionTotal) * 100.0
            : 0.0;
        $participantDerived['correct_total_4tasks'] = $participantDecisionCorrectCount;
        $participantDerived['correct_rate_4tasks'] = $participantDecisionTotal > 0
            ? $participantDecisionCorrectCount / $participantDecisionTotal
            : 0.0;
        $participantDerived['avg_confidence'] = $participantConfidenceCount > 0
            ? ($participantConfidenceSum / $participantConfidenceCount)
            : 0.0;
        $participantDerived['tasks_observed'] = $participantDecisionTotal;
        $participantDerived['avg_docs_opened_4tasks'] = $participantDecisionTotal > 0
            ? ($docsOpenedSum / $participantDecisionTotal)
            : 0.0;
        $participantDerived['avg_inspection_time_4tasks'] = $participantDecisionTotal > 0
            ? ($inspectionSecondsSum / $participantDecisionTotal)
            : 0.0;
        $participantDerived['doc_clicks_total_4tasks'] = $docClicksSum;
        $participantDerived['relevant_doc_open_rate_4tasks'] = $relevantTaskOpportunities > 0
            ? ($openedRelevantTasks / $relevantTaskOpportunities)
            : 0.0;

        if ($participantPostsurvey !== null) {
            $aiFields = ['ai_lit_1', 'ai_lit_2', 'ai_lit_3', 'ai_lit_4'];
            $aiValues = [];
            foreach ($aiFields as $field) {
                $aiValues[$field] = int_in_range_or_null($participantPostsurvey[$field] ?? null, 1, 5);
            }
            $hasAllAiValues = true;
            foreach ($aiValues as $value) {
                if ($value === null) {
                    $hasAllAiValues = false;
                    break;
                }
            }
            if ($hasAllAiValues) {
                $raw = (int) array_sum($aiValues);
                $participantDerived['ai_literacy_raw'] = $raw;
            }

            $crt1 = isset($participantPostsurvey['crt_1']) ? (float) $participantPostsurvey['crt_1'] : null;
            $crt2 = isset($participantPostsurvey['crt_2']) ? (float) $participantPostsurvey['crt_2'] : null;
            $crt3 = isset($participantPostsurvey['crt_3']) ? (float) $participantPostsurvey['crt_3'] : null;

            if ($crt1 !== null && $crt2 !== null && $crt3 !== null) {
                $crtCorrectCount = 0;
                if (is_close_enough($crt1, 0.05)) {
                    $crtCorrectCount++;
                }
                if (is_close_enough($crt2, 5.0)) {
                    $crtCorrectCount++;
                }
                if (is_close_enough($crt3, 47.0)) {
                    $crtCorrectCount++;
                }
                $participantDerived['crt_correct_count'] = $crtCorrectCount;
                $participantDerived['crt_score_pct'] = ($crtCorrectCount / 3) * 100.0;
            }
        }
    }
}

/**
 * Pull task-level response data for correctness and confidence summaries.
 */
$responseCorrectnessSelect = analysis_column_exists($pdo, 'task_responses', 'response_correctness')
    ? 'tr.response_correctness'
    : 'NULL AS response_correctness';
$manualResponseCorrectnessSelect = analysis_column_exists($pdo, 'task_responses', 'manual_response_correctness')
    ? 'tr.manual_response_correctness'
    : 'NULL AS manual_response_correctness';
$taskRowsStmt = $pdo->query(
    'SELECT
        p.condition_name,
        tr.participant_id,
        tr.task_number,
        tr.ai_correct,
        tr.reliance_choice,
        ' . $responseCorrectnessSelect . ',
        ' . $manualResponseCorrectnessSelect . ',
        tr.active_reflection,
        tr.confidence
     FROM task_responses tr
     JOIN participants p ON p.id = tr.participant_id'
);
$taskRows = $taskRowsStmt->fetchAll();

/**
 * Pull open events once; used to compute whether relevant document was opened.
 */
$openEventsStmt = $pdo->query(
    'SELECT DISTINCT participant_id, task_number, document_key
     FROM document_events
     WHERE event_type = \'open\''
);
$openedDocKeys = [];
foreach ($openEventsStmt->fetchAll() as $row) {
    $key = (int) $row['participant_id'] . '|' . (int) $row['task_number'] . '|' . (string) $row['document_key'];
    $openedDocKeys[$key] = true;
}

$openedRelevantCounts = [];
$relevantOpportunities = [];
$correctnessHits = [];
$correctnessTotals = [];
$confidenceSums = [];
$confidenceCounts = [];

foreach ($conditionNames as $condition) {
    $openedRelevantCounts[$condition] = 0;
    $relevantOpportunities[$condition] = 0;
    $correctnessHits[$condition] = 0;
    $correctnessTotals[$condition] = 0;
    $confidenceSums[$condition] = 0.0;
    $confidenceCounts[$condition] = 0;
}

foreach ($taskRows as $row) {
    $condition = (string) $row['condition_name'];
    if (!in_array($condition, $conditionNames, true)) {
        continue;
    }

    $participantId = (int) $row['participant_id'];
    $taskNumber = (int) $row['task_number'];
    $aiCorrect = (int) $row['ai_correct'];
    $relianceChoice = (string) $row['reliance_choice'];
    $confidence = (int) $row['confidence'];

    if (isset($relevantDocumentByTask[$taskNumber])) {
        $relevantOpportunities[$condition]++;
        $openKey = $participantId . '|' . $taskNumber . '|' . $relevantDocumentByTask[$taskNumber];
        if (isset($openedDocKeys[$openKey])) {
            $openedRelevantCounts[$condition]++;
        }
    }

    $manualResponseCorrectness = isset($row['manual_response_correctness']) && $row['manual_response_correctness'] !== null
        ? (int) $row['manual_response_correctness']
        : null;
    if ($manualResponseCorrectness === null) {
        $manualFromReflection = extract_reflection_value((string) ($row['active_reflection'] ?? ''), 'manual_response_correctness');
        $manualResponseCorrectness = $manualFromReflection !== null && $manualFromReflection !== ''
            ? (int) $manualFromReflection
            : null;
    }
    $responseCorrectness = isset($row['response_correctness']) && $row['response_correctness'] !== null
        ? (int) $row['response_correctness']
        : null;
    if ($responseCorrectness === null) {
        $responseFromReflection = extract_reflection_value((string) ($row['active_reflection'] ?? ''), 'response_correctness');
        $responseCorrectness = $responseFromReflection !== null && $responseFromReflection !== ''
            ? (int) $responseFromReflection
            : null;
    }
    $finalDecisionCorrect = $manualResponseCorrectness ?? $responseCorrectness;
    if ($finalDecisionCorrect === null) {
        // Fallback proxy for older rows that do not yet have coding.
        $isCorrectDecision = ($aiCorrect === 1 && $relianceChoice !== 'did_not_use')
            || ($aiCorrect === 0 && $relianceChoice === 'did_not_use');
    } else {
        $isCorrectDecision = $finalDecisionCorrect === 1;
    }
    $correctnessTotals[$condition]++;
    if ($isCorrectDecision) {
        $correctnessHits[$condition]++;
    }

    $confidenceSums[$condition] += $confidence;
    $confidenceCounts[$condition]++;
}

$openedRelevantPctByCondition = [];
$decisionCorrectPctByCondition = [];
$avgConfidenceByCondition = [];

foreach ($conditionNames as $condition) {
    $openedRelevantPctByCondition[$condition] = $relevantOpportunities[$condition] > 0
        ? ($openedRelevantCounts[$condition] / $relevantOpportunities[$condition]) * 100.0
        : 0.0;
    $decisionCorrectPctByCondition[$condition] = $correctnessTotals[$condition] > 0
        ? ($correctnessHits[$condition] / $correctnessTotals[$condition]) * 100.0
        : 0.0;
    $avgConfidenceByCondition[$condition] = $confidenceCounts[$condition] > 0
        ? $confidenceSums[$condition] / $confidenceCounts[$condition]
        : 0.0;
}

$totalRespondents = (int) $participantSummary['total_respondents'];
$completedRespondents = (int) $participantSummary['completed_respondents'];
$completionRate = $totalRespondents > 0 ? ($completedRespondents / $totalRespondents) * 100.0 : 0.0;

$overallAvgConfidence = 0.0;
$overallConfidenceCount = array_sum($confidenceCounts);
if ($overallConfidenceCount > 0) {
    $overallAvgConfidence = array_sum($confidenceSums) / $overallConfidenceCount;
}

$analysisTaskLevelRows = analysis_task_level($pdo, $includeTestParticipants);
$analysisParticipantRows = analysis_participant_summary($pdo, $includeTestParticipants);

$analysisTotalRespondents = count($analysisParticipantRows);
$analysisCompletedRespondents = 0;
$analysisCompletedProlificRespondents = 0;
$analysisAvgCorrectPctSum = 0.0;
$analysisAvgCorrectPctCount = 0;
$analysisAvgConfidenceSum = 0.0;
$analysisAvgConfidenceCount = 0;
$analysisRelevantOpenRateSum = 0.0;
$analysisRelevantOpenRateCount = 0;
$analysisConditionStats = [];
$analysisConditionAiBuckets = [];
$correctDist = ['0/2' => 0, '1/2' => 0, '2/2' => 0];
$relevantDist = ['0%' => 0, '50%' => 0, '100%' => 0];
$manualCodeRequiredCount = 0;
$uncodedOtherCount = 0;
$nullFinalDecisionCount = 0;
$uncodedOtherRows = [];

foreach ($analysisParticipantRows as $participantRow) {
    $condition = (string) ($participantRow['condition_name'] ?? 'unknown');
    $tasksCompleted = (int) ($participantRow['tasks_completed'] ?? 0);
    $correctCount = (int) ($participantRow['correct_count'] ?? 0);
    $relevantRate = $participantRow['relevant_doc_open_rate'];

    $isCompletedParticipant = ($participantRow['completed_at'] ?? null) !== null
        && trim((string) $participantRow['completed_at']) !== '';
    if ($isCompletedParticipant) {
        $analysisCompletedRespondents++;
        $isProlificParticipant = strtolower(trim((string) ($participantRow['prolific'] ?? 'no'))) === 'yes';
        if ($isProlificParticipant) {
            $analysisCompletedProlificRespondents++;
        }
    }
    if ($participantRow['correct_pct'] !== null) {
        $analysisAvgCorrectPctSum += (float) $participantRow['correct_pct'];
        $analysisAvgCorrectPctCount++;
    }
    if ($participantRow['avg_confidence'] !== null) {
        $analysisAvgConfidenceSum += (float) $participantRow['avg_confidence'];
        $analysisAvgConfidenceCount++;
    }
    if ($relevantRate !== null) {
        $analysisRelevantOpenRateSum += (float) $relevantRate;
        $analysisRelevantOpenRateCount++;
    }

    if (!isset($analysisConditionStats[$condition])) {
        $analysisConditionStats[$condition] = [
            'participants' => 0,
            'completed' => 0,
            'tasks_completed_sum' => 0,
            'correct_pct_sum' => 0.0,
            'correct_pct_count' => 0,
            'avg_confidence_sum' => 0.0,
            'avg_confidence_count' => 0,
            'relevant_rate_sum' => 0.0,
            'relevant_rate_count' => 0,
            'avg_docs_opened_sum' => 0.0,
            'avg_docs_opened_count' => 0,
            'overreliance_sum' => 0,
        ];
    }
    $analysisConditionStats[$condition]['participants']++;
    if (($participantRow['completed_at'] ?? null) !== null && trim((string) $participantRow['completed_at']) !== '') {
        $analysisConditionStats[$condition]['completed']++;
    }
    $analysisConditionStats[$condition]['tasks_completed_sum'] += $tasksCompleted;
    if ($participantRow['correct_pct'] !== null) {
        $analysisConditionStats[$condition]['correct_pct_sum'] += (float) $participantRow['correct_pct'];
        $analysisConditionStats[$condition]['correct_pct_count']++;
    }
    if ($participantRow['avg_confidence'] !== null) {
        $analysisConditionStats[$condition]['avg_confidence_sum'] += (float) $participantRow['avg_confidence'];
        $analysisConditionStats[$condition]['avg_confidence_count']++;
    }
    if ($relevantRate !== null) {
        $analysisConditionStats[$condition]['relevant_rate_sum'] += (float) $relevantRate;
        $analysisConditionStats[$condition]['relevant_rate_count']++;
    }
    if ($participantRow['avg_docs_opened'] !== null) {
        $analysisConditionStats[$condition]['avg_docs_opened_sum'] += (float) $participantRow['avg_docs_opened'];
        $analysisConditionStats[$condition]['avg_docs_opened_count']++;
    }
    $analysisConditionStats[$condition]['overreliance_sum'] += (int) ($participantRow['overreliance_error_count'] ?? 0);

    if ($tasksCompleted === 2) {
        if ($correctCount <= 0) {
            $correctDist['0/2']++;
        } elseif ($correctCount === 1) {
            $correctDist['1/2']++;
        } else {
            $correctDist['2/2']++;
        }
    }
    if ($tasksCompleted === 2 && $relevantRate !== null) {
        $bucket = (int) round(((float) $relevantRate) * 100.0);
        if ($bucket <= 0) {
            $relevantDist['0%']++;
        } elseif ($bucket >= 100) {
            $relevantDist['100%']++;
        } else {
            $relevantDist['50%']++;
        }
    }
}

foreach ($analysisTaskLevelRows as $taskRow) {
    if ((int) ($taskRow['manual_code_required'] ?? 0) === 1) {
        $manualCodeRequiredCount++;
    }
    if (
        ($taskRow['selected_option_key'] ?? null) === 'other'
        && ($taskRow['final_decision_correct'] ?? null) === null
    ) {
        $uncodedOtherCount++;
        $uncodedOtherRows[] = [
            'participant_id' => (int) ($taskRow['participant_id'] ?? 0),
            'participant_code' => (string) ($taskRow['participant_code'] ?? ''),
            'condition_name' => (string) ($taskRow['condition_name'] ?? ''),
            'task_number' => (int) ($taskRow['task_number'] ?? 0),
            'custom_response_text' => (string) ($taskRow['custom_response_text'] ?? ''),
            'final_response' => (string) ($taskRow['final_response'] ?? ''),
            'active_reflection' => (string) ($taskRow['active_reflection'] ?? ''),
            'verification_intention' => (string) ($taskRow['verification_intention'] ?? ''),
        ];
    }
    if (($taskRow['final_decision_correct'] ?? null) === null) {
        $nullFinalDecisionCount++;
    }
}

$analysisCompletionRate = $analysisTotalRespondents > 0
    ? ($analysisCompletedRespondents / $analysisTotalRespondents) * 100.0
    : 0.0;
$analysisAvgCorrectPct = $analysisAvgCorrectPctCount > 0
    ? $analysisAvgCorrectPctSum / $analysisAvgCorrectPctCount
    : 0.0;
$analysisAvgConfidence = $analysisAvgConfidenceCount > 0
    ? $analysisAvgConfidenceSum / $analysisAvgConfidenceCount
    : 0.0;
$analysisRelevantOpenRatePct = $analysisRelevantOpenRateCount > 0
    ? ($analysisRelevantOpenRateSum / $analysisRelevantOpenRateCount) * 100.0
    : 0.0;

$analysisTaskRowsByParticipant = [];
foreach ($analysisTaskLevelRows as $taskRow) {
    $participantId = (int) ($taskRow['participant_id'] ?? 0);
    $taskNumber = (int) ($taskRow['task_number'] ?? 0);
    if ($participantId > 0 && $taskNumber > 0) {
        $analysisTaskRowsByParticipant[$participantId][$taskNumber] = $taskRow;
    }
}

$analysisCohortParticipants = array_values(array_filter(
    $analysisParticipantRows,
    static fn (array $row): bool => analysis_participant_finished_survey(
        (int) ($row['tasks_completed'] ?? 0),
        $row['serious_effort'] ?? null,
        $row['completed_at'] ?? null
    ) === 1
));
$analysisNotFinishedRows = [];
foreach ($analysisParticipantRows as $participantRow) {
    $tasksCompleted = (int) ($participantRow['tasks_completed'] ?? 0);
    $completedAt = trim((string) ($participantRow['completed_at'] ?? ''));
    $seriousEffort = $participantRow['serious_effort'] ?? null;
    if ($tasksCompleted === 2 && $completedAt !== '' && $seriousEffort !== null) {
        continue;
    }

    $reasons = [];
    if ($tasksCompleted < 2) {
        $reasons[] = 'tasks_completed < 2';
    }
    if ($completedAt === '') {
        $reasons[] = 'completed_at missing';
    }
    if ($seriousEffort === null) {
        $reasons[] = 'post-survey missing';
    }
    if (empty($reasons)) {
        $reasons[] = 'excluded from analysis cohort';
    }

    $participantId = (int) ($participantRow['participant_id'] ?? 0);
    $task1 = $analysisTaskRowsByParticipant[$participantId][1] ?? null;
    $task2 = $analysisTaskRowsByParticipant[$participantId][2] ?? null;

    $analysisNotFinishedRows[] = [
        'participant_id' => $participantId,
        'participant_code' => (string) ($participantRow['participant_code'] ?? ''),
        'condition_name' => (string) ($participantRow['condition_name'] ?? ''),
        'task1_reliance_choice' => is_array($task1) ? (string) ($task1['reliance_choice'] ?? '') : '',
        'task1_decision_correct' => is_array($task1) ? ($task1['final_decision_correct'] ?? null) : null,
        'task1_confidence' => is_array($task1) ? ($task1['confidence'] ?? null) : null,
        'task1_relevant_doc_opened' => is_array($task1) ? ($task1['relevant_document_opened'] ?? null) : null,
        'task1_number_docs_opened' => is_array($task1) ? ($task1['number_documents_opened'] ?? null) : null,
        'task1_docs_opened_any' => is_array($task1)
            ? ((((int) ($task1['number_documents_opened'] ?? 0)) > 0) ? 1 : 0)
            : null,
        'task1_total_doc_view_time_sec' => is_array($task1)
            ? (($task1['total_document_view_time_sec'] ?? null) !== null ? (float) $task1['total_document_view_time_sec'] : null)
            : null,
        'task2_reliance_choice' => is_array($task2) ? (string) ($task2['reliance_choice'] ?? '') : '',
        'task2_decision_correct' => is_array($task2) ? ($task2['final_decision_correct'] ?? null) : null,
        'task2_confidence' => is_array($task2) ? ($task2['confidence'] ?? null) : null,
        'task2_relevant_doc_opened' => is_array($task2) ? ($task2['relevant_document_opened'] ?? null) : null,
        'task2_number_docs_opened' => is_array($task2) ? ($task2['number_documents_opened'] ?? null) : null,
        'task2_docs_opened_any' => is_array($task2)
            ? ((((int) ($task2['number_documents_opened'] ?? 0)) > 0) ? 1 : 0)
            : null,
        'task2_total_doc_view_time_sec' => is_array($task2)
            ? (($task2['total_document_view_time_sec'] ?? null) !== null ? (float) $task2['total_document_view_time_sec'] : null)
            : null,
        'task1_duration_seconds' => is_array($task1) && ($task1['duration_seconds'] ?? null) !== null
            ? (float) $task1['duration_seconds']
            : null,
        'task2_duration_seconds' => is_array($task2) && ($task2['duration_seconds'] ?? null) !== null
            ? (float) $task2['duration_seconds']
            : null,
        'tasks_completed' => $tasksCompleted,
        'completed_at' => $completedAt,
        'serious_effort' => $seriousEffort,
        'reason' => implode(' + ', $reasons),
    ];
}
$analysisCohortParticipantIds = array_flip(array_map(
    static fn (array $row): int => (int) $row['participant_id'],
    $analysisCohortParticipants
));
$analysisCohortTaskRows = array_values(array_filter(
    $analysisTaskLevelRows,
    static fn (array $row): bool => isset($analysisCohortParticipantIds[(int) ($row['participant_id'] ?? 0)])
));

$analysisConditionAiBuckets = [];
foreach ($analysisCohortTaskRows as $taskRow) {
    $condition = (string) ($taskRow['condition_name'] ?? 'unknown');
    $aiCorrect = (int) ($taskRow['ai_correct'] ?? 0);
    if (!isset($analysisConditionAiBuckets[$condition])) {
        $analysisConditionAiBuckets[$condition] = [
            0 => ['n' => 0, 'correct' => 0],
            1 => ['n' => 0, 'correct' => 0],
        ];
    }
    if (($taskRow['final_decision_correct'] ?? null) === null) {
        continue;
    }
    $analysisConditionAiBuckets[$condition][$aiCorrect]['n']++;
    if ((int) $taskRow['final_decision_correct'] === 1) {
        $analysisConditionAiBuckets[$condition][$aiCorrect]['correct']++;
    }
}

$analysisPostsurveyByParticipant = [];
$analysisPostsurveyStmt = $pdo->query(
    'SELECT ps.*
     FROM postsurvey_responses ps
     INNER JOIN (
         SELECT participant_id, MAX(id) AS max_id
         FROM postsurvey_responses
         GROUP BY participant_id
     ) latest_ps ON latest_ps.max_id = ps.id'
);
if ($analysisPostsurveyStmt !== false) {
    foreach ($analysisPostsurveyStmt->fetchAll() as $postRow) {
        $analysisPostsurveyByParticipant[(int) ($postRow['participant_id'] ?? 0)] = $postRow;
    }
}

$analysisDataForAnalysisRows = build_analysis_data_for_analysis_rows(
    $analysisCohortParticipants,
    $analysisTaskRowsByParticipant,
    $analysisPostsurveyByParticipant
);
$analysisDataForAnalysisAllRows = build_analysis_data_for_analysis_rows(
    $analysisParticipantRows,
    $analysisTaskRowsByParticipant,
    $analysisPostsurveyByParticipant
);
$analysisSeriousEffortAllExportRows = [];
foreach ($analysisParticipantRows as $participantRow) {
    $participantId = (int) ($participantRow['participant_id'] ?? 0);
    if ($participantId <= 0) {
        continue;
    }

    $seriousEffort = $participantRow['serious_effort'] ?? null;
    $analysisSeriousEffortAllExportRows[] = [
        'participant_id' => $participantId,
        'serious_effort' => $seriousEffort === null ? '' : (string) (int) $seriousEffort,
    ];
}
$analysisDataForAnalysisByParticipant = [];
foreach ($analysisDataForAnalysisRows as $analysisRow) {
    $analysisParticipantId = (int) ($analysisRow['participant_id'] ?? 0);
    if ($analysisParticipantId > 0) {
        $analysisDataForAnalysisByParticipant[$analysisParticipantId] = $analysisRow;
    }
}

$avgSurveyDurationSeconds = 0.0;
$avgSurveyDurationCount = 0;
foreach ($analysisDataForAnalysisRows as $analysisRow) {
    if (($analysisRow['total_survey_duration_seconds'] ?? null) !== null) {
        $avgSurveyDurationSeconds += (float) $analysisRow['total_survey_duration_seconds'];
        $avgSurveyDurationCount++;
    }
}
$avgSurveyDurationSeconds = $avgSurveyDurationCount > 0
    ? ($avgSurveyDurationSeconds / $avgSurveyDurationCount)
    : 0.0;

$analysisDataForAnalysisColumns = [
    'participant_id',
    'participant_code',
    'condition_name',
    'prolific',
    'task1_reliance_choice',
    'task1_decision_correct',
    'task1_confidence',
    'task1_relevant_doc_opened',
    'task1_number_docs_opened',
    'task1_docs_opened_any',
    'task1_total_doc_view_time_sec',
    'task2_reliance_choice',
    'task2_decision_correct',
    'task2_confidence',
    'task2_relevant_doc_opened',
    'task2_number_docs_opened',
    'task2_docs_opened_any',
    'task2_total_doc_view_time_sec',
    'calibration_score',
    'avg_confidence',
    'avg_docs_opened',
    'total_doc_time_sec',
    'ai_literacy',
    'crt_score',
    'task_clarity',
    'notice_cue',
    'task_realism',
    'ai_experience',
    'age',
    'gender',
    'education',
    'task1_duration_seconds',
    'task2_duration_seconds',
    'postsurvey_duration_seconds',
    'total_survey_duration_seconds',
];

$analysisDataForAnalysisAllColumns = $analysisDataForAnalysisColumns;
array_splice($analysisDataForAnalysisAllColumns, 4, 0, ['finished_survey']);

$analysisTaskLevelExportColumns = [
    'participant_id',
    'participant_code',
    'condition_name',
    'prolific',
    'task_number',
    'ai_correct',
    'selected_option_key',
    'final_decision_correct',
    'inspection_any',
    'relevant_document_opened',
    'number_documents_opened',
    'total_document_view_time_sec',
    'relevant_document_view_time_sec',
    'confidence',
    'manual_code_required',
    'manual_response_correctness',
    'ai_literacy_score',
    'crt_score',
    'ai_experience',
    'age',
    'gender',
    'education',
    'low_quality_response',
];

$analysisTaskLevelAllExportColumns = $analysisTaskLevelExportColumns;
array_splice($analysisTaskLevelAllExportColumns, 4, 0, ['finished_survey']);

$participantsPerCondition = [];
foreach ($conditionNames as $conditionName) {
    $participantsPerCondition[$conditionName] = (int) ($respondentsByCondition[$conditionName] ?? 0);
}
$lowQualityCount = 0;
foreach ($analysisCohortParticipants as $row) {
    if ((int) ($row['low_quality_response'] ?? 0) === 1) {
        $lowQualityCount++;
    }
}
$shortFlagStatsByParticipant = [];
foreach ($analysisCohortTaskRows as $taskRow) {
    $participantId = (int) ($taskRow['participant_id'] ?? 0);
    if ($participantId <= 0) {
        continue;
    }
    if (!isset($shortFlagStatsByParticipant[$participantId])) {
        $shortFlagStatsByParticipant[$participantId] = [
            'tasks' => 0,
            'short_flags' => 0,
            'total_documents_opened' => 0,
            'total_task_duration_seconds' => 0.0,
        ];
    }
    $shortFlagStatsByParticipant[$participantId]['tasks']++;
    if ((int) ($taskRow['short_time_flag'] ?? 0) === 1) {
        $shortFlagStatsByParticipant[$participantId]['short_flags']++;
    }
    $shortFlagStatsByParticipant[$participantId]['total_documents_opened'] += max(0, (int) ($taskRow['number_documents_opened'] ?? 0));
    $shortFlagStatsByParticipant[$participantId]['total_task_duration_seconds'] += max(0.0, (float) ($taskRow['duration_seconds'] ?? 0.0));
}
$lowQualityRows = [];
foreach ($analysisCohortParticipants as $participantRow) {
    if ((int) ($participantRow['low_quality_response'] ?? 0) !== 1) {
        continue;
    }
    $participantId = (int) ($participantRow['participant_id'] ?? 0);
    $seriousEffort = $participantRow['serious_effort'] !== null ? (int) $participantRow['serious_effort'] : null;
    $taskStats = $shortFlagStatsByParticipant[$participantId] ?? [
        'tasks' => 0,
        'short_flags' => 0,
        'total_documents_opened' => 0,
        'total_task_duration_seconds' => 0.0,
    ];
    $hasLowEffort = $seriousEffort !== null && $seriousEffort <= 2;
    $hasBothShortFlags = (int) $taskStats['tasks'] >= 2 && (int) $taskStats['short_flags'] >= 2;
    $lowEffortFlag = ((int) $taskStats['total_documents_opened'] === 0 && (float) $taskStats['total_task_duration_seconds'] < 90.0) ? 1 : 0;
    $reasons = [];
    if ($hasLowEffort) {
        $reasons[] = 'serious_effort <= 2';
    }
    if ($hasBothShortFlags) {
        $reasons[] = 'short_time_flag = 1 on both tasks';
    }
    if (empty($reasons)) {
        $reasons[] = 'Flagged by low-quality rule';
    }
    $lowQualityRows[] = [
        'participant_id' => $participantId,
        'participant_code' => (string) ($participantRow['participant_code'] ?? ''),
        'condition_name' => (string) ($participantRow['condition_name'] ?? ''),
        'serious_effort' => $seriousEffort,
        'short_flags' => (int) $taskStats['short_flags'],
        'tasks_count' => (int) $taskStats['tasks'],
        'low_effort_flag' => $lowEffortFlag,
        'reason' => implode(' + ', $reasons),
    ];
}
$avgTaskDurationSeconds = 0.0;
$avgTaskDurationCount = 0;
foreach ($analysisCohortTaskRows as $row) {
    if (($row['duration_seconds'] ?? null) !== null) {
        $avgTaskDurationSeconds += (float) $row['duration_seconds'];
        $avgTaskDurationCount++;
    }
}
$avgTaskDurationSeconds = $avgTaskDurationCount > 0 ? ($avgTaskDurationSeconds / $avgTaskDurationCount) : 0.0;

$avgPostsurveyDurationSeconds = 0.0;
$avgPostsurveyDurationCount = 0;
$postsurveyDurationStmt = $pdo->query('SELECT duration_seconds FROM postsurvey_responses WHERE duration_seconds IS NOT NULL');
foreach ($postsurveyDurationStmt->fetchAll() as $durationRow) {
    $avgPostsurveyDurationSeconds += (float) ($durationRow['duration_seconds'] ?? 0);
    $avgPostsurveyDurationCount++;
}
$avgPostsurveyDurationSeconds = $avgPostsurveyDurationCount > 0
    ? ($avgPostsurveyDurationSeconds / $avgPostsurveyDurationCount)
    : 0.0;

$conditionResults = [];
$calibrationRows = [];
$inspectionRows = [];
$correctDistByCondition = [];
$relevantDistByCondition = [];
$participantUncodedOtherCount = [];

foreach ($analysisTaskLevelRows as $taskRow) {
    $participantId = (int) ($taskRow['participant_id'] ?? 0);
    if (
        ($taskRow['selected_option_key'] ?? null) === 'other'
        && ($taskRow['final_decision_correct'] ?? null) === null
    ) {
        $participantUncodedOtherCount[$participantId] = ($participantUncodedOtherCount[$participantId] ?? 0) + 1;
    }
}

foreach ($analysisCohortParticipants as $participantRow) {
    $condition = (string) ($participantRow['condition_name'] ?? 'unknown');
    if (!isset($conditionResults[$condition])) {
        $conditionResults[$condition] = [
            'n_completed' => 0,
            'correct_count_sum' => 0.0,
            'correct_pct_sum' => 0.0,
            'correct_pct_count' => 0,
            'two_of_two' => 0,
            'task_1_correct_sum' => 0.0,
            'task_1_correct_count' => 0,
            'task_2_correct_sum' => 0.0,
            'task_2_correct_count' => 0,
            'relevant_rate_sum' => 0.0,
            'relevant_rate_count' => 0,
            'avg_docs_opened_sum' => 0.0,
            'avg_docs_opened_count' => 0,
            'no_doc_open_count' => 0,
            'avg_total_doc_time_sum' => 0.0,
            'avg_total_doc_time_count' => 0,
            'avg_relevant_doc_time_sum' => 0.0,
            'avg_relevant_doc_time_count' => 0,
            'avg_confidence_sum' => 0.0,
            'avg_confidence_count' => 0,
            'avg_task_duration_sum' => 0.0,
            'avg_task_duration_count' => 0,
            'avg_total_survey_duration_sum' => 0.0,
            'avg_total_survey_duration_count' => 0,
            'task1_duration_sum' => 0.0,
            'task1_duration_count' => 0,
            'task2_duration_sum' => 0.0,
            'task2_duration_count' => 0,
        ];
        $correctDistByCondition[$condition] = ['0' => 0, '1' => 0, '2' => 0];
        $relevantDistByCondition[$condition] = ['0' => 0, '50' => 0, '100' => 0];
    }
    $conditionResults[$condition]['n_completed']++;
    $correctCount = (int) ($participantRow['correct_count'] ?? 0);
    $conditionResults[$condition]['correct_count_sum'] += $correctCount;
    if ($participantRow['correct_pct'] !== null) {
        $conditionResults[$condition]['correct_pct_sum'] += (float) $participantRow['correct_pct'];
        $conditionResults[$condition]['correct_pct_count']++;
    }
    if ($correctCount === 2) {
        $conditionResults[$condition]['two_of_two']++;
    }
    if ($participantRow['relevant_doc_open_rate'] !== null) {
        $rate = (float) $participantRow['relevant_doc_open_rate'];
        $conditionResults[$condition]['relevant_rate_sum'] += $rate;
        $conditionResults[$condition]['relevant_rate_count']++;
        $rateBucket = (int) round($rate * 100.0);
        if ($rateBucket <= 0) {
            $relevantDistByCondition[$condition]['0']++;
        } elseif ($rateBucket >= 100) {
            $relevantDistByCondition[$condition]['100']++;
        } else {
            $relevantDistByCondition[$condition]['50']++;
        }
    }
    if ($participantRow['avg_docs_opened'] !== null) {
        $avgDocsOpenedValue = (float) $participantRow['avg_docs_opened'];
        $conditionResults[$condition]['avg_docs_opened_sum'] += $avgDocsOpenedValue;
        $conditionResults[$condition]['avg_docs_opened_count']++;
        if ($avgDocsOpenedValue <= 0.0) {
            $conditionResults[$condition]['no_doc_open_count']++;
        }
    }
    if ($participantRow['avg_total_doc_time_sec'] !== null) {
        $conditionResults[$condition]['avg_total_doc_time_sum'] += (float) $participantRow['avg_total_doc_time_sec'];
        $conditionResults[$condition]['avg_total_doc_time_count']++;
    }
    if ($participantRow['avg_relevant_doc_time_sec'] !== null) {
        $conditionResults[$condition]['avg_relevant_doc_time_sum'] += (float) $participantRow['avg_relevant_doc_time_sec'];
        $conditionResults[$condition]['avg_relevant_doc_time_count']++;
    }
    if ($participantRow['avg_confidence'] !== null) {
        $conditionResults[$condition]['avg_confidence_sum'] += (float) $participantRow['avg_confidence'];
        $conditionResults[$condition]['avg_confidence_count']++;
    }
    $participantIdForDuration = (int) ($participantRow['participant_id'] ?? 0);
    $durationRow = $analysisDataForAnalysisByParticipant[$participantIdForDuration] ?? null;
    if (is_array($durationRow)) {
        if (($durationRow['task1_duration_seconds'] ?? null) !== null) {
            $conditionResults[$condition]['avg_task_duration_sum'] += (float) $durationRow['task1_duration_seconds'];
            $conditionResults[$condition]['avg_task_duration_count']++;
            $conditionResults[$condition]['task1_duration_sum'] += (float) $durationRow['task1_duration_seconds'];
            $conditionResults[$condition]['task1_duration_count']++;
        }
        if (($durationRow['task2_duration_seconds'] ?? null) !== null) {
            $conditionResults[$condition]['avg_task_duration_sum'] += (float) $durationRow['task2_duration_seconds'];
            $conditionResults[$condition]['avg_task_duration_count']++;
            $conditionResults[$condition]['task2_duration_sum'] += (float) $durationRow['task2_duration_seconds'];
            $conditionResults[$condition]['task2_duration_count']++;
        }
        if (($durationRow['total_survey_duration_seconds'] ?? null) !== null) {
            $conditionResults[$condition]['avg_total_survey_duration_sum'] += (float) $durationRow['total_survey_duration_seconds'];
            $conditionResults[$condition]['avg_total_survey_duration_count']++;
        }
    }
    $correctDistKey = (string) max(0, min(2, $correctCount));
    $correctDistByCondition[$condition][$correctDistKey]++;
}

foreach ($analysisCohortTaskRows as $taskRow) {
    $condition = (string) ($taskRow['condition_name'] ?? 'unknown');
    if (!isset($conditionResults[$condition])) {
        $conditionResults[$condition] = [
            'n_completed' => 0,
            'correct_count_sum' => 0.0,
            'correct_pct_sum' => 0.0,
            'correct_pct_count' => 0,
            'two_of_two' => 0,
            'task_1_correct_sum' => 0.0,
            'task_1_correct_count' => 0,
            'task_2_correct_sum' => 0.0,
            'task_2_correct_count' => 0,
            'relevant_rate_sum' => 0.0,
            'relevant_rate_count' => 0,
            'avg_docs_opened_sum' => 0.0,
            'avg_docs_opened_count' => 0,
            'no_doc_open_count' => 0,
            'avg_total_doc_time_sum' => 0.0,
            'avg_total_doc_time_count' => 0,
            'avg_relevant_doc_time_sum' => 0.0,
            'avg_relevant_doc_time_count' => 0,
            'avg_confidence_sum' => 0.0,
            'avg_confidence_count' => 0,
            'avg_task_duration_sum' => 0.0,
            'avg_task_duration_count' => 0,
            'avg_total_survey_duration_sum' => 0.0,
            'avg_total_survey_duration_count' => 0,
            'task1_duration_sum' => 0.0,
            'task1_duration_count' => 0,
            'task2_duration_sum' => 0.0,
            'task2_duration_count' => 0,
        ];
    }
    $taskNumber = (int) ($taskRow['task_number'] ?? 0);
    if (($taskRow['final_decision_correct'] ?? null) !== null) {
        $finalDecisionCorrect = (float) $taskRow['final_decision_correct'];
        if ($taskNumber === 1) {
            $conditionResults[$condition]['task_1_correct_sum'] += $finalDecisionCorrect;
            $conditionResults[$condition]['task_1_correct_count']++;
        } elseif ($taskNumber === 2) {
            $conditionResults[$condition]['task_2_correct_sum'] += $finalDecisionCorrect;
            $conditionResults[$condition]['task_2_correct_count']++;
        }
    }
    $aiCorrect = (int) ($taskRow['ai_correct'] ?? 0);
    $calibrationKey = $condition . '|' . $aiCorrect;
    if (!isset($calibrationRows[$calibrationKey])) {
        $calibrationRows[$calibrationKey] = [
            'condition_name' => $condition,
            'ai_correct' => $aiCorrect,
            'n' => 0,
            'final_correct_sum' => 0.0,
            'final_correct_count' => 0,
            'relevant_open_sum' => 0.0,
            'relevant_open_count' => 0,
            'confidence_sum' => 0.0,
            'confidence_count' => 0,
            'relevant_time_sum' => 0.0,
            'relevant_time_count' => 0,
            'overreliance_sum' => 0.0,
            'overreliance_count' => 0,
            'underreliance_sum' => 0.0,
            'underreliance_count' => 0,
        ];
    }
    $calibrationRows[$calibrationKey]['n']++;
    if (($taskRow['final_decision_correct'] ?? null) !== null) {
        $calibrationRows[$calibrationKey]['final_correct_sum'] += (float) $taskRow['final_decision_correct'];
        $calibrationRows[$calibrationKey]['final_correct_count']++;
    }
    if (($taskRow['relevant_document_opened'] ?? null) !== null) {
        $calibrationRows[$calibrationKey]['relevant_open_sum'] += (float) $taskRow['relevant_document_opened'];
        $calibrationRows[$calibrationKey]['relevant_open_count']++;
    }
    if (($taskRow['confidence'] ?? null) !== null) {
        $calibrationRows[$calibrationKey]['confidence_sum'] += (float) $taskRow['confidence'];
        $calibrationRows[$calibrationKey]['confidence_count']++;
    }
    if (($taskRow['relevant_document_view_time_sec'] ?? null) !== null) {
        $calibrationRows[$calibrationKey]['relevant_time_sum'] += (float) $taskRow['relevant_document_view_time_sec'];
        $calibrationRows[$calibrationKey]['relevant_time_count']++;
    }
    if ($aiCorrect === 0) {
        $calibrationRows[$calibrationKey]['overreliance_sum'] += (float) ($taskRow['overreliance_error'] ?? 0);
        $calibrationRows[$calibrationKey]['overreliance_count']++;
    }
    if ($aiCorrect === 1) {
        $calibrationRows[$calibrationKey]['underreliance_sum'] += (float) ($taskRow['underreliance_or_false_alarm_error'] ?? 0);
        $calibrationRows[$calibrationKey]['underreliance_count']++;
    }

    if (!isset($inspectionRows[$condition])) {
        $inspectionRows[$condition] = [
            'n' => 0,
            'inspection_any_sum' => 0.0,
            'inspection_any_count' => 0,
            'inspection_relevant_sum' => 0.0,
            'inspection_relevant_count' => 0,
            'opened_all_sum' => 0.0,
            'opened_all_count' => 0,
            'docs_opened_sum' => 0.0,
            'docs_opened_count' => 0,
            'total_time_sum' => 0.0,
            'total_time_count' => 0,
            'relevant_time_sum' => 0.0,
            'relevant_time_count' => 0,
        ];
    }
    $inspectionRows[$condition]['n']++;
    $inspectionRows[$condition]['inspection_any_sum'] += (float) ($taskRow['inspection_any'] ?? 0);
    $inspectionRows[$condition]['inspection_any_count']++;
    $inspectionRows[$condition]['inspection_relevant_sum'] += (float) ($taskRow['inspection_relevant'] ?? 0);
    $inspectionRows[$condition]['inspection_relevant_count']++;
    $inspectionRows[$condition]['opened_all_sum'] += (float) ($taskRow['opened_all_docs'] ?? 0);
    $inspectionRows[$condition]['opened_all_count']++;
    $inspectionRows[$condition]['docs_opened_sum'] += (float) ($taskRow['number_documents_opened'] ?? 0);
    $inspectionRows[$condition]['docs_opened_count']++;
    $inspectionRows[$condition]['total_time_sum'] += (float) ($taskRow['total_document_view_time_sec'] ?? 0);
    $inspectionRows[$condition]['total_time_count']++;
    $inspectionRows[$condition]['relevant_time_sum'] += (float) ($taskRow['relevant_document_view_time_sec'] ?? 0);
    $inspectionRows[$condition]['relevant_time_count']++;
}

$analysisConditionStats = sort_condition_keyed_array($analysisConditionStats);
$analysisConditionAiBuckets = sort_condition_keyed_array($analysisConditionAiBuckets);
$conditionResults = sort_condition_keyed_array($conditionResults);
$inspectionRows = sort_condition_keyed_array($inspectionRows);
$correctDistByCondition = sort_condition_keyed_array($correctDistByCondition);
$relevantDistByCondition = sort_condition_keyed_array($relevantDistByCondition);
$participantsPerCondition = sort_condition_keyed_array($participantsPerCondition);
$completedByCondition = sort_condition_keyed_array($completedByCondition);

$calibrationRows = array_values($calibrationRows);
usort($calibrationRows, static function (array $a, array $b): int {
    $conditionA = (string) ($a['condition_name'] ?? '');
    $conditionB = (string) ($b['condition_name'] ?? '');
    $weightCompare = condition_sort_weight($conditionA) <=> condition_sort_weight($conditionB);
    if ($weightCompare !== 0) {
        return $weightCompare;
    }
    $nameCompare = strcmp($conditionA, $conditionB);
    if ($nameCompare !== 0) {
        return $nameCompare;
    }
    return ((int) ($a['ai_correct'] ?? 0)) <=> ((int) ($b['ai_correct'] ?? 0));
});

if ($currentTab === 'data_for_analysis_all' && ((string) ($_GET['download'] ?? '')) === 'serious_effort') {
    $filename = 'data_for_analysis_all_serious_effort_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    if ($output === false) {
        http_response_code(500);
        exit('Could not open CSV output stream.');
    }

    $seriousEffortExportColumns = ['participant_id', 'serious_effort'];
    fputcsv($output, $seriousEffortExportColumns, ',', '"', '\\');
    foreach ($analysisSeriousEffortAllExportRows as $row) {
        fputcsv($output, [
            (string) ($row['participant_id'] ?? ''),
            (string) ($row['serious_effort'] ?? ''),
        ], ',', '"', '\\');
    }
    fclose($output);
    exit;
}

if (in_array($currentTab, ['data_for_analysis', 'data_for_analysis_all'], true) && ((string) ($_GET['download'] ?? '0')) === '1') {
    $downloadRows = $currentTab === 'data_for_analysis_all'
        ? $analysisDataForAnalysisAllRows
        : $analysisDataForAnalysisRows;
    $exportColumns = $currentTab === 'data_for_analysis_all'
        ? $analysisDataForAnalysisAllColumns
        : $analysisDataForAnalysisColumns;
    $filename = $currentTab . '_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    if ($output === false) {
        http_response_code(500);
        exit('Could not open CSV output stream.');
    }

    fputcsv($output, $exportColumns, ',', '"', '\\');
    foreach ($downloadRows as $row) {
        $csvRow = [];
        foreach ($exportColumns as $column) {
            $value = $row[$column] ?? '';
            if ($value === null) {
                $csvRow[] = '';
                continue;
            }
            if (
                in_array($column, [
                    'task1_total_doc_view_time_sec',
                    'task2_total_doc_view_time_sec',
                    'calibration_score',
                    'avg_confidence',
                    'avg_docs_opened',
                    'total_doc_time_sec',
                    'task1_duration_seconds',
                    'task2_duration_seconds',
                    'postsurvey_duration_seconds',
                    'total_survey_duration_seconds',
                ], true)
            ) {
                $csvRow[] = number_format((float) $value, 2, '.', '');
            } else {
                $csvRow[] = (string) $value;
            }
        }
        fputcsv($output, $csvRow, ',', '"', '\\');
    }
    fclose($output);
    exit;
}

if (in_array($currentTab, ['task_level_analysis', 'task_level_analysis_all'], true) && ((string) ($_GET['download'] ?? '0')) === '1') {
    $downloadRows = $currentTab === 'task_level_analysis_all'
        ? $analysisTaskLevelRows
        : $analysisCohortTaskRows;
    $exportColumns = $currentTab === 'task_level_analysis_all'
        ? $analysisTaskLevelAllExportColumns
        : $analysisTaskLevelExportColumns;
    $filename = $currentTab . '_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    if ($output === false) {
        http_response_code(500);
        exit('Could not open CSV output stream.');
    }

    fputcsv($output, $exportColumns, ',', '"', '\\');
    foreach ($downloadRows as $row) {
        $csvRow = [];
        foreach ($exportColumns as $column) {
            $value = $row[$column] ?? '';
            if ($value === null) {
                $csvRow[] = '';
                continue;
            }
            if (
                in_array($column, [
                    'total_document_view_time_sec',
                    'relevant_document_view_time_sec',
                    'ai_literacy_score',
                ], true)
            ) {
                $csvRow[] = number_format((float) $value, 2, '.', '');
            } else {
                $csvRow[] = (string) $value;
            }
        }
        fputcsv($output, $csvRow, ',', '"', '\\');
    }
    fclose($output);
    exit;
}

$pageTitle = 'Internal Dashboard';
require __DIR__ . '/../views/header.php';
?>

<main class="max-w-6xl mx-auto px-4 py-6 md:py-8">
    <section class="mb-5 md:mb-6 flex flex-col sm:flex-row sm:items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 mb-1">Internal Survey Dashboard</h1>
            <p class="text-slate-600 text-sm">Internal monitoring view for thesis supervision.</p>
        </div>
        <a
            href="/dashboard/?logout=1"
            class="inline-flex items-center justify-center bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-3 py-2 rounded-lg transition w-full sm:w-auto"
        >
            Log out
        </a>
    </section>

    <section class="mb-4 md:mb-6">
        <div class="md:hidden space-y-2">
            <div class="grid grid-cols-2 gap-2">
                <a
                    href="/dashboard/?tab=overview<?= e($includeTestQuery) ?>"
                    class="text-center px-3 py-2 text-xs font-semibold rounded-lg border transition <?= $currentTab === 'overview' ? 'accent-bg text-white border-transparent' : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-100' ?>"
                >
                    Overview
                </a>
                <a
                    href="/dashboard/?tab=data_for_analysis<?= e($includeTestQuery) ?>"
                    class="text-center px-3 py-2 text-xs font-semibold rounded-lg border transition <?= $currentTab === 'data_for_analysis' ? 'accent-bg text-white border-transparent' : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-100' ?>"
                >
                    Data for analysis
                </a>
                <a
                    href="/dashboard/?tab=data_for_analysis_all<?= e($includeTestQuery) ?>"
                    class="text-center px-3 py-2 text-xs font-semibold rounded-lg border transition <?= $currentTab === 'data_for_analysis_all' ? 'accent-bg text-white border-transparent' : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-100' ?>"
                >
                    Data for analysis (all)
                </a>
                <a
                    href="/dashboard/?tab=data<?= e($includeTestQuery) ?>"
                    class="text-center px-3 py-2 text-xs font-semibold rounded-lg border transition <?= $currentTab === 'data' ? 'accent-bg text-white border-transparent' : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-100' ?>"
                >
                    Raw Data
                </a>
                <a
                    href="/dashboard/?tab=trash<?= e($includeTestQuery) ?>"
                    class="text-center px-3 py-2 text-xs font-semibold rounded-lg border transition <?= $currentTab === 'trash' ? 'bg-rose-600 text-white border-transparent' : 'bg-white border-rose-200 text-rose-700 hover:bg-rose-50' ?>"
                >
                    Trash
                </a>
            </div>
            <form method="get" action="/dashboard/" class="bg-white border border-slate-200 rounded-lg p-3 flex items-end gap-2">
                <div class="flex-1">
                    <label for="dashboard_tab_mobile" class="block text-xs font-medium text-slate-600 mb-1">Secondary views</label>
                    <select id="dashboard_tab_mobile" name="tab" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="condition_results" <?= $currentTab === 'condition_results' ? 'selected' : '' ?>>Condition Results</option>
                        <option value="calibration" <?= $currentTab === 'calibration' ? 'selected' : '' ?>>Calibration by Task</option>
                        <option value="inspection" <?= $currentTab === 'inspection' ? 'selected' : '' ?>>Inspection Behavior</option>
                        <option value="participants_analysis" <?= $currentTab === 'participants_analysis' ? 'selected' : '' ?>>Participants</option>
                        <option value="task_level_analysis" <?= $currentTab === 'task_level_analysis' ? 'selected' : '' ?>>Task-Level Data</option>
                        <option value="task_level_analysis_all" <?= $currentTab === 'task_level_analysis_all' ? 'selected' : '' ?>>Task-Level Data (all)</option>
                        <option value="data_for_analysis_all" <?= $currentTab === 'data_for_analysis_all' ? 'selected' : '' ?>>Data for analysis (all)</option>
                        <?php if ($currentTab === 'participant' && $participantDetailId !== false && $participantDetailId !== null): ?>
                            <option value="participant" selected>Participant <?= e((string) $participantDetailId) ?></option>
                        <?php endif; ?>
                    </select>
                </div>
                <?php if ($includeTestParticipants): ?>
                    <input type="hidden" name="include_test" value="1">
                <?php endif; ?>
                <?php if ($currentTab === 'participant' && $participantDetailId !== false && $participantDetailId !== null): ?>
                    <input type="hidden" name="participant_id" value="<?= e((string) $participantDetailId) ?>">
                <?php endif; ?>
                <?php if ($currentTab === 'data'): ?>
                    <input type="hidden" name="table" value="<?= e($selectedTable) ?>">
                <?php endif; ?>
                <button type="submit" class="accent-bg accent-bg-hover text-white text-sm font-medium px-3 py-2 rounded-lg transition">
                    Go
                </button>
            </form>
        </div>

        <div class="hidden md:block">
            <div class="flex items-center justify-between gap-3 mb-2">
                <div class="flex items-center gap-1 overflow-x-auto rounded-xl border border-slate-200 bg-white p-1.5 shadow-sm">
                    <a
                href="/dashboard/?tab=overview<?= e($includeTestQuery) ?>"
                class="px-3 py-1.5 text-sm font-medium whitespace-nowrap rounded-md transition <?= $currentTab === 'overview' ? 'accent-bg text-white shadow-sm' : 'text-slate-700 hover:bg-slate-100' ?>"
            >
                Overview / Data Quality
            </a>
                    <a
                href="/dashboard/?tab=data_for_analysis<?= e($includeTestQuery) ?>"
                class="px-3 py-1.5 text-sm font-medium whitespace-nowrap rounded-md transition <?= $currentTab === 'data_for_analysis' ? 'accent-bg text-white shadow-sm' : 'text-slate-700 hover:bg-slate-100' ?>"
            >
                Data for analysis
            </a>
                    <a
                href="/dashboard/?tab=data_for_analysis_all<?= e($includeTestQuery) ?>"
                class="px-3 py-1.5 text-sm font-medium whitespace-nowrap rounded-md transition <?= $currentTab === 'data_for_analysis_all' ? 'accent-bg text-white shadow-sm' : 'text-slate-700 hover:bg-slate-100' ?>"
            >
                Data for analysis (all)
            </a>
                    <a
                href="/dashboard/?tab=data<?= e($includeTestQuery) ?>"
                class="px-3 py-1.5 text-sm font-medium whitespace-nowrap rounded-md transition <?= $currentTab === 'data' ? 'accent-bg text-white shadow-sm' : 'text-slate-700 hover:bg-slate-100' ?>"
            >
                Raw Data
            </a>
                </div>
                <a
                href="/dashboard/?tab=trash<?= e($includeTestQuery) ?>"
                class="px-3 py-1.5 text-sm font-medium whitespace-nowrap rounded-md border transition <?= $currentTab === 'trash' ? 'bg-rose-600 text-white border-transparent shadow-sm' : 'border-rose-200 text-rose-700 hover:bg-rose-50' ?>"
            >
                Trash
            </a>
            </div>
            <div class="flex items-center gap-1 overflow-x-auto rounded-xl border border-slate-200 bg-white p-1.5 shadow-sm">
                <a
                    href="/dashboard/?tab=condition_results<?= e($includeTestQuery) ?>"
                    class="px-3 py-1.5 text-sm font-medium whitespace-nowrap rounded-md transition <?= $currentTab === 'condition_results' ? 'accent-bg text-white shadow-sm' : 'text-slate-700 hover:bg-slate-100' ?>"
                >
                    Condition Results
                </a>
                <a
                    href="/dashboard/?tab=calibration<?= e($includeTestQuery) ?>"
                    class="px-3 py-1.5 text-sm font-medium whitespace-nowrap rounded-md transition <?= $currentTab === 'calibration' ? 'accent-bg text-white shadow-sm' : 'text-slate-700 hover:bg-slate-100' ?>"
                >
                    Calibration by Task
                </a>
                <a
                    href="/dashboard/?tab=inspection<?= e($includeTestQuery) ?>"
                    class="px-3 py-1.5 text-sm font-medium whitespace-nowrap rounded-md transition <?= $currentTab === 'inspection' ? 'accent-bg text-white shadow-sm' : 'text-slate-700 hover:bg-slate-100' ?>"
                >
                    Inspection Behavior
                </a>
                <a
                    href="/dashboard/?tab=participants_analysis<?= e($includeTestQuery) ?>"
                    class="px-3 py-1.5 text-sm font-medium whitespace-nowrap rounded-md transition <?= $currentTab === 'participants_analysis' ? 'accent-bg text-white shadow-sm' : 'text-slate-700 hover:bg-slate-100' ?>"
                >
                    Participants
                </a>
                <a
                    href="/dashboard/?tab=task_level_analysis<?= e($includeTestQuery) ?>"
                    class="px-3 py-1.5 text-sm font-medium whitespace-nowrap rounded-md transition <?= $currentTab === 'task_level_analysis' ? 'accent-bg text-white shadow-sm' : 'text-slate-700 hover:bg-slate-100' ?>"
                >
                    Task-Level Data
                </a>
                <a
                    href="/dashboard/?tab=task_level_analysis_all<?= e($includeTestQuery) ?>"
                    class="px-3 py-1.5 text-sm font-medium whitespace-nowrap rounded-md transition <?= $currentTab === 'task_level_analysis_all' ? 'accent-bg text-white shadow-sm' : 'text-slate-700 hover:bg-slate-100' ?>"
                >
                    Task-Level Data (all)
                </a>
                <?php if ($currentTab === 'participant' && $participantDetailId !== false && $participantDetailId !== null): ?>
                    <a
                        href="/dashboard/?tab=participant&participant_id=<?= e((string) $participantDetailId) ?><?= e($includeTestQuery) ?>"
                        class="px-3 py-1.5 text-sm font-medium whitespace-nowrap rounded-md transition accent-bg text-white shadow-sm"
                    >
                        Participant <?= e((string) $participantDetailId) ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <section class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <form method="get" action="/dashboard/" class="inline-flex items-center gap-2 text-sm text-slate-700 flex-wrap">
            <input type="hidden" name="tab" value="<?= e($currentTab) ?>">
            <?php if ($currentTab === 'data'): ?>
                <input type="hidden" name="table" value="<?= e($selectedTable) ?>">
            <?php endif; ?>
            <?php if ($currentTab === 'participant' && $participantDetailId !== false && $participantDetailId !== null): ?>
                <input type="hidden" name="participant_id" value="<?= e((string) $participantDetailId) ?>">
            <?php endif; ?>
            <label class="inline-flex items-center gap-2">
                <input
                    type="checkbox"
                    name="include_test"
                    value="1"
                    <?= $includeTestParticipants ? 'checked' : '' ?>
                    onchange="this.form.submit()"
                >
                <span>Include test participants (<?= e(TEST_PARTICIPANT_PREFIX) ?>*)</span>
            </label>
        </form>
        <a
            href="/dashboard/?tab=<?= e($currentTab) ?><?= e($currentTab === 'data' ? '&table=' . urlencode($selectedTable) : '') ?><?= e($currentTab === 'participant' && $participantDetailId !== false && $participantDetailId !== null ? '&participant_id=' . urlencode((string) $participantDetailId) : '') ?><?= e($includeTestParticipants ? '&include_test=1' : '') ?>"
            class="inline-flex md:hidden items-center justify-center bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-3 py-2 rounded-lg transition"
        >
            Refresh
        </a>
    </section>

    <?php if ($flashSuccess !== ''): ?>
        <section class="mb-4">
            <p class="text-sm text-emerald-700"><?= e($flashSuccess) ?></p>
        </section>
    <?php endif; ?>
    <?php if ($flashError !== ''): ?>
        <section class="mb-4">
            <p class="text-sm text-rose-700"><?= e($flashError) ?></p>
        </section>
    <?php endif; ?>
    <?php if ($currentTab === 'overview'): ?>
        <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
            <article class="bg-white shadow rounded-xl p-6 min-h-36">
                <p class="text-sm font-medium text-slate-500">Total started participants</p>
                <p class="text-3xl font-bold text-slate-800 mt-2"><?= e((string) $totalRespondents) ?></p>
            </article>
            <article class="bg-white shadow rounded-xl p-6 min-h-36">
                <p class="text-sm font-medium text-slate-500">Completed respondents</p>
                <p class="text-3xl font-bold text-slate-800 mt-2"><?= e((string) $analysisCompletedRespondents) ?></p>
                <p class="text-xs text-slate-500 mt-2">From Prolific: <?= e((string) $analysisCompletedProlificRespondents) ?></p>
            </article>
            <article class="bg-white shadow rounded-xl p-6 min-h-36">
                <p class="text-sm font-medium text-slate-500">Completion rate</p>
                <p class="text-3xl font-bold text-slate-800 mt-2"><?= e(number_format($analysisCompletionRate, 1)) ?>%</p>
            </article>
            <article class="bg-white shadow rounded-xl p-6 min-h-36">
                <p class="text-sm font-medium text-slate-500">Started per condition</p>
                <?php $maxStartedPerCondition = max([1, ...array_values($participantsPerCondition)]); ?>
                <div class="mt-3 space-y-2.5">
                    <?php foreach ($participantsPerCondition as $condition => $count): ?>
                        <?php $barWidth = ((float) $count / (float) $maxStartedPerCondition) * 100.0; ?>
                        <div>
                            <div class="flex items-center justify-between text-sm mb-1">
                                <span class="font-medium text-slate-700"><?= e((string) $condition) ?></span>
                                <span class="text-slate-600 tabular-nums"><?= e((string) $count) ?></span>
                            </div>
                            <div class="w-full h-3 bg-slate-100 rounded">
                                <div class="h-3 accent-bg rounded" style="width: <?= e(number_format($barWidth, 2, '.', '')) ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>
            <article class="bg-white shadow rounded-xl p-6 min-h-36">
                <p class="text-sm font-medium text-slate-500">Completed per condition</p>
                <?php $maxCompletedPerCondition = max([1, ...array_values($completedByCondition)]); ?>
                <div class="mt-3 space-y-2.5">
                    <?php foreach ($participantsPerCondition as $condition => $count): ?>
                        <?php
                        $completedCount = (int) ($completedByCondition[$condition] ?? 0);
                        $barWidth = ((float) $completedCount / (float) $maxCompletedPerCondition) * 100.0;
                        ?>
                        <div>
                            <div class="flex items-center justify-between text-sm mb-1">
                                <span class="font-medium text-slate-700"><?= e((string) $condition) ?></span>
                                <span class="text-slate-600 tabular-nums"><?= e((string) $completedCount) ?></span>
                            </div>
                            <div class="w-full h-3 bg-slate-100 rounded">
                                <div class="h-3 accent-bg rounded" style="width: <?= e(number_format($barWidth, 2, '.', '')) ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>
            <article class="bg-white shadow rounded-xl p-6 min-h-36">
                <p class="text-sm font-medium text-slate-500">Abandoned per condition</p>
                <?php
                $abandonedByCondition = [];
                foreach ($participantsPerCondition as $condition => $startedCount) {
                    $completedCount = (int) ($completedByCondition[$condition] ?? 0);
                    $abandonedByCondition[$condition] = max(0, (int) $startedCount - $completedCount);
                }
                $maxAbandonedPerCondition = max([1, ...array_values($abandonedByCondition)]);
                ?>
                <div class="mt-3 space-y-2.5">
                    <?php foreach ($abandonedByCondition as $condition => $abandonedCount): ?>
                        <?php $barWidth = ((float) $abandonedCount / (float) $maxAbandonedPerCondition) * 100.0; ?>
                        <div>
                            <div class="flex items-center justify-between text-sm mb-1">
                                <span class="font-medium text-slate-700"><?= e((string) $condition) ?></span>
                                <span class="text-slate-600 tabular-nums"><?= e((string) $abandonedCount) ?></span>
                            </div>
                            <div class="w-full h-3 bg-slate-100 rounded">
                                <div class="h-3 bg-amber-500 rounded" style="width: <?= e(number_format($barWidth, 2, '.', '')) ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>
        </section>

        <section class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <article class="bg-white shadow rounded-xl p-6 min-h-36">
                <p class="text-sm font-medium text-slate-500">Average task duration</p>
                <p class="text-3xl font-bold text-slate-800 mt-2"><?= e(number_format($avgTaskDurationSeconds, 1)) ?>s</p>
            </article>
            <article class="bg-white shadow rounded-xl p-6 min-h-36">
                <p class="text-sm font-medium text-slate-500">Average post-survey duration</p>
                <p class="text-3xl font-bold text-slate-800 mt-2"><?= e(number_format($avgPostsurveyDurationSeconds, 1)) ?>s</p>
            </article>
            <article class="bg-white shadow rounded-xl p-6 min-h-36">
                <p class="text-sm font-medium text-slate-500">Average survey duration</p>
                <p class="text-3xl font-bold text-slate-800 mt-2"><?= e(number_format($avgSurveyDurationSeconds, 1)) ?>s</p>
            </article>
        </section>

        <section class="bg-white shadow rounded-xl p-6 mb-6">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Analysis Exports</h2>
            <div class="flex flex-wrap gap-3">
                <a
                    href="/dashboard/?tab=task_level_analysis&download=1<?= e($includeTestParticipants ? '&include_test=1' : '') ?>"
                    class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-4 py-2 rounded-lg transition"
                >
                    Download Task-Level Data (cohort) CSV
                </a>
                <a
                    href="/dashboard/?tab=task_level_analysis_all&download=1<?= e($includeTestParticipants ? '&include_test=1' : '') ?>"
                    class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-4 py-2 rounded-lg transition"
                >
                    Download Task-Level Data (all) CSV
                </a>
                <a
                    href="/export_csv.php?table=analysis_participant_summary<?= e($includeTestParticipants ? '&include_test=1' : '') ?>"
                    class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-4 py-2 rounded-lg transition"
                >
                    Download Participant Summary CSV
                </a>
                <a
                    href="/dashboard/?tab=data_for_analysis&download=1<?= e($includeTestParticipants ? '&include_test=1' : '') ?>"
                    class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-4 py-2 rounded-lg transition"
                >
                    Download Data for Analysis CSV
                </a>
                <a
                    href="/dashboard/?tab=data_for_analysis_all&download=1<?= e($includeTestParticipants ? '&include_test=1' : '') ?>"
                    class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-4 py-2 rounded-lg transition"
                >
                    Download Data for Analysis (all) CSV
                </a>
                <a
                    href="/dashboard/?tab=data_for_analysis_all&download=serious_effort<?= e($includeTestParticipants ? '&include_test=1' : '') ?>"
                    class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-4 py-2 rounded-lg transition"
                >
                    Download Serious Effort (all) CSV
                </a>
                <a
                    href="/export_csv.php?table=document_inspection_data<?= e($includeTestParticipants ? '&include_test=1' : '') ?>"
                    class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-4 py-2 rounded-lg transition"
                >
                    Download Document Inspection CSV
                </a>
                <a
                    href="/export_csv.php?table=document_events<?= e($includeTestParticipants ? '&include_test=1' : '') ?>"
                    class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-4 py-2 rounded-lg transition"
                >
                    Download Raw document_events CSV
                </a>
                <a
                    href="/export_csv.php?table=task_responses<?= e($includeTestParticipants ? '&include_test=1' : '') ?>"
                    class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-4 py-2 rounded-lg transition"
                >
                    Download Raw task_responses CSV
                </a>
                <a
                    href="/export_csv.php?table=postsurvey_responses<?= e($includeTestParticipants ? '&include_test=1' : '') ?>"
                    class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-4 py-2 rounded-lg transition"
                >
                    Download Raw postsurvey_responses CSV
                </a>
            </div>
        </section>

        <?php
        $overviewParticipantRows = $analysisCohortParticipants;
        $overviewTaskRows = $analysisCohortTaskRows;
        ?>
        <section class="bg-white shadow rounded-xl p-6 mb-6">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Participant-Level Analysis View</h2>
            <p class="text-xs text-slate-500 mb-3">Shows one row per fully completed participant (2 finished tasks + completed survey). Currently showing <?= e((string) count($overviewParticipantRows)) ?> rows.</p>
            <?php if (empty($overviewParticipantRows)): ?>
                <p class="text-sm text-slate-600">No fully completed participant rows found.</p>
            <?php else: ?>
                <?php $participantAnalysisColumns = array_keys($overviewParticipantRows[0]); ?>
                <div class="h-96 overflow-auto rounded-lg border border-slate-200">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-slate-600">
                                <?php foreach ($participantAnalysisColumns as $column): ?>
                                    <th class="sticky top-0 z-10 bg-white text-left py-2 pr-3 font-semibold whitespace-nowrap"><?= e((string) $column) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($overviewParticipantRows as $row): ?>
                                <tr class="border-b border-slate-100 odd:bg-slate-50 last:border-b-0">
                                    <?php foreach ($participantAnalysisColumns as $column): ?>
                                        <?php $rawValue = $row[$column] ?? null; ?>
                                        <td class="py-2 pr-3 text-slate-700 align-top whitespace-nowrap"><?= e($rawValue === null ? '' : (string) $rawValue) ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="bg-white shadow rounded-xl p-6 mb-6">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Task-Level Analysis View</h2>
            <p class="text-xs text-slate-500 mb-3">Shows one row per task from fully completed participants only. Currently showing <?= e((string) count($overviewTaskRows)) ?> rows.</p>
            <?php if (empty($overviewTaskRows)): ?>
                <p class="text-sm text-slate-600">No fully completed task rows found.</p>
            <?php else: ?>
                <?php
                $taskAnalysisColumns = array_values(array_filter(
                    array_keys($overviewTaskRows[0]),
                    static fn (string $column): bool => $column !== 'active_reflection'
                ));
                ?>
                <div class="h-96 overflow-auto rounded-lg border border-slate-200">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-slate-600">
                                <?php foreach ($taskAnalysisColumns as $column): ?>
                                    <th class="sticky top-0 z-10 bg-white text-left py-2 pr-3 font-semibold whitespace-nowrap"><?= e((string) $column) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($overviewTaskRows as $row): ?>
                                <tr class="border-b border-slate-100 odd:bg-slate-50 last:border-b-0">
                                    <?php foreach ($taskAnalysisColumns as $column): ?>
                                        <?php
                                        $rawValue = $row[$column] ?? null;
                                        $displayValue = $rawValue === null ? '' : (string) $rawValue;
                                        ?>
                                        <td class="py-2 pr-3 text-slate-700 align-top whitespace-nowrap"><?= e($displayValue) ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section id="other-coding" class="bg-white shadow rounded-xl p-6 mb-6 overflow-x-auto">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Code Uncoded “Other” Responses</h2>
            <?php if (empty($uncodedOtherRows)): ?>
                <p class="text-sm text-slate-600">No uncoded “Other” responses found.</p>
            <?php else: ?>
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-600">
                            <th class="text-left py-2 pr-3">Participant</th>
                            <th class="text-left py-2 pr-3">Condition</th>
                            <th class="text-left py-2 pr-3">Task</th>
                            <th class="text-left py-2 pr-3">Response text</th>
                            <th class="text-left py-2 pr-3">Verification intention</th>
                            <th class="text-left py-2 pr-3">Code</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($uncodedOtherRows as $uncodedRow): ?>
                            <?php
                            $responseText = trim((string) ($uncodedRow['custom_response_text'] ?? ''));
                            if ($responseText === '') {
                                $responseText = trim((string) ($uncodedRow['final_response'] ?? ''));
                            }
                            if ($responseText === '') {
                                $responseText = '(empty response)';
                            }
                            ?>
                            <tr class="border-b border-slate-100 odd:bg-slate-50 last:border-b-0">
                                <td class="py-2 pr-3 text-slate-700 whitespace-nowrap">
                                    <?= e((string) $uncodedRow['participant_id']) ?>
                                    <?php if ((string) $uncodedRow['participant_code'] !== ''): ?>
                                        <span class="text-slate-500">(<?= e((string) $uncodedRow['participant_code']) ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2 pr-3 text-slate-700 whitespace-nowrap"><?= e((string) $uncodedRow['condition_name']) ?></td>
                                <td class="py-2 pr-3 text-slate-700 whitespace-nowrap"><?= e((string) $uncodedRow['task_number']) ?></td>
                                <td class="py-2 pr-3 text-slate-700 max-w-lg whitespace-normal break-words"><?= e($responseText) ?></td>
                                <td class="py-2 pr-3 text-slate-700 max-w-xs whitespace-normal break-words"><?= e((string) $uncodedRow['verification_intention']) ?></td>
                                <td class="py-2 pr-3 text-slate-700 whitespace-nowrap">
                                    <form method="post" action="/dashboard/" class="flex items-center gap-2">
                                        <input type="hidden" name="dashboard_action" value="code_other_response">
                                        <input type="hidden" name="csrf_token" value="<?= e($dashboardCsrfToken) ?>">
                                        <input type="hidden" name="participant_id" value="<?= e((string) $uncodedRow['participant_id']) ?>">
                                        <input type="hidden" name="task_number" value="<?= e((string) $uncodedRow['task_number']) ?>">
                                        <input type="hidden" name="return_url" value="/dashboard/?tab=overview#other-coding">
                                        <select name="manual_response_correctness" class="rounded border border-slate-300 px-2 py-1 text-sm" required>
                                            <option value="" selected disabled>Choose...</option>
                                            <option value="1">Correct (1)</option>
                                            <option value="0">Incorrect (0)</option>
                                        </select>
                                        <button
                                            type="submit"
                                            class="text-sm bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-1 rounded border border-slate-300"
                                        >
                                            Save
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <section class="bg-white shadow rounded-xl p-6 mb-6 overflow-x-auto">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Condition Comparison</h2>
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-600">
                        <th class="text-left py-2 pr-4">Condition</th>
                        <th class="text-right py-2 px-2">Full surveys (N)</th>
                        <th class="text-right py-2 px-2">Avg correct (%)</th>
                        <th class="text-right py-2 px-2">Avg docs opened</th>
                        <th class="text-right py-2 px-2">N no doc opened at all</th>
                        <th class="text-right py-2 px-2">Avg total doc time (s)</th>
                        <th class="text-right py-2 px-2">Avg task duration (s)</th>
                        <th class="text-right py-2 px-2">Avg total survey duration (s)</th>
                        <th class="text-right py-2 px-2">Avg confidence</th>
                        <th class="text-right py-2 pl-2">Relevant doc open rate (%)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($conditionResults as $condition => $stats): ?>
                        <?php
                        $fullSurveys = (int) ($stats['n_completed'] ?? 0);
                        $avgCorrectPct = ($stats['correct_pct_count'] ?? 0) > 0
                            ? ($stats['correct_pct_sum'] / $stats['correct_pct_count'])
                            : 0.0;
                        $avgDocsOpened = ($stats['avg_docs_opened_count'] ?? 0) > 0
                            ? ($stats['avg_docs_opened_sum'] / $stats['avg_docs_opened_count'])
                            : 0.0;
                        $noDocOpenedCount = (int) ($stats['no_doc_open_count'] ?? 0);
                        $avgTotalDocTime = ($stats['avg_total_doc_time_count'] ?? 0) > 0
                            ? ($stats['avg_total_doc_time_sum'] / $stats['avg_total_doc_time_count'])
                            : 0.0;
                        $avgTaskDuration = ($stats['avg_task_duration_count'] ?? 0) > 0
                            ? ($stats['avg_task_duration_sum'] / $stats['avg_task_duration_count'])
                            : 0.0;
                        $avgTotalSurveyDuration = ($stats['avg_total_survey_duration_count'] ?? 0) > 0
                            ? ($stats['avg_total_survey_duration_sum'] / $stats['avg_total_survey_duration_count'])
                            : 0.0;
                        $avgConfidence = ($stats['avg_confidence_count'] ?? 0) > 0
                            ? ($stats['avg_confidence_sum'] / $stats['avg_confidence_count'])
                            : 0.0;
                        $relevantRatePct = ($stats['relevant_rate_count'] ?? 0) > 0
                            ? (($stats['relevant_rate_sum'] / $stats['relevant_rate_count']) * 100.0)
                            : 0.0;
                        ?>
                        <tr class="border-b border-slate-100 odd:bg-slate-50 last:border-b-0">
                            <td class="py-2 pr-4 font-medium text-slate-800"><?= e((string) $condition) ?></td>
                            <td class="py-2 px-2 text-right text-slate-700"><?= e((string) $fullSurveys) ?></td>
                            <td class="py-2 px-2 text-right text-slate-700"><?= e(number_format($avgCorrectPct, 1)) ?>%</td>
                            <td class="py-2 px-2 text-right text-slate-700"><?= e(number_format($avgDocsOpened, 2)) ?></td>
                            <td class="py-2 px-2 text-right text-slate-700"><?= e((string) $noDocOpenedCount) ?></td>
                            <td class="py-2 px-2 text-right text-slate-700"><?= e(number_format($avgTotalDocTime, 1)) ?></td>
                            <td class="py-2 px-2 text-right text-slate-700"><?= e(number_format($avgTaskDuration, 1)) ?></td>
                            <td class="py-2 px-2 text-right text-slate-700"><?= e(number_format($avgTotalSurveyDuration, 1)) ?></td>
                            <td class="py-2 px-2 text-right text-slate-700"><?= e(number_format($avgConfidence, 2)) ?></td>
                            <td class="py-2 pl-2 text-right text-slate-700"><?= e(number_format($relevantRatePct, 1)) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="bg-white shadow rounded-xl p-6 mb-6 overflow-x-auto">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Condition × AI Correctness</h2>
            <p class="text-xs text-slate-500 mb-3">Calculated from fully completed participants only; percentages use coded final decisions.</p>
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-600">
                        <th class="text-left py-2 pr-4">Condition</th>
                        <th class="text-right py-2 px-2">AI correct tasks (n)</th>
                        <th class="text-right py-2 px-2">AI correct final decision (%)</th>
                        <th class="text-right py-2 px-2">AI incorrect tasks (n)</th>
                        <th class="text-right py-2 pl-2">AI incorrect final decision (%)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($analysisConditionAiBuckets as $condition => $aiBucket): ?>
                        <?php
                        $aiCorrectN = (int) ($aiBucket[1]['n'] ?? 0);
                        $aiCorrectPct = $aiCorrectN > 0
                            ? (((int) ($aiBucket[1]['correct'] ?? 0)) / $aiCorrectN) * 100.0
                            : 0.0;
                        $aiIncorrectN = (int) ($aiBucket[0]['n'] ?? 0);
                        $aiIncorrectPct = $aiIncorrectN > 0
                            ? (((int) ($aiBucket[0]['correct'] ?? 0)) / $aiIncorrectN) * 100.0
                            : 0.0;
                        ?>
                        <tr class="border-b border-slate-100 odd:bg-slate-50 last:border-b-0">
                            <td class="py-2 pr-4 font-medium text-slate-800"><?= e((string) $condition) ?></td>
                            <td class="py-2 px-2 text-right text-slate-700"><?= e((string) $aiCorrectN) ?></td>
                            <td class="py-2 px-2 text-right text-slate-700"><?= e(number_format($aiCorrectPct, 1)) ?>%</td>
                            <td class="py-2 px-2 text-right text-slate-700"><?= e((string) $aiIncorrectN) ?></td>
                            <td class="py-2 pl-2 text-right text-slate-700"><?= e(number_format($aiIncorrectPct, 1)) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="bg-white shadow rounded-xl p-6 mb-6 overflow-x-auto">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Low-quality responses</h2>
            <p class="text-xs text-slate-500 mb-3">Flagged when serious_effort &le; 2 or short_time_flag = 1 on both tasks. Showing <?= e((string) $lowQualityCount) ?> participants.</p>
            <?php if (empty($lowQualityRows)): ?>
                <p class="text-sm text-slate-600">No low-quality responses found in the analysis cohort.</p>
            <?php else: ?>
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-600">
                            <th class="text-right py-2 pr-3">participant_id</th>
                            <th class="text-left py-2 pr-3">participant_code</th>
                            <th class="text-left py-2 pr-3">condition_name</th>
                            <th class="text-right py-2 px-2">serious_effort</th>
                            <th class="text-right py-2 px-2">short_time_flags</th>
                            <th class="text-right py-2 px-2">low_effort_flag</th>
                            <th class="text-left py-2 pl-2">reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lowQualityRows as $row): ?>
                            <tr class="border-b border-slate-100 odd:bg-slate-50 last:border-b-0">
                                <td class="py-2 pr-3 text-right">
                                    <a href="/dashboard/?tab=participant&participant_id=<?= e((string) $row['participant_id']) ?><?= e($includeTestParticipants ? '&include_test=1' : '') ?>" class="accent-text hover:underline font-medium">
                                        <?= e((string) $row['participant_id']) ?>
                                    </a>
                                </td>
                                <td class="py-2 pr-3"><?= e((string) $row['participant_code']) ?></td>
                                <td class="py-2 pr-3"><?= e((string) $row['condition_name']) ?></td>
                                <td class="py-2 px-2 text-right"><?= $row['serious_effort'] === null ? '' : e((string) $row['serious_effort']) ?></td>
                                <td class="py-2 px-2 text-right"><?= e((string) $row['short_flags']) ?>/<?= e((string) $row['tasks_count']) ?></td>
                                <td class="py-2 px-2 text-right"><?= e((string) $row['low_effort_flag']) ?></td>
                                <td class="py-2 pl-2"><?= e((string) $row['reason']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

    <?php elseif ($currentTab === 'condition_results'): ?>
        <section class="bg-white shadow rounded-xl p-6 mb-6 overflow-x-auto">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Condition Comparison (Analysis Cohort)</h2>
            <p class="text-xs text-slate-500 mb-3">
                Cohort filter: completed participants with tasks_completed=2 and post-survey available.
            </p>
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-600">
                        <th class="text-left py-2 pr-3">condition_name</th>
                        <th class="text-right py-2 px-2">N completed</th>
                        <th class="text-right py-2 px-2">mean correct_count (out of 2)</th>
                        <th class="text-right py-2 px-2">correct_pct</th>
                        <th class="text-right py-2 px-2">% with 2/2 correct</th>
                        <th class="text-right py-2 px-2">relevant_doc_open_rate</th>
                        <th class="text-right py-2 px-2">avg_docs_opened</th>
                        <th class="text-right py-2 px-2">avg_total_doc_time_sec</th>
                        <th class="text-right py-2 px-2">avg_relevant_doc_time_sec</th>
                        <th class="text-right py-2 pl-2">avg_confidence</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($conditionResults as $condition => $stats): ?>
                        <?php
                        $n = (int) $stats['n_completed'];
                        $meanCorrectCount = $n > 0 ? ($stats['correct_count_sum'] / $n) : 0.0;
                        $correctPct = $stats['correct_pct_count'] > 0 ? ($stats['correct_pct_sum'] / $stats['correct_pct_count']) : 0.0;
                        $twoOfTwoPct = $n > 0 ? ((float) $stats['two_of_two'] / $n) * 100.0 : 0.0;
                        $relevantRatePct = $stats['relevant_rate_count'] > 0 ? (($stats['relevant_rate_sum'] / $stats['relevant_rate_count']) * 100.0) : 0.0;
                        $avgDocs = $stats['avg_docs_opened_count'] > 0 ? ($stats['avg_docs_opened_sum'] / $stats['avg_docs_opened_count']) : 0.0;
                        $avgTotalTime = $stats['avg_total_doc_time_count'] > 0 ? ($stats['avg_total_doc_time_sum'] / $stats['avg_total_doc_time_count']) : 0.0;
                        $avgRelevantTime = $stats['avg_relevant_doc_time_count'] > 0 ? ($stats['avg_relevant_doc_time_sum'] / $stats['avg_relevant_doc_time_count']) : 0.0;
                        $avgConf = $stats['avg_confidence_count'] > 0 ? ($stats['avg_confidence_sum'] / $stats['avg_confidence_count']) : 0.0;
                        ?>
                        <tr class="border-b border-slate-100 odd:bg-slate-50 last:border-b-0">
                            <td class="py-2 pr-3 font-medium text-slate-800"><?= e((string) $condition) ?></td>
                            <td class="py-2 px-2 text-right text-slate-700"><?= e((string) $n) ?></td>
                            <td class="py-2 px-2 text-right text-slate-700"><?= e(number_format($meanCorrectCount, 2)) ?></td>
                            <td class="py-2 px-2 text-right text-slate-700"><?= e(number_format($correctPct, 1)) ?>%</td>
                            <td class="py-2 px-2 text-right text-slate-700"><?= e(number_format($twoOfTwoPct, 1)) ?>%</td>
                            <td class="py-2 px-2 text-right text-slate-700"><?= e(number_format($relevantRatePct, 1)) ?>%</td>
                            <td class="py-2 px-2 text-right text-slate-700"><?= e(number_format($avgDocs, 2)) ?></td>
                            <td class="py-2 px-2 text-right text-slate-700"><?= e(number_format($avgTotalTime, 2)) ?></td>
                            <td class="py-2 px-2 text-right text-slate-700"><?= e(number_format($avgRelevantTime, 2)) ?></td>
                            <td class="py-2 pl-2 text-right text-slate-700"><?= e(number_format($avgConf, 2)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
        <section class="bg-white shadow rounded-xl p-6 mb-6 overflow-x-auto">
            <h3 class="text-base font-semibold text-slate-800 mb-3">Task-level correctness by condition</h3>
            <p class="text-xs text-slate-500 mb-3">Percent correct is calculated separately for Task 1 and Task 2 within each condition (analysis cohort only).</p>
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-600">
                        <th class="text-left py-2 pr-3">condition_name</th>
                        <th class="text-right py-2 px-2">% task 1 correct</th>
                        <th class="text-right py-2 pl-2">% task 2 correct</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($conditionResults as $condition => $stats): ?>
                        <?php
                        $task1CorrectPct = ($stats['task_1_correct_count'] ?? 0) > 0
                            ? (($stats['task_1_correct_sum'] / $stats['task_1_correct_count']) * 100.0)
                            : 0.0;
                        $task2CorrectPct = ($stats['task_2_correct_count'] ?? 0) > 0
                            ? (($stats['task_2_correct_sum'] / $stats['task_2_correct_count']) * 100.0)
                            : 0.0;
                        ?>
                        <tr class="border-b border-slate-100 odd:bg-slate-50 last:border-b-0">
                            <td class="py-2 pr-3 font-medium text-slate-800"><?= e((string) $condition) ?></td>
                            <td class="py-2 px-2 text-right text-slate-700"><?= e(number_format($task1CorrectPct, 1)) ?>%</td>
                            <td class="py-2 pl-2 text-right text-slate-700"><?= e(number_format($task2CorrectPct, 1)) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
        <section class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <article class="bg-white shadow rounded-xl p-6">
                <h3 class="text-base font-semibold text-slate-800 mb-3">Mean correct_count by condition</h3>
                <?php $maxMeanCorrect = 1.0; foreach ($conditionResults as $stats) { $maxMeanCorrect = max($maxMeanCorrect, (float) (($stats['n_completed'] ?? 0) > 0 ? ($stats['correct_count_sum'] / $stats['n_completed']) : 0.0)); } ?>
                <div class="space-y-2.5">
                    <?php foreach ($conditionResults as $condition => $stats): ?>
                        <?php $val = (float) (($stats['n_completed'] ?? 0) > 0 ? ($stats['correct_count_sum'] / $stats['n_completed']) : 0.0); $w = ($val / $maxMeanCorrect) * 100.0; ?>
                        <div>
                            <div class="flex items-center justify-between text-sm mb-1"><span><?= e((string) $condition) ?></span><span><?= e(number_format($val, 2)) ?></span></div>
                            <div class="w-full h-3 bg-slate-100 rounded"><div class="h-3 accent-bg rounded" style="width: <?= e(number_format($w, 2, '.', '')) ?>%"></div></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>
            <article class="bg-white shadow rounded-xl p-6">
                <h3 class="text-base font-semibold text-slate-800 mb-3">Relevant doc open rate by condition</h3>
                <div class="space-y-3">
                    <?php foreach ($conditionResults as $condition => $stats): ?>
                        <?php $val = (float) (($stats['relevant_rate_count'] ?? 0) > 0 ? (($stats['relevant_rate_sum'] / $stats['relevant_rate_count']) * 100.0) : 0.0); ?>
                        <div>
                            <div class="flex items-center justify-between text-sm mb-1"><span><?= e((string) $condition) ?></span><span><?= e(number_format($val, 1)) ?>%</span></div>
                            <div class="w-full h-3 bg-slate-100 rounded"><div class="h-3 accent-bg rounded" style="width: <?= e(number_format($val, 2, '.', '')) ?>%"></div></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>
        </section>
        <section class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <article class="bg-white shadow rounded-xl p-6">
                <h3 class="text-base font-semibold text-slate-800 mb-3">Avg total survey duration by condition</h3>
                <?php
                $maxTotalSurveyDuration = 1.0;
                foreach ($conditionResults as $stats) {
                    $value = ($stats['avg_total_survey_duration_count'] ?? 0) > 0
                        ? ($stats['avg_total_survey_duration_sum'] / $stats['avg_total_survey_duration_count'])
                        : 0.0;
                    $maxTotalSurveyDuration = max($maxTotalSurveyDuration, (float) $value);
                }
                ?>
                <div class="space-y-3">
                    <?php foreach ($conditionResults as $condition => $stats): ?>
                        <?php
                        $avgTotalSurveyDuration = ($stats['avg_total_survey_duration_count'] ?? 0) > 0
                            ? ($stats['avg_total_survey_duration_sum'] / $stats['avg_total_survey_duration_count'])
                            : 0.0;
                        $barWidth = ($avgTotalSurveyDuration / $maxTotalSurveyDuration) * 100.0;
                        ?>
                        <div>
                            <div class="flex items-center justify-between text-sm mb-1">
                                <span class="font-medium text-slate-700"><?= e((string) $condition) ?></span>
                                <span class="text-slate-700 tabular-nums"><?= e(number_format($avgTotalSurveyDuration, 1)) ?>s</span>
                            </div>
                            <div class="w-full h-3 bg-slate-100 rounded">
                                <div class="h-3 accent-bg rounded" style="width: <?= e(number_format($barWidth, 2, '.', '')) ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>
            <article class="bg-white shadow rounded-xl p-6">
                <h3 class="text-base font-semibold text-slate-800 mb-3">Avg task duration by condition</h3>
                <?php
                $maxTaskDuration = 1.0;
                foreach ($conditionResults as $stats) {
                    $task1Val = ($stats['task1_duration_count'] ?? 0) > 0
                        ? ($stats['task1_duration_sum'] / $stats['task1_duration_count'])
                        : 0.0;
                    $task2Val = ($stats['task2_duration_count'] ?? 0) > 0
                        ? ($stats['task2_duration_sum'] / $stats['task2_duration_count'])
                        : 0.0;
                    $maxTaskDuration = max($maxTaskDuration, (float) $task1Val, (float) $task2Val);
                }
                ?>
                <div class="space-y-3">
                    <?php foreach ($conditionResults as $condition => $stats): ?>
                        <?php
                        $avgTask1Duration = ($stats['task1_duration_count'] ?? 0) > 0
                            ? ($stats['task1_duration_sum'] / $stats['task1_duration_count'])
                            : 0.0;
                        $avgTask2Duration = ($stats['task2_duration_count'] ?? 0) > 0
                            ? ($stats['task2_duration_sum'] / $stats['task2_duration_count'])
                            : 0.0;
                        $task1Width = ($avgTask1Duration / $maxTaskDuration) * 100.0;
                        $task2Width = ($avgTask2Duration / $maxTaskDuration) * 100.0;
                        ?>
                        <div>
                            <div class="flex items-center justify-between text-sm mb-1">
                                <span class="font-medium text-slate-700"><?= e((string) $condition) ?></span>
                            </div>
                            <div class="grid grid-cols-2 gap-1.5">
                                <div>
                                    <div class="flex items-center justify-between text-[10px] text-slate-500 mb-0.5">
                                        <span>T1</span>
                                        <span class="tabular-nums"><?= e(number_format($avgTask1Duration, 1)) ?>s</span>
                                    </div>
                                    <div class="w-full h-1.5 bg-slate-200 rounded">
                                        <div class="h-1.5 accent-bg rounded" style="width: <?= e(number_format($task1Width, 2, '.', '')) ?>%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex items-center justify-between text-[10px] text-slate-500 mb-0.5">
                                        <span>T2</span>
                                        <span class="tabular-nums"><?= e(number_format($avgTask2Duration, 1)) ?>s</span>
                                    </div>
                                    <div class="w-full h-1.5 bg-slate-200 rounded">
                                        <div class="h-1.5 bg-slate-500 rounded" style="width: <?= e(number_format($task2Width, 2, '.', '')) ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>
        </section>
        <section class="bg-white shadow rounded-xl p-6 mb-6 overflow-x-auto">
            <h3 class="text-base font-semibold text-slate-800 mb-3">Distribution of correct_count (0/1/2) by condition</h3>
            <table class="min-w-full text-sm">
                <thead><tr class="border-b border-slate-200 text-slate-600"><th class="text-left py-2 pr-3">condition</th><th class="text-right py-2 px-2">0</th><th class="text-right py-2 px-2">1</th><th class="text-right py-2 pl-2">2</th></tr></thead>
                <tbody>
                    <?php foreach ($correctDistByCondition as $condition => $dist): ?>
                        <tr class="border-b border-slate-100 odd:bg-slate-50 last:border-b-0"><td class="py-2 pr-3"><?= e((string) $condition) ?></td><td class="py-2 px-2 text-right"><?= e((string) ($dist['0'] ?? 0)) ?></td><td class="py-2 px-2 text-right"><?= e((string) ($dist['1'] ?? 0)) ?></td><td class="py-2 pl-2 text-right"><?= e((string) ($dist['2'] ?? 0)) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
        <section class="bg-white shadow rounded-xl p-6 mb-6 overflow-x-auto">
            <h3 class="text-base font-semibold text-slate-800 mb-3">Distribution of relevant_doc_open_rate (0/50/100) by condition</h3>
            <table class="min-w-full text-sm">
                <thead><tr class="border-b border-slate-200 text-slate-600"><th class="text-left py-2 pr-3">condition</th><th class="text-right py-2 px-2">0%</th><th class="text-right py-2 px-2">50%</th><th class="text-right py-2 pl-2">100%</th></tr></thead>
                <tbody>
                    <?php foreach ($relevantDistByCondition as $condition => $dist): ?>
                        <tr class="border-b border-slate-100 odd:bg-slate-50 last:border-b-0"><td class="py-2 pr-3"><?= e((string) $condition) ?></td><td class="py-2 px-2 text-right"><?= e((string) ($dist['0'] ?? 0)) ?></td><td class="py-2 px-2 text-right"><?= e((string) ($dist['50'] ?? 0)) ?></td><td class="py-2 pl-2 text-right"><?= e((string) ($dist['100'] ?? 0)) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php elseif ($currentTab === 'calibration'): ?>
        <section class="bg-white shadow rounded-xl p-6 mb-6 overflow-x-auto">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Condition × AI Correctness</h2>
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-600">
                        <th class="text-left py-2 pr-3">condition_name</th>
                        <th class="text-right py-2 px-2">ai_correct</th>
                        <th class="text-right py-2 px-2">N task responses</th>
                        <th class="text-right py-2 px-2">mean final_decision_correct</th>
                        <th class="text-right py-2 px-2">relevant_document_opened rate</th>
                        <th class="text-right py-2 px-2">avg confidence</th>
                        <th class="text-right py-2 px-2">avg relevant document view time</th>
                        <th class="text-right py-2 px-2">overreliance_error_rate (ai incorrect)</th>
                        <th class="text-right py-2 pl-2">underreliance_or_too_strict_error_rate (ai correct)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($calibrationRows as $row): ?>
                        <?php
                        $meanFinal = $row['final_correct_count'] > 0 ? ($row['final_correct_sum'] / $row['final_correct_count']) : 0.0;
                        $relevantRate = $row['relevant_open_count'] > 0 ? ($row['relevant_open_sum'] / $row['relevant_open_count']) : 0.0;
                        $avgConf = $row['confidence_count'] > 0 ? ($row['confidence_sum'] / $row['confidence_count']) : 0.0;
                        $avgRelevantTime = $row['relevant_time_count'] > 0 ? ($row['relevant_time_sum'] / $row['relevant_time_count']) : 0.0;
                        $overRate = $row['overreliance_count'] > 0 ? ($row['overreliance_sum'] / $row['overreliance_count']) : null;
                        $underRate = $row['underreliance_count'] > 0 ? ($row['underreliance_sum'] / $row['underreliance_count']) : null;
                        ?>
                        <tr class="border-b border-slate-100 odd:bg-slate-50 last:border-b-0">
                            <td class="py-2 pr-3"><?= e((string) $row['condition_name']) ?></td>
                            <td class="py-2 px-2 text-right"><?= e((string) $row['ai_correct']) ?></td>
                            <td class="py-2 px-2 text-right"><?= e((string) $row['n']) ?></td>
                            <td class="py-2 px-2 text-right"><?= e(number_format($meanFinal, 3)) ?></td>
                            <td class="py-2 px-2 text-right"><?= e(number_format($relevantRate * 100.0, 1)) ?>%</td>
                            <td class="py-2 px-2 text-right"><?= e(number_format($avgConf, 2)) ?></td>
                            <td class="py-2 px-2 text-right"><?= e(number_format($avgRelevantTime, 2)) ?></td>
                            <td class="py-2 px-2 text-right"><?= $overRate === null ? '-' : e(number_format($overRate * 100.0, 1)) . '%' ?></td>
                            <td class="py-2 pl-2 text-right"><?= $underRate === null ? '-' : e(number_format($underRate * 100.0, 1)) . '%' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php elseif ($currentTab === 'inspection'): ?>
        <section class="bg-white shadow rounded-xl p-6 mb-6 overflow-x-auto">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Inspection Behavior by Condition</h2>
            <table class="min-w-full text-sm">
                <thead><tr class="border-b border-slate-200 text-slate-600"><th class="text-left py-2 pr-3">condition</th><th class="text-right py-2 px-2">opened any rate</th><th class="text-right py-2 px-2">relevant opened rate</th><th class="text-right py-2 px-2">opened all docs rate</th><th class="text-right py-2 px-2">avg number_documents_opened</th><th class="text-right py-2 px-2">avg total_document_view_time_sec</th><th class="text-right py-2 pl-2">avg relevant_document_view_time_sec</th></tr></thead>
                <tbody>
                    <?php foreach ($inspectionRows as $condition => $row): ?>
                        <?php
                        $openedAny = $row['inspection_any_count'] > 0 ? ($row['inspection_any_sum'] / $row['inspection_any_count']) : 0.0;
                        $openedRelevant = $row['inspection_relevant_count'] > 0 ? ($row['inspection_relevant_sum'] / $row['inspection_relevant_count']) : 0.0;
                        $openedAll = $row['opened_all_count'] > 0 ? ($row['opened_all_sum'] / $row['opened_all_count']) : 0.0;
                        $avgDocs = $row['docs_opened_count'] > 0 ? ($row['docs_opened_sum'] / $row['docs_opened_count']) : 0.0;
                        $avgTotalTime = $row['total_time_count'] > 0 ? ($row['total_time_sum'] / $row['total_time_count']) : 0.0;
                        $avgRelevantTime = $row['relevant_time_count'] > 0 ? ($row['relevant_time_sum'] / $row['relevant_time_count']) : 0.0;
                        ?>
                        <tr class="border-b border-slate-100 odd:bg-slate-50 last:border-b-0">
                            <td class="py-2 pr-3"><?= e((string) $condition) ?></td>
                            <td class="py-2 px-2 text-right"><?= e(number_format($openedAny * 100.0, 1)) ?>%</td>
                            <td class="py-2 px-2 text-right"><?= e(number_format($openedRelevant * 100.0, 1)) ?>%</td>
                            <td class="py-2 px-2 text-right"><?= e(number_format($openedAll * 100.0, 1)) ?>%</td>
                            <td class="py-2 px-2 text-right"><?= e(number_format($avgDocs, 2)) ?></td>
                            <td class="py-2 px-2 text-right"><?= e(number_format($avgTotalTime, 2)) ?></td>
                            <td class="py-2 pl-2 text-right"><?= e(number_format($avgRelevantTime, 2)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
        <section class="bg-white shadow rounded-xl p-6 mb-6">
            <h3 class="text-base font-semibold text-slate-800 mb-3">Document inspection by condition (opened any rate)</h3>
            <div class="space-y-3">
                <?php foreach ($inspectionRows as $condition => $row): ?>
                    <?php $openedAnyPct = $row['inspection_any_count'] > 0 ? ($row['inspection_any_sum'] / $row['inspection_any_count']) * 100.0 : 0.0; ?>
                    <div>
                        <div class="flex items-center justify-between text-sm mb-1"><span><?= e((string) $condition) ?></span><span><?= e(number_format($openedAnyPct, 1)) ?>%</span></div>
                        <div class="w-full h-3 bg-slate-100 rounded"><div class="h-3 accent-bg rounded" style="width: <?= e(number_format($openedAnyPct, 2, '.', '')) ?>%"></div></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php elseif ($currentTab === 'participants_analysis'): ?>
        <section class="bg-white shadow rounded-xl p-6 mb-6 overflow-x-auto">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Participants (analysis_participant_summary)</h2>
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-600">
                        <th class="text-right py-2 pr-3">participant_id</th>
                        <th class="text-left py-2 pr-3">participant_code</th>
                        <th class="text-left py-2 pr-3">condition_name</th>
                        <th class="text-left py-2 pr-3">completed_at</th>
                        <th class="text-right py-2 px-2">tasks_completed</th>
                        <th class="text-right py-2 px-2">correct_count</th>
                        <th class="text-right py-2 px-2">correct_pct</th>
                        <th class="text-right py-2 px-2">relevant_doc_open_rate</th>
                        <th class="text-right py-2 px-2">avg_confidence</th>
                        <th class="text-right py-2 px-2">ai_literacy_score</th>
                        <th class="text-right py-2 px-2">crt_score</th>
                        <th class="text-right py-2 px-2">serious_effort</th>
                        <th class="text-right py-2 px-2">low_quality_response</th>
                        <th class="text-right py-2 pl-2">uncoded_other_count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($analysisCohortParticipants as $row): ?>
                        <?php $participantId = (int) ($row['participant_id'] ?? 0); ?>
                        <tr class="border-b border-slate-100 odd:bg-slate-50 last:border-b-0">
                            <td class="py-2 pr-3 text-right">
                                <?php if ($participantId > 0): ?>
                                    <a href="/dashboard/?tab=participant&participant_id=<?= e((string) $participantId) ?><?= e($includeTestParticipants ? '&include_test=1' : '') ?>" class="accent-text hover:underline font-medium">
                                        <?= e((string) $participantId) ?>
                                    </a>
                                <?php else: ?>
                                    <?= e((string) $participantId) ?>
                                <?php endif; ?>
                            </td>
                            <td class="py-2 pr-3"><?= e((string) ($row['participant_code'] ?? '')) ?></td>
                            <td class="py-2 pr-3"><?= e((string) ($row['condition_name'] ?? '')) ?></td>
                            <td class="py-2 pr-3"><?= e(format_dashboard_datetime((string) ($row['completed_at'] ?? ''))) ?></td>
                            <td class="py-2 px-2 text-right"><?= e((string) ($row['tasks_completed'] ?? '')) ?></td>
                            <td class="py-2 px-2 text-right"><?= e((string) ($row['correct_count'] ?? '')) ?></td>
                            <td class="py-2 px-2 text-right"><?= e(number_format((float) ($row['correct_pct'] ?? 0), 1)) ?>%</td>
                            <td class="py-2 px-2 text-right"><?= e(number_format(((float) ($row['relevant_doc_open_rate'] ?? 0)) * 100.0, 1)) ?>%</td>
                            <td class="py-2 px-2 text-right"><?= e(number_format((float) ($row['avg_confidence'] ?? 0), 2)) ?></td>
                            <td class="py-2 px-2 text-right"><?= e(number_format((float) ($row['ai_literacy_score'] ?? 0), 2)) ?></td>
                            <td class="py-2 px-2 text-right"><?= e((string) ($row['crt_score'] ?? '')) ?></td>
                            <td class="py-2 px-2 text-right"><?= e((string) ($row['serious_effort'] ?? '')) ?></td>
                            <td class="py-2 px-2 text-right"><?= e((string) ($row['low_quality_response'] ?? '')) ?></td>
                            <td class="py-2 pl-2 text-right"><?= e((string) ($participantUncodedOtherCount[$participantId] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php elseif ($currentTab === 'task_level_analysis' || $currentTab === 'task_level_analysis_all'): ?>
        <?php
        $isTaskLevelAllTab = $currentTab === 'task_level_analysis_all';
        $taskLevelDisplayRows = $isTaskLevelAllTab
            ? $analysisTaskLevelRows
            : $analysisCohortTaskRows;
        $taskLevelDisplayTitle = $isTaskLevelAllTab
            ? 'Task-Level Data (all entries)'
            : 'Task-Level Data (analysis_task_level)';
        $taskLevelDisplayDescription = $isTaskLevelAllTab
            ? 'All task response rows, including participants who did not fully complete the study. finished_survey = 1 when tasks_completed = 2, post-survey submitted, and completed_at is set.'
            : 'Task response rows for fully completed analysis cohort participants only.';
        ?>
        <section class="bg-white shadow rounded-xl p-6 mb-6 overflow-x-auto">
            <div class="flex items-center justify-between gap-3 mb-2">
                <h2 class="text-lg font-semibold text-slate-800"><?= e($taskLevelDisplayTitle) ?></h2>
                <a
                    href="/dashboard/?tab=<?= e($currentTab) ?>&download=1<?= e($includeTestParticipants ? '&include_test=1' : '') ?>"
                    class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-4 py-2 rounded-lg transition"
                >
                    Download CSV
                </a>
            </div>
            <p class="text-sm text-slate-600 mb-4"><?= e($taskLevelDisplayDescription) ?></p>
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-600">
                        <th class="text-right py-2 pr-3">participant_id</th>
                        <th class="text-left py-2 pr-3">participant_code</th>
                        <th class="text-left py-2 pr-3">condition_name</th>
                        <th class="text-left py-2 pr-3">prolific</th>
                        <?php if ($isTaskLevelAllTab): ?>
                            <th class="text-right py-2 px-2">finished_survey</th>
                        <?php endif; ?>
                        <th class="text-right py-2 px-2">task_number</th>
                        <th class="text-right py-2 px-2">ai_correct</th>
                        <th class="text-left py-2 px-2">selected_option_key</th>
                        <th class="text-right py-2 px-2">final_decision_correct</th>
                        <th class="text-right py-2 px-2">any_doc_opened</th>
                        <th class="text-right py-2 px-2">relevant_document_opened</th>
                        <th class="text-right py-2 px-2">number_documents_opened</th>
                        <th class="text-right py-2 px-2">total_document_view_time_sec</th>
                        <th class="text-right py-2 px-2">relevant_document_view_time_sec</th>
                        <th class="text-right py-2 px-2">confidence</th>
                        <th class="text-right py-2 px-2">manual_code_required</th>
                        <th class="text-right py-2 px-2">manual_response_correctness</th>
                        <th class="text-right py-2 px-2">ai_literacy_score</th>
                        <th class="text-right py-2 px-2">crt_score</th>
                        <th class="text-left py-2 px-2">prior_ai_use</th>
                        <th class="text-right py-2 px-2">age</th>
                        <th class="text-left py-2 px-2">gender</th>
                        <th class="text-left py-2 px-2">education</th>
                        <th class="text-right py-2 pl-2">low_quality_response</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($taskLevelDisplayRows as $row): ?>
                        <?php $participantId = (int) ($row['participant_id'] ?? 0); ?>
                        <tr class="border-b border-slate-100 odd:bg-slate-50 last:border-b-0">
                            <td class="py-2 pr-3 text-right">
                                <?php if ($participantId > 0): ?>
                                    <a href="/dashboard/?tab=participant&participant_id=<?= e((string) $participantId) ?><?= e($includeTestParticipants ? '&include_test=1' : '') ?>" class="accent-text hover:underline font-medium">
                                        <?= e((string) $participantId) ?>
                                    </a>
                                <?php else: ?>
                                    <?= e((string) $participantId) ?>
                                <?php endif; ?>
                            </td>
                            <td class="py-2 pr-3"><?= e((string) ($row['participant_code'] ?? '')) ?></td>
                            <td class="py-2 pr-3"><?= e((string) ($row['condition_name'] ?? '')) ?></td>
                            <td class="py-2 pr-3"><?= e((string) ($row['prolific'] ?? '')) ?></td>
                            <?php if ($isTaskLevelAllTab): ?>
                                <td class="py-2 px-2 text-right"><?= e((string) ($row['finished_survey'] ?? '')) ?></td>
                            <?php endif; ?>
                            <td class="py-2 px-2 text-right"><?= e((string) ($row['task_number'] ?? '')) ?></td>
                            <td class="py-2 px-2 text-right"><?= e((string) ($row['ai_correct'] ?? '')) ?></td>
                            <td class="py-2 px-2"><?= e((string) ($row['selected_option_key'] ?? '')) ?></td>
                            <td class="py-2 px-2 text-right"><?= e((string) ($row['final_decision_correct'] ?? '')) ?></td>
                            <td class="py-2 px-2 text-right"><?= e((string) ($row['inspection_any'] ?? '')) ?></td>
                            <td class="py-2 px-2 text-right"><?= e((string) ($row['relevant_document_opened'] ?? '')) ?></td>
                            <td class="py-2 px-2 text-right"><?= e((string) ($row['number_documents_opened'] ?? '')) ?></td>
                            <td class="py-2 px-2 text-right"><?= e(number_format((float) ($row['total_document_view_time_sec'] ?? 0), 2)) ?></td>
                            <td class="py-2 px-2 text-right"><?= e(number_format((float) ($row['relevant_document_view_time_sec'] ?? 0), 2)) ?></td>
                            <td class="py-2 px-2 text-right"><?= e((string) ($row['confidence'] ?? '')) ?></td>
                            <td class="py-2 px-2 text-right"><?= e((string) ($row['manual_code_required'] ?? '')) ?></td>
                            <td class="py-2 px-2 text-right"><?= e((string) ($row['manual_response_correctness'] ?? '')) ?></td>
                            <td class="py-2 px-2 text-right"><?= e($row['ai_literacy_score'] !== null ? number_format((float) $row['ai_literacy_score'], 2) : '') ?></td>
                            <td class="py-2 px-2 text-right"><?= e((string) ($row['crt_score'] ?? '')) ?></td>
                            <td class="py-2 px-2"><?= e((string) ($row['ai_experience'] ?? '')) ?></td>
                            <td class="py-2 px-2 text-right"><?= e((string) ($row['age'] ?? '')) ?></td>
                            <td class="py-2 px-2"><?= e((string) ($row['gender'] ?? '')) ?></td>
                            <td class="py-2 px-2"><?= e((string) ($row['education'] ?? '')) ?></td>
                            <td class="py-2 pl-2 text-right"><?= e((string) ($row['low_quality_response'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php elseif ($currentTab === 'data_for_analysis' || $currentTab === 'data_for_analysis_all'): ?>
        <?php
        $isDataForAnalysisAllTab = $currentTab === 'data_for_analysis_all';
        $dataForAnalysisDisplayRows = $isDataForAnalysisAllTab
            ? $analysisDataForAnalysisAllRows
            : $analysisDataForAnalysisRows;
        $dataForAnalysisTableId = $isDataForAnalysisAllTab ? 'data-analysis-all-table' : 'data-analysis-table';
        $dataForAnalysisColumnSelectId = $isDataForAnalysisAllTab ? 'data-analysis-all-column-select' : 'data-analysis-column-select';
        $dataForAnalysisSelectedColumnLabelId = $isDataForAnalysisAllTab ? 'data-analysis-all-selected-column' : 'data-analysis-selected-column';
        $dataForAnalysisOpenFiltersId = $isDataForAnalysisAllTab ? 'data-analysis-all-open-filters' : 'data-analysis-open-filters';
        $dataForAnalysisCloseFiltersId = $isDataForAnalysisAllTab ? 'data-analysis-all-close-filters' : 'data-analysis-close-filters';
        $dataForAnalysisFilterModalId = $isDataForAnalysisAllTab ? 'data-analysis-all-filter-modal' : 'data-analysis-filter-modal';
        $dataForAnalysisFilterFieldsId = $isDataForAnalysisAllTab ? 'data-analysis-all-filter-fields' : 'data-analysis-filter-fields';
        $dataForAnalysisFilterCountId = $isDataForAnalysisAllTab ? 'data-analysis-all-filter-count' : 'data-analysis-filter-count';
        $dataForAnalysisMoveFirstId = $isDataForAnalysisAllTab ? 'data-analysis-all-move-first' : 'data-analysis-move-first';
        $dataForAnalysisMoveLeftId = $isDataForAnalysisAllTab ? 'data-analysis-all-move-left' : 'data-analysis-move-left';
        $dataForAnalysisMoveRightId = $isDataForAnalysisAllTab ? 'data-analysis-all-move-right' : 'data-analysis-move-right';
        $dataForAnalysisMoveLastId = $isDataForAnalysisAllTab ? 'data-analysis-all-move-last' : 'data-analysis-move-last';
        $dataForAnalysisClearFiltersId = $isDataForAnalysisAllTab ? 'data-analysis-all-clear-filters' : 'data-analysis-clear-filters';
        $dataForAnalysisResetId = $isDataForAnalysisAllTab ? 'data-analysis-all-reset' : 'data-analysis-reset';
        $dataForAnalysisTitle = $isDataForAnalysisAllTab ? 'Data for analysis (all entries)' : 'Data for analysis';
        $dataForAnalysisDescription = $isDataForAnalysisAllTab
            ? 'All participants, including those who did not fully complete the study. finished_survey = 1 when tasks_completed = 2, post-survey submitted, and completed_at is set.'
            : 'Only fully completed participants (tasks_completed=2 and post-survey available).';
        ?>
        <section class="bg-white shadow rounded-xl p-6 mb-4 overflow-x-auto">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-2">
                <h2 class="text-lg font-semibold text-slate-800"><?= e($dataForAnalysisTitle) ?></h2>
                <div class="flex flex-wrap items-center gap-2">
                    <a
                        href="/dashboard/?tab=<?= e($currentTab) ?>&download=1<?= e($includeTestParticipants ? '&include_test=1' : '') ?>"
                        class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-4 py-2 rounded-lg transition"
                    >
                        Download CSV
                    </a>
                    <?php if ($isDataForAnalysisAllTab): ?>
                        <a
                            href="/dashboard/?tab=data_for_analysis_all&download=serious_effort<?= e($includeTestParticipants ? '&include_test=1' : '') ?>"
                            class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-4 py-2 rounded-lg transition"
                        >
                            Download Serious Effort CSV
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <p class="text-sm text-slate-600 mb-4"><?= e($dataForAnalysisDescription) ?></p>
            <div class="mb-4 rounded-lg border border-slate-200 bg-slate-50 p-3">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                    <p class="text-xs font-semibold text-slate-700">Table controls</p>
                    <span id="<?= e($dataForAnalysisSelectedColumnLabelId) ?>" class="rounded-full bg-white px-2 py-1 text-[11px] text-slate-600 border border-slate-200">Selected column: -</span>
                </div>
                <div class="grid gap-2 md:grid-cols-[auto_minmax(220px,1fr)_auto] md:items-center">
                    <label for="<?= e($dataForAnalysisColumnSelectId) ?>" class="text-xs text-slate-600">Column order</label>
                    <select id="<?= e($dataForAnalysisColumnSelectId) ?>" class="w-full rounded-md border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-700 shadow-sm"></select>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" id="<?= e($dataForAnalysisOpenFiltersId) ?>" class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700 hover:bg-slate-100">Filters</button>
                        <span id="<?= e($dataForAnalysisFilterCountId) ?>" class="rounded-full bg-white px-2 py-1 text-[11px] text-slate-600 border border-slate-200">0 active</span>
                        <button type="button" id="<?= e($dataForAnalysisMoveFirstId) ?>" class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700 hover:bg-slate-100">Move first</button>
                        <button type="button" id="<?= e($dataForAnalysisMoveLeftId) ?>" class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700 hover:bg-slate-100">Move left</button>
                        <button type="button" id="<?= e($dataForAnalysisMoveRightId) ?>" class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700 hover:bg-slate-100">Move right</button>
                        <button type="button" id="<?= e($dataForAnalysisMoveLastId) ?>" class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700 hover:bg-slate-100">Move last</button>
                        <button type="button" id="<?= e($dataForAnalysisClearFiltersId) ?>" class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700 hover:bg-slate-100">Clear filters</button>
                        <button type="button" id="<?= e($dataForAnalysisResetId) ?>" class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100">Reset all</button>
                    </div>
                </div>
                <p class="text-[11px] text-slate-500 mt-2">Use Filters to set column filters in a popup. Move first/last helps quickly re-sequence wide tables.</p>
            </div>
            <div id="<?= e($dataForAnalysisFilterModalId) ?>" class="fixed inset-0 z-40 hidden items-center justify-center bg-slate-900/40 p-4">
                <div class="w-full max-w-3xl rounded-xl bg-white shadow-xl">
                    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                        <h3 class="text-sm font-semibold text-slate-800">Set filters</h3>
                        <button type="button" id="<?= e($dataForAnalysisCloseFiltersId) ?>" class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700 hover:bg-slate-100">Close</button>
                    </div>
                    <div id="<?= e($dataForAnalysisFilterFieldsId) ?>" class="max-h-[65vh] space-y-3 overflow-y-auto px-4 py-3"></div>
                </div>
            </div>
            <table id="<?= e($dataForAnalysisTableId) ?>" class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-600">
                        <th class="text-right py-2 pr-3">ID</th>
                        <th class="text-left py-2 pr-3">participant_code</th>
                        <th class="text-left py-2 pr-3">condition</th>
                        <th class="text-left py-2 pr-3">prolific</th>
                        <?php if ($isDataForAnalysisAllTab): ?>
                            <th class="text-right py-2 px-2">finished_survey</th>
                        <?php endif; ?>
                        <th class="text-left py-2 pr-3">task1_reliance_choice</th>
                        <th class="text-right py-2 px-2">task1_decision_correct</th>
                        <th class="text-right py-2 px-2">task1_confidence</th>
                        <th class="text-right py-2 px-2">task1_relevant_doc_opened</th>
                        <th class="text-right py-2 px-2">task1_number_docs_opened</th>
                        <th class="text-right py-2 px-2">task1_docs_opened_any</th>
                        <th class="text-right py-2 px-2">task1_total_doc_view_time_sec</th>
                        <th class="text-left py-2 px-2">task2_reliance_choice</th>
                        <th class="text-right py-2 px-2">task2_decision_correct</th>
                        <th class="text-right py-2 px-2">task2_confidence</th>
                        <th class="text-right py-2 px-2">task2_relevant_doc_opened</th>
                        <th class="text-right py-2 px-2">task2_number_docs_opened</th>
                        <th class="text-right py-2 px-2">task2_docs_opened_any</th>
                        <th class="text-right py-2 px-2">task2_total_doc_view_time_sec</th>
                        <th class="text-right py-2 px-2">calibration_score</th>
                        <th class="text-right py-2 px-2">avg_confidence</th>
                        <th class="text-right py-2 px-2">avg_docs_opened</th>
                        <th class="text-right py-2 px-2">total_doc_time_sec</th>
                        <th class="text-right py-2 px-2">ai_literacy</th>
                        <th class="text-right py-2 px-2">crt</th>
                        <th class="text-right py-2 px-2">task_clarity</th>
                        <th class="text-right py-2 px-2">notice_cue</th>
                        <th class="text-right py-2 px-2">task_realism</th>
                        <th class="text-left py-2 px-2">ai_exp</th>
                        <th class="text-right py-2 px-2">age</th>
                        <th class="text-left py-2 px-2">gender</th>
                        <th class="text-left py-2 px-2">education</th>
                        <th class="text-right py-2 px-2">task1_duration_sec</th>
                        <th class="text-right py-2 px-2">task2_duration_sec</th>
                        <th class="text-right py-2 px-2">postsurvey_duration_sec</th>
                        <th class="text-right py-2 pl-2">total_survey_duration_sec</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dataForAnalysisDisplayRows)): ?>
                        <tr>
                            <td class="py-3 text-slate-500" colspan="<?= e((string) ($isDataForAnalysisAllTab ? 36 : 35)) ?>"><?= $isDataForAnalysisAllTab ? 'No participants found.' : 'No fully completed participants found.' ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($dataForAnalysisDisplayRows as $row): ?>
                            <tr class="border-b border-slate-100 odd:bg-slate-50 last:border-b-0">
                                <td class="py-2 pr-3 text-right">
                                    <a href="/dashboard/?tab=participant&participant_id=<?= e((string) $row['participant_id']) ?><?= e($includeTestParticipants ? '&include_test=1' : '') ?>" class="accent-text hover:underline font-medium">
                                        <?= e((string) $row['participant_id']) ?>
                                    </a>
                                </td>
                                <td class="py-2 pr-3"><?= e((string) ($row['participant_code'] ?? '')) ?></td>
                                <td class="py-2 pr-3"><?= e((string) ($row['condition_name'] ?? '')) ?></td>
                                <td class="py-2 pr-3"><?= e((string) ($row['prolific'] ?? '')) ?></td>
                                <?php if ($isDataForAnalysisAllTab): ?>
                                    <td class="py-2 px-2 text-right"><?= e((string) ($row['finished_survey'] ?? '')) ?></td>
                                <?php endif; ?>
                                <td class="py-2 pr-3"><?= e((string) ($row['task1_reliance_choice'] ?? '')) ?></td>
                                <td class="py-2 px-2 text-right"><?= e((string) ($row['task1_decision_correct'] ?? '')) ?></td>
                                <td class="py-2 px-2 text-right"><?= e((string) ($row['task1_confidence'] ?? '')) ?></td>
                                <td class="py-2 px-2 text-right"><?= e((string) ($row['task1_relevant_doc_opened'] ?? '')) ?></td>
                                <td class="py-2 px-2 text-right"><?= e((string) ($row['task1_number_docs_opened'] ?? '')) ?></td>
                                <td class="py-2 px-2 text-right"><?= e((string) ($row['task1_docs_opened_any'] ?? '')) ?></td>
                                <td class="py-2 px-2 text-right"><?= $row['task1_total_doc_view_time_sec'] === null ? '' : e(number_format((float) $row['task1_total_doc_view_time_sec'], 2)) ?></td>
                                <td class="py-2 px-2"><?= e((string) ($row['task2_reliance_choice'] ?? '')) ?></td>
                                <td class="py-2 px-2 text-right"><?= e((string) ($row['task2_decision_correct'] ?? '')) ?></td>
                                <td class="py-2 px-2 text-right"><?= e((string) ($row['task2_confidence'] ?? '')) ?></td>
                                <td class="py-2 px-2 text-right"><?= e((string) ($row['task2_relevant_doc_opened'] ?? '')) ?></td>
                                <td class="py-2 px-2 text-right"><?= e((string) ($row['task2_number_docs_opened'] ?? '')) ?></td>
                                <td class="py-2 px-2 text-right"><?= e((string) ($row['task2_docs_opened_any'] ?? '')) ?></td>
                                <td class="py-2 px-2 text-right"><?= $row['task2_total_doc_view_time_sec'] === null ? '' : e(number_format((float) $row['task2_total_doc_view_time_sec'], 2)) ?></td>
                                <td class="py-2 px-2 text-right"><?= $row['calibration_score'] === null ? '' : e(number_format((float) $row['calibration_score'], 4)) ?></td>
                                <td class="py-2 px-2 text-right"><?= $row['avg_confidence'] === null ? '' : e(number_format((float) $row['avg_confidence'], 2)) ?></td>
                                <td class="py-2 px-2 text-right"><?= $row['avg_docs_opened'] === null ? '' : e(number_format((float) $row['avg_docs_opened'], 2)) ?></td>
                                <td class="py-2 px-2 text-right"><?= $row['total_doc_time_sec'] === null ? '' : e(number_format((float) $row['total_doc_time_sec'], 2)) ?></td>
                                <td class="py-2 px-2 text-right"><?= e((string) ($row['ai_literacy'] ?? '')) ?></td>
                                <td class="py-2 px-2 text-right"><?= e((string) ($row['crt_score'] ?? '')) ?></td>
                                <td class="py-2 px-2 text-right"><?= e((string) ($row['task_clarity'] ?? '')) ?></td>
                                <td class="py-2 px-2 text-right"><?= e((string) ($row['notice_cue'] ?? '')) ?></td>
                                <td class="py-2 px-2 text-right"><?= e((string) ($row['task_realism'] ?? '')) ?></td>
                                <td class="py-2 px-2"><?= e((string) ($row['ai_experience'] ?? '')) ?></td>
                                <td class="py-2 px-2 text-right"><?= e((string) ($row['age'] ?? '')) ?></td>
                                <td class="py-2 px-2"><?= e((string) ($row['gender'] ?? '')) ?></td>
                                <td class="py-2 px-2"><?= e((string) ($row['education'] ?? '')) ?></td>
                                <td class="py-2 px-2 text-right"><?= $row['task1_duration_seconds'] === null ? '' : e(number_format((float) $row['task1_duration_seconds'], 2)) ?></td>
                                <td class="py-2 px-2 text-right"><?= $row['task2_duration_seconds'] === null ? '' : e(number_format((float) $row['task2_duration_seconds'], 2)) ?></td>
                                <td class="py-2 px-2 text-right"><?= $row['postsurvey_duration_seconds'] === null ? '' : e(number_format((float) $row['postsurvey_duration_seconds'], 2)) ?></td>
                                <td class="py-2 pl-2 text-right"><?= $row['total_survey_duration_seconds'] === null ? '' : e(number_format((float) $row['total_survey_duration_seconds'], 2)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
        <?php if (!$isDataForAnalysisAllTab): ?>
        <section class="bg-white shadow rounded-xl p-6 mb-4 overflow-x-auto">
            <h3 class="text-base font-semibold text-slate-800 mb-3">Not finished surveys</h3>
            <p class="text-sm text-slate-600 mb-3">Participants excluded from Data for analysis because they did not complete all required study components.</p>
            <?php if (empty($analysisNotFinishedRows)): ?>
                <p class="text-sm text-slate-500">No unfinished surveys found.</p>
            <?php else: ?>
                <div class="mb-4 rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                        <p class="text-xs font-semibold text-slate-700">Table controls</p>
                        <span id="data-not-finished-selected-column" class="rounded-full bg-white px-2 py-1 text-[11px] text-slate-600 border border-slate-200">Selected column: -</span>
                    </div>
                    <div class="grid gap-2 md:grid-cols-[auto_minmax(220px,1fr)_auto] md:items-center">
                        <label for="data-not-finished-column-select" class="text-xs text-slate-600">Column order</label>
                        <select id="data-not-finished-column-select" class="w-full rounded-md border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-700 shadow-sm"></select>
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" id="data-not-finished-open-filters" class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700 hover:bg-slate-100">Filters</button>
                            <span id="data-not-finished-filter-count" class="rounded-full bg-white px-2 py-1 text-[11px] text-slate-600 border border-slate-200">0 active</span>
                            <button type="button" id="data-not-finished-move-first" class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700 hover:bg-slate-100">Move first</button>
                            <button type="button" id="data-not-finished-move-left" class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700 hover:bg-slate-100">Move left</button>
                            <button type="button" id="data-not-finished-move-right" class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700 hover:bg-slate-100">Move right</button>
                            <button type="button" id="data-not-finished-move-last" class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700 hover:bg-slate-100">Move last</button>
                            <button type="button" id="data-not-finished-clear-filters" class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700 hover:bg-slate-100">Clear filters</button>
                            <button type="button" id="data-not-finished-reset" class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100">Reset all</button>
                        </div>
                    </div>
                    <p class="text-[11px] text-slate-500 mt-2">Use Filters to set column filters in a popup. Move first/last helps quickly re-sequence wide tables.</p>
                </div>
                <div id="data-not-finished-filter-modal" class="fixed inset-0 z-40 hidden items-center justify-center bg-slate-900/40 p-4">
                    <div class="w-full max-w-3xl rounded-xl bg-white shadow-xl">
                        <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                            <h3 class="text-sm font-semibold text-slate-800">Set filters</h3>
                            <button type="button" id="data-not-finished-close-filters" class="rounded-md border border-slate-300 bg-white px-2 py-1 text-xs text-slate-700 hover:bg-slate-100">Close</button>
                        </div>
                        <div id="data-not-finished-filter-fields" class="max-h-[65vh] space-y-3 overflow-y-auto px-4 py-3"></div>
                    </div>
                </div>
                <table id="data-not-finished-table" class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-600">
                            <th class="text-right py-2 pr-3">participant_id</th>
                            <th class="text-left py-2 pr-3">participant_code</th>
                            <th class="text-left py-2 pr-3">condition_name</th>
                            <th class="text-left py-2 pr-3">task1_reliance_choice</th>
                            <th class="text-right py-2 px-2">task1_decision_correct</th>
                            <th class="text-right py-2 px-2">task1_confidence</th>
                            <th class="text-right py-2 px-2">task1_relevant_doc_opened</th>
                            <th class="text-right py-2 px-2">task1_number_docs_opened</th>
                            <th class="text-right py-2 px-2">task1_docs_opened_any</th>
                            <th class="text-right py-2 px-2">task1_total_doc_view_time_sec</th>
                            <th class="text-left py-2 px-2">task2_reliance_choice</th>
                            <th class="text-right py-2 px-2">task2_decision_correct</th>
                            <th class="text-right py-2 px-2">task2_confidence</th>
                            <th class="text-right py-2 px-2">task2_relevant_doc_opened</th>
                            <th class="text-right py-2 px-2">task2_number_docs_opened</th>
                            <th class="text-right py-2 px-2">task2_docs_opened_any</th>
                            <th class="text-right py-2 px-2">task2_total_doc_view_time_sec</th>
                            <th class="text-right py-2 px-2">task1_duration_sec</th>
                            <th class="text-right py-2 px-2">task2_duration_sec</th>
                            <th class="text-right py-2 px-2">tasks_completed</th>
                            <th class="text-left py-2 px-2">completed_at</th>
                            <th class="text-right py-2 px-2">serious_effort</th>
                            <th class="text-left py-2 pl-2">reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($analysisNotFinishedRows as $row): ?>
                            <tr class="border-b border-slate-100 odd:bg-slate-50 last:border-b-0">
                                <td class="py-2 pr-3 text-right">
                                    <a href="/dashboard/?tab=participant&participant_id=<?= e((string) $row['participant_id']) ?><?= e($includeTestParticipants ? '&include_test=1' : '') ?>" class="accent-text hover:underline font-medium">
                                        <?= e((string) $row['participant_id']) ?>
                                    </a>
                                </td>
                                <td class="py-2 pr-3"><?= e((string) $row['participant_code']) ?></td>
                                <td class="py-2 pr-3"><?= e((string) $row['condition_name']) ?></td>
                                <td class="py-2 pr-3"><?= e((string) ($row['task1_reliance_choice'] !== '' ? $row['task1_reliance_choice'] : 'Missing')) ?></td>
                                <td class="py-2 px-2 text-right"><?= e((string) ($row['task1_decision_correct'] ?? 'Missing')) ?></td>
                                <td class="py-2 px-2 text-right"><?= e((string) ($row['task1_confidence'] ?? 'Missing')) ?></td>
                                <td class="py-2 px-2 text-right"><?= e((string) ($row['task1_relevant_doc_opened'] ?? 'Missing')) ?></td>
                                <td class="py-2 px-2 text-right"><?= e((string) ($row['task1_number_docs_opened'] ?? 'Missing')) ?></td>
                                <td class="py-2 px-2 text-right"><?= e((string) ($row['task1_docs_opened_any'] ?? 'Missing')) ?></td>
                                <td class="py-2 px-2 text-right"><?= $row['task1_total_doc_view_time_sec'] === null ? e('Missing') : e(number_format((float) $row['task1_total_doc_view_time_sec'], 2)) ?></td>
                                <td class="py-2 px-2"><?= e((string) ($row['task2_reliance_choice'] !== '' ? $row['task2_reliance_choice'] : 'Missing')) ?></td>
                                <td class="py-2 px-2 text-right"><?= e((string) ($row['task2_decision_correct'] ?? 'Missing')) ?></td>
                                <td class="py-2 px-2 text-right"><?= e((string) ($row['task2_confidence'] ?? 'Missing')) ?></td>
                                <td class="py-2 px-2 text-right"><?= e((string) ($row['task2_relevant_doc_opened'] ?? 'Missing')) ?></td>
                                <td class="py-2 px-2 text-right"><?= e((string) ($row['task2_number_docs_opened'] ?? 'Missing')) ?></td>
                                <td class="py-2 px-2 text-right"><?= e((string) ($row['task2_docs_opened_any'] ?? 'Missing')) ?></td>
                                <td class="py-2 px-2 text-right"><?= $row['task2_total_doc_view_time_sec'] === null ? e('Missing') : e(number_format((float) $row['task2_total_doc_view_time_sec'], 2)) ?></td>
                                <td class="py-2 px-2 text-right"><?= $row['task1_duration_seconds'] === null ? e('Missing') : e(number_format((float) $row['task1_duration_seconds'], 2)) ?></td>
                                <td class="py-2 px-2 text-right"><?= $row['task2_duration_seconds'] === null ? e('Missing') : e(number_format((float) $row['task2_duration_seconds'], 2)) ?></td>
                                <td class="py-2 px-2 text-right"><?= e((string) $row['tasks_completed']) ?></td>
                                <td class="py-2 px-2">
                                    <?php if ((string) $row['completed_at'] === ''): ?>
                                        <span class="text-slate-400">Not completed</span>
                                    <?php else: ?>
                                        <?= e(format_dashboard_datetime((string) $row['completed_at'])) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2 px-2 text-right">
                                    <?php if ($row['serious_effort'] === null): ?>
                                        <span class="text-slate-400">Missing</span>
                                    <?php else: ?>
                                        <?= e((string) $row['serious_effort']) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2 pl-2"><?= e((string) $row['reason']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    <?php elseif ($currentTab === 'data'): ?>
        <section class="bg-white shadow rounded-xl p-6 mb-4">
            <form method="get" action="/dashboard/" class="flex flex-wrap items-end gap-3">
                <input type="hidden" name="tab" value="data">
                <div>
                    <label for="table" class="block text-sm font-medium text-slate-700 mb-1">Table</label>
                    <select id="table" name="table" class="rounded-lg border border-slate-300 px-3 py-2">
                        <?php foreach ($allowedDataTables as $tableName): ?>
                            <option value="<?= e($tableName) ?>" <?= $selectedTable === $tableName ? 'selected' : '' ?>>
                                <?= e($tableName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button
                    type="submit"
                    class="accent-bg accent-bg-hover text-white text-sm font-medium px-4 py-2 rounded-lg transition"
                >
                    Load Data
                </button>
                <a
                    href="/export_csv.php?table=<?= e($selectedTable) ?><?= e($includeTestParticipants ? '&include_test=1' : '') ?>"
                    class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-4 py-2 rounded-lg transition"
                >
                    Download CSV
                </a>
            </form>
            <p class="text-xs text-slate-500 mt-3">
                Showing <?= e((string) count($dataRows)) ?> rows (page <?= e((string) $dataPage) ?> of <?= e((string) $dataTotalPages) ?>, total <?= e((string) $dataTotalRows) ?> rows), sorted by <?= e($sortColumn) ?> (<?= e(strtoupper($sortDirection)) ?>).
            </p>
            <p class="text-xs text-slate-500 mt-1">
                Datetime columns are displayed in Europe/Amsterdam (converted from UTC).
            </p>
            <?php if (in_array('id', $dataColumns, true) && !empty($dataRows)): ?>
                <?php
                $bulkReturnUrl = '/dashboard/?tab=data&table=' . urlencode($selectedTable)
                    . '&sort=' . urlencode($sortColumn)
                    . '&dir=' . urlencode($sortDirection)
                    . '&page=' . urlencode((string) $dataPage);
                ?>
                <form id="bulk-data-form" method="post" action="/dashboard/" class="mt-4 flex flex-wrap items-center gap-2">
                    <input type="hidden" name="dashboard_action" value="bulk_move_to_trash">
                    <input type="hidden" name="csrf_token" value="<?= e($dashboardCsrfToken) ?>">
                    <input type="hidden" name="table" value="<?= e($selectedTable) ?>">
                    <input type="hidden" name="return_url" value="<?= e($bulkReturnUrl) ?>">
                    <button
                        id="bulk-data-submit"
                        type="submit"
                        class="text-sm bg-rose-50 hover:bg-rose-100 text-rose-700 px-3 py-2 rounded border border-rose-200"
                        onclick="return confirm('Move selected rows to trash?');"
                    >
                        Move Selected to Trash
                    </button>
                    <span class="text-xs text-slate-500">Use the checkboxes in the first column.</span>
                    <span class="text-xs text-slate-500">Selected: <span id="data-selected-count">0</span></span>
                </form>
            <?php endif; ?>
        </section>

        <section class="bg-white shadow rounded-xl p-6 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-600">
                        <?php if (in_array('id', $dataColumns, true)): ?>
                            <th class="sticky top-0 z-10 bg-white text-left py-2 pr-3 font-semibold whitespace-nowrap">
                                <input type="checkbox" id="select-all-data" title="Select all rows on this page">
                            </th>
                        <?php endif; ?>
                        <?php foreach ($dataColumns as $column): ?>
                            <?php
                            $nextDirection = ($sortColumn === $column && $sortDirection === 'asc') ? 'desc' : 'asc';
                            $sortUrl = '/dashboard/?tab=data&table=' . urlencode($selectedTable)
                                . '&page=1&sort=' . urlencode($column) . '&dir=' . urlencode($nextDirection);
                            ?>
                            <th class="sticky top-0 z-10 bg-white text-left py-2 pr-3 font-semibold whitespace-nowrap">
                                <a href="<?= e($sortUrl) ?>" class="hover:accent-text">
                                    <?= e($column) ?>
                                    <?php if ($sortColumn === $column): ?>
                                        <span class="accent-text"><?= $sortDirection === 'asc' ? '▲' : '▼' ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                        <?php endforeach; ?>
                        <?php if (in_array('id', $dataColumns, true)): ?>
                            <th class="sticky top-0 z-10 bg-white text-left py-2 pr-3 font-semibold whitespace-nowrap">actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dataRows)): ?>
                        <tr>
                            <td class="py-3 text-slate-500" colspan="<?= e((string) (max(1, count($dataColumns)) + (in_array('id', $dataColumns, true) ? 2 : 0))) ?>">No data found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($dataRows as $row): ?>
                            <tr class="border-b border-slate-100 odd:bg-slate-50 last:border-b-0">
                                <?php if (in_array('id', $dataColumns, true)): ?>
                                    <td class="py-2 pr-3 text-slate-700 align-top whitespace-nowrap">
                                        <?php $rowIdForSelect = (string) ($row['id'] ?? ''); ?>
                                        <?php if ($rowIdForSelect !== ''): ?>
                                            <input
                                                type="checkbox"
                                                name="selected_row_ids[]"
                                                value="<?= e($rowIdForSelect) ?>"
                                                form="bulk-data-form"
                                                class="data-row-checkbox"
                                            >
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <?php foreach ($dataColumns as $column): ?>
                                    <?php
                                    $rawValue = (string) ($row[$column] ?? '');
                                    $displayValue = $rawValue;
                                    if (
                                        $rawValue !== ''
                                        && (str_ends_with($column, '_at') || $column === 'event_time')
                                    ) {
                                        $displayValue = format_dashboard_datetime($rawValue);
                                    }
                                    ?>
                                    <td class="py-2 pr-3 text-slate-700 align-top whitespace-nowrap">
                                        <?php
                                        $isParticipantLink = (
                                            ($selectedTable === 'participants' && $column === 'id')
                                            || ($column === 'participant_id' && $rawValue !== '')
                                        );
                                        if ($isParticipantLink):
                                        ?>
                                            <a
                                                href="/dashboard/?tab=participant&participant_id=<?= e($rawValue) ?>"
                                                class="accent-text hover:underline font-medium"
                                            >
                                                <?= e($displayValue) ?>
                                            </a>
                                        <?php else: ?>
                                            <?= e($displayValue) ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                <?php if (in_array('id', $dataColumns, true)): ?>
                                    <td class="py-2 pr-3 align-top whitespace-nowrap">
                                        <?php $rowId = (string) ($row['id'] ?? ''); ?>
                                        <?php if ($rowId !== ''): ?>
                                            <?php
                                            $returnUrl = '/dashboard/?tab=data&table=' . urlencode($selectedTable)
                                                . '&sort=' . urlencode($sortColumn)
                                                . '&dir=' . urlencode($sortDirection)
                                                . '&page=' . urlencode((string) $dataPage);
                                            ?>
                                            <form method="post" action="/dashboard/" onsubmit="return confirm('Move this row to trash?');">
                                                <input type="hidden" name="dashboard_action" value="delete_row">
                                                <input type="hidden" name="csrf_token" value="<?= e($dashboardCsrfToken) ?>">
                                                <input type="hidden" name="table" value="<?= e($selectedTable) ?>">
                                                <input type="hidden" name="row_id" value="<?= e($rowId) ?>">
                                                <input type="hidden" name="return_url" value="<?= e($returnUrl) ?>">
                                                <button
                                                    type="submit"
                                                    aria-label="Move row to trash"
                                                    title="Move to trash"
                                                    class="text-xs bg-rose-50 hover:bg-rose-100 text-rose-700 px-2 py-1 rounded border border-rose-200"
                                                >
                                                    &#128465;
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

        <section class="mt-4 flex items-center gap-2">
            <?php
            $prevPage = max(1, $dataPage - 1);
            $nextPage = min($dataTotalPages, $dataPage + 1);
            $baseDataUrl = '/dashboard/?tab=data&table=' . urlencode($selectedTable)
                . '&sort=' . urlencode($sortColumn) . '&dir=' . urlencode($sortDirection) . '&page=';
            ?>
            <a
                href="<?= e($baseDataUrl . '1') ?>"
                class="px-3 py-1.5 text-sm rounded border border-slate-300 <?= $dataPage === 1 ? 'pointer-events-none opacity-50' : 'hover:bg-slate-100' ?>"
            >
                First
            </a>
            <a
                href="<?= e($baseDataUrl . (string) $prevPage) ?>"
                class="px-3 py-1.5 text-sm rounded border border-slate-300 <?= $dataPage === 1 ? 'pointer-events-none opacity-50' : 'hover:bg-slate-100' ?>"
            >
                Prev
            </a>
            <span class="text-sm text-slate-600 px-1">Page <?= e((string) $dataPage) ?> / <?= e((string) $dataTotalPages) ?></span>
            <a
                href="<?= e($baseDataUrl . (string) $nextPage) ?>"
                class="px-3 py-1.5 text-sm rounded border border-slate-300 <?= $dataPage >= $dataTotalPages ? 'pointer-events-none opacity-50' : 'hover:bg-slate-100' ?>"
            >
                Next
            </a>
            <a
                href="<?= e($baseDataUrl . (string) $dataTotalPages) ?>"
                class="px-3 py-1.5 text-sm rounded border border-slate-300 <?= $dataPage >= $dataTotalPages ? 'pointer-events-none opacity-50' : 'hover:bg-slate-100' ?>"
            >
                Last
            </a>
        </section>
    <?php elseif ($currentTab === 'trash'): ?>
        <section class="bg-white shadow rounded-xl p-6 overflow-x-auto">
            <div class="mb-3">
                <h2 class="text-lg font-semibold text-slate-800">Trash Bin</h2>
                <p class="text-sm text-slate-600">Restore recently deleted rows or permanently remove them.</p>
            </div>
            <?php if (!empty($trashRows)): ?>
                <form id="bulk-trash-form" method="post" action="/dashboard/" class="mb-4 flex flex-wrap items-center gap-2">
                    <input type="hidden" name="csrf_token" value="<?= e($dashboardCsrfToken) ?>">
                    <select name="dashboard_action" class="rounded border border-slate-300 px-3 py-2 text-sm">
                        <option value="bulk_restore_trash">Restore selected</option>
                        <option value="bulk_purge_trash">Delete selected permanently</option>
                    </select>
                    <button
                        id="bulk-trash-submit"
                        type="submit"
                        class="text-sm bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-2 rounded border border-slate-300"
                        onclick="return confirm('Apply action to selected trash items?');"
                    >
                        Apply to Selected
                    </button>
                    <span class="text-xs text-slate-500">Use the checkboxes in the first column.</span>
                    <span class="text-xs text-slate-500">Selected: <span id="trash-selected-count">0</span></span>
                </form>
            <?php endif; ?>
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-600">
                        <th class="text-left py-2 pr-3 font-semibold">
                            <input type="checkbox" id="select-all-trash" title="Select all trash items">
                        </th>
                        <th class="text-left py-2 pr-3 font-semibold">ID</th>
                        <th class="text-left py-2 pr-3 font-semibold">Entity type</th>
                        <th class="text-left py-2 pr-3 font-semibold">Source table</th>
                        <th class="text-left py-2 pr-3 font-semibold">Source ID</th>
                        <th class="text-left py-2 pr-3 font-semibold">Deleted at</th>
                        <th class="text-left py-2 pr-3 font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($trashRows)): ?>
                        <tr>
                            <td class="py-3 text-slate-500" colspan="7">Trash is empty.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($trashRows as $trashRow): ?>
                            <tr class="border-b border-slate-100 odd:bg-slate-50 last:border-b-0">
                                <td class="py-2 pr-3 text-slate-700 whitespace-nowrap">
                                    <input
                                        type="checkbox"
                                        name="selected_trash_ids[]"
                                        value="<?= e((string) $trashRow['id']) ?>"
                                        form="bulk-trash-form"
                                        class="trash-row-checkbox"
                                    >
                                </td>
                                <td class="py-2 pr-3 text-slate-700 whitespace-nowrap"><?= e((string) $trashRow['id']) ?></td>
                                <td class="py-2 pr-3 text-slate-700 whitespace-nowrap"><?= e((string) $trashRow['entity_type']) ?></td>
                                <td class="py-2 pr-3 text-slate-700 whitespace-nowrap"><?= e((string) $trashRow['source_table']) ?></td>
                                <td class="py-2 pr-3 text-slate-700 whitespace-nowrap"><?= e((string) ($trashRow['source_id'] ?? '')) ?></td>
                                <td class="py-2 pr-3 text-slate-700 whitespace-nowrap"><?= e(format_dashboard_datetime((string) $trashRow['deleted_at'])) ?></td>
                                <td class="py-2 pr-3 text-slate-700 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <form method="post" action="/dashboard/" onsubmit="return confirm('Restore this item?');">
                                            <input type="hidden" name="dashboard_action" value="restore_trash">
                                            <input type="hidden" name="csrf_token" value="<?= e($dashboardCsrfToken) ?>">
                                            <input type="hidden" name="trash_id" value="<?= e((string) $trashRow['id']) ?>">
                                            <button
                                                type="submit"
                                                class="text-xs bg-emerald-50 hover:bg-emerald-100 text-emerald-700 px-2 py-1 rounded border border-emerald-200"
                                            >
                                                Restore
                                            </button>
                                        </form>
                                        <form method="post" action="/dashboard/" onsubmit="return confirm('Permanently delete this trash item? This cannot be undone.');">
                                            <input type="hidden" name="dashboard_action" value="purge_trash">
                                            <input type="hidden" name="csrf_token" value="<?= e($dashboardCsrfToken) ?>">
                                            <input type="hidden" name="trash_id" value="<?= e((string) $trashRow['id']) ?>">
                                            <button
                                                type="submit"
                                                class="text-xs bg-rose-50 hover:bg-rose-100 text-rose-700 px-2 py-1 rounded border border-rose-200"
                                            >
                                                Delete Permanently
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    <?php else: ?>
        <section class="bg-white shadow rounded-xl p-6 mb-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-800">Participant Details</h2>
                    <p class="text-sm text-slate-600">Detailed view across participant profile, tasks, document events, and post-survey.</p>
                </div>
                <a href="/dashboard/?tab=data&table=participants" class="text-sm bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-2 rounded-lg transition">
                    Back to Full Data
                </a>
            </div>
        </section>

        <?php if ($participantDetail === null): ?>
            <section class="bg-white shadow rounded-xl p-6">
                <p class="text-slate-600">Participant not found. Select a participant from the Full Data tab.</p>
            </section>
        <?php else: ?>
            <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
                <article class="bg-white shadow rounded-xl p-5">
                    <p class="text-sm text-slate-500">Participant ID</p>
                    <p class="text-2xl font-bold text-slate-800 mt-1"><?= e((string) $participantDetail['id']) ?></p>
                    <p class="text-xs text-slate-500 mt-1"><?= e((string) $participantDetail['participant_code']) ?></p>
                </article>
                <article class="bg-white shadow rounded-xl p-5">
                    <p class="text-sm text-slate-500">Condition</p>
                    <p class="text-2xl font-bold text-slate-800 mt-1"><?= e((string) $participantDetail['condition_name']) ?></p>
                </article>
                <article class="bg-white shadow rounded-xl p-5">
                    <p class="text-sm text-slate-500">Avg docs opened / task</p>
                    <p class="text-2xl font-bold text-slate-800 mt-1"><?= e(number_format($participantDerived['avg_docs_opened_4tasks'], 2)) ?></p>
                    <p class="text-xs text-slate-500 mt-1">Total opens (clicks): <?= e((string) $participantDerived['doc_clicks_total_4tasks']) ?></p>
                </article>
                <article class="bg-white shadow rounded-xl p-5">
                    <p class="text-sm text-slate-500">Relevant doc open rate</p>
                    <p class="text-2xl font-bold text-slate-800 mt-1"><?= e(number_format($participantDerived['relevant_doc_open_rate_4tasks'] * 100.0, 1)) ?>%</p>
                    <p class="text-xs text-slate-500 mt-1">Across tasks with known relevant docs</p>
                </article>
                <article class="bg-white shadow rounded-xl p-5">
                    <p class="text-sm text-slate-500">Correct total (0-2)</p>
                    <p class="text-2xl font-bold text-slate-800 mt-1"><?= e((string) $participantDerived['correct_total_4tasks']) ?></p>
                    <p class="text-xs text-slate-500 mt-1">
                        <?= e((string) $participantDerived['decision_correct_count']) ?> / <?= e((string) $participantDerived['decision_total']) ?> tasks
                    </p>
                </article>
                <article class="bg-white shadow rounded-xl p-5">
                    <p class="text-sm text-slate-500">Correct rate (0-1)</p>
                    <p class="text-2xl font-bold text-slate-800 mt-1"><?= e(number_format($participantDerived['correct_rate_4tasks'], 2)) ?></p>
                    <p class="text-xs text-slate-500 mt-1">Decision correctness across submitted tasks</p>
                </article>
                <article class="bg-white shadow rounded-xl p-5">
                    <p class="text-sm text-slate-500">Avg confidence / task</p>
                    <p class="text-2xl font-bold text-slate-800 mt-1"><?= e(number_format($participantDerived['avg_confidence'], 2)) ?></p>
                    <p class="text-xs text-slate-500 mt-1">Task confidence (1-5)</p>
                </article>
                <article class="bg-white shadow rounded-xl p-5">
                    <p class="text-sm text-slate-500">Avg inspect time / task</p>
                    <p class="text-2xl font-bold text-slate-800 mt-1"><?= e(number_format($participantDerived['avg_inspection_time_4tasks'], 2)) ?>s</p>
                    <p class="text-xs text-slate-500 mt-1">Total inspection time across docs per task</p>
                </article>
                <article class="bg-white shadow rounded-xl p-5">
                    <p class="text-sm text-slate-500">AI literacy score</p>
                    <p class="text-2xl font-bold text-slate-800 mt-1">
                        <?php if ($participantDerived['ai_literacy_raw'] === null): ?>
                            -
                        <?php else: ?>
                            <?= e((string) $participantDerived['ai_literacy_raw']) ?> / <?= e((string) $participantDerived['ai_literacy_max']) ?>
                        <?php endif; ?>
                    </p>
                    <p class="text-xs text-slate-500 mt-1">Sum of four items (each 1–5).</p>
                </article>
                <article class="bg-white shadow rounded-xl p-5">
                    <p class="text-sm text-slate-500">CRT score</p>
                    <p class="text-2xl font-bold text-slate-800 mt-1">
                        <?php if ($participantDerived['crt_correct_count'] === null): ?>
                            -
                        <?php else: ?>
                            <?= e((string) $participantDerived['crt_correct_count']) ?> / <?= e((string) $participantDerived['crt_total']) ?>
                        <?php endif; ?>
                    </p>
                    <p class="text-xs text-slate-500 mt-1">
                        <?php if ($participantDerived['crt_score_pct'] === null): ?>
                            Accuracy: -
                        <?php else: ?>
                            Accuracy: <?= e(number_format((float) $participantDerived['crt_score_pct'], 1)) ?>%
                        <?php endif; ?>
                    </p>
                </article>
            </section>

            <section class="bg-white shadow rounded-xl p-6 mb-6 overflow-x-auto">
                <h3 class="text-base font-semibold text-slate-800 mb-3">Participant Profile</h3>
                <table class="min-w-full text-sm">
                    <tbody>
                        <?php foreach ($participantDetail as $field => $value): ?>
                            <tr class="border-b border-slate-100 odd:bg-slate-50 last:border-b-0">
                                <td class="py-2 pr-4 font-medium text-slate-700 whitespace-nowrap"><?= e((string) $field) ?></td>
                                <td class="py-2 text-slate-700">
                                    <?php
                                    $rawValue = (string) ($value ?? '');
                                    $displayValue = $rawValue;
                                    if ($rawValue !== '' && str_ends_with((string) $field, '_at')) {
                                        $displayValue = format_dashboard_datetime($rawValue);
                                    }
                                    ?>
                                    <?= e($displayValue) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>

            <section class="bg-white shadow rounded-xl p-6 mb-6 overflow-x-auto">
                <h3 class="text-base font-semibold text-slate-800 mb-3">Task Responses</h3>
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-600">
                            <th class="sticky top-0 z-10 bg-white text-left py-2 pr-3">task_number</th>
                            <th class="sticky top-0 z-10 bg-white text-left py-2 pr-3">ai_correct</th>
                            <th class="sticky top-0 z-10 bg-white text-left py-2 pr-3">reliance_choice</th>
                            <th class="sticky top-0 z-10 bg-white text-left py-2 pr-3">decision_correct</th>
                            <th class="sticky top-0 z-10 bg-white text-left py-2 pr-3">confidence</th>
                            <th class="sticky top-0 z-10 bg-white text-left py-2 pr-3">docs_opened_unique</th>
                            <th class="sticky top-0 z-10 bg-white text-left py-2 pr-3">inspection_time_total_s</th>
                            <th class="sticky top-0 z-10 bg-white text-left py-2 pr-3">relevant_doc_opened</th>
                            <th class="sticky top-0 z-10 bg-white text-left py-2 pr-3">doc_clicks_total</th>
                            <th class="sticky top-0 z-10 bg-white text-left py-2 pr-3">verification_intention</th>
                            <th class="sticky top-0 z-10 bg-white text-left py-2 pr-3">duration_seconds</th>
                            <th class="sticky top-0 z-10 bg-white text-left py-2 pr-3">short_time_flag</th>
                            <th class="sticky top-0 z-10 bg-white text-left py-2 pr-3">task_submitted_at</th>
                            <th class="sticky top-0 z-10 bg-white text-left py-2 pr-3">final_response</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($participantTaskRowsDetailed)): ?>
                            <tr><td class="py-3 text-slate-500" colspan="14">No task responses found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($participantTaskRowsDetailed as $taskRow): ?>
                                <tr class="border-b border-slate-100 odd:bg-slate-50 last:border-b-0">
                                    <td class="py-2 pr-3"><?= e((string) $taskRow['task_number']) ?></td>
                                    <td class="py-2 pr-3"><?= e((string) $taskRow['ai_correct']) ?></td>
                                    <td class="py-2 pr-3"><?= e((string) $taskRow['reliance_choice']) ?></td>
                                    <td class="py-2 pr-3 font-medium <?= $taskRow['_decision_correct'] === 'Yes' ? 'text-emerald-700' : 'text-rose-700' ?>">
                                        <?= e((string) $taskRow['_decision_correct']) ?>
                                    </td>
                                    <td class="py-2 pr-3"><?= e((string) $taskRow['confidence']) ?></td>
                                    <td class="py-2 pr-3"><?= e((string) $taskRow['_docs_opened_unique']) ?></td>
                                    <td class="py-2 pr-3"><?= e(number_format((float) $taskRow['_inspection_time_total_seconds'], 2)) ?></td>
                                    <td class="py-2 pr-3"><?= e((string) $taskRow['_relevant_doc_opened']) ?></td>
                                    <td class="py-2 pr-3"><?= e((string) $taskRow['_doc_clicks_total']) ?></td>
                                    <td class="py-2 pr-3"><?= e((string) ($taskRow['verification_intention'] ?? '')) ?></td>
                                    <td class="py-2 pr-3"><?= e((string) ($taskRow['duration_seconds'] ?? '')) ?></td>
                                    <td class="py-2 pr-3"><?= e((string) ($taskRow['short_time_flag'] ?? '')) ?></td>
                                    <td class="py-2 pr-3"><?= e(format_dashboard_datetime((string) ($taskRow['task_submitted_at'] ?? ''))) ?></td>
                                    <td class="py-2 pr-3 max-w-sm whitespace-normal break-words"><?= e((string) $taskRow['final_response']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>

            <section class="bg-white shadow rounded-xl p-6 mb-6 overflow-x-auto">
                <h3 class="text-base font-semibold text-slate-800 mb-3">Document Events</h3>
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-600">
                            <th class="sticky top-0 z-10 bg-white text-left py-2 pr-3">event_time</th>
                            <th class="sticky top-0 z-10 bg-white text-left py-2 pr-3">task_number</th>
                            <th class="sticky top-0 z-10 bg-white text-left py-2 pr-3">document_key</th>
                            <th class="sticky top-0 z-10 bg-white text-left py-2 pr-3">event_type</th>
                            <th class="sticky top-0 z-10 bg-white text-left py-2 pr-3">view_ms</th>
                            <th class="sticky top-0 z-10 bg-white text-left py-2 pr-3">event_order</th>
                            <th class="sticky top-0 z-10 bg-white text-left py-2 pr-3">display_order</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($participantEventRows)): ?>
                            <tr><td class="py-3 text-slate-500" colspan="7">No document events found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($participantEventRows as $eventRow): ?>
                                <tr class="border-b border-slate-100 odd:bg-slate-50 last:border-b-0">
                                    <td class="py-2 pr-3"><?= e(format_dashboard_datetime((string) ($eventRow['event_time'] ?? ''))) ?></td>
                                    <td class="py-2 pr-3"><?= e((string) $eventRow['task_number']) ?></td>
                                    <td class="py-2 pr-3"><?= e((string) $eventRow['document_key']) ?></td>
                                    <td class="py-2 pr-3"><?= e((string) $eventRow['event_type']) ?></td>
                                    <td class="py-2 pr-3"><?= e((string) ($eventRow['view_ms'] ?? '')) ?></td>
                                    <td class="py-2 pr-3"><?= e((string) ($eventRow['event_order'] ?? '')) ?></td>
                                    <td class="py-2 pr-3"><?= e((string) ($eventRow['display_order'] ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>

            <section class="bg-white shadow rounded-xl p-6 overflow-x-auto">
                <h3 class="text-base font-semibold text-slate-800 mb-3">Post-Survey Response</h3>
                <?php if ($participantPostsurvey === null): ?>
                    <p class="text-slate-500 text-sm">No post-survey response found.</p>
                <?php else: ?>
                    <table class="min-w-full text-sm">
                        <tbody>
                            <?php foreach ($participantPostsurvey as $field => $value): ?>
                                <tr class="border-b border-slate-100 odd:bg-slate-50 last:border-b-0">
                                    <td class="py-2 pr-4 font-medium text-slate-700 whitespace-nowrap"><?= e((string) $field) ?></td>
                                    <td class="py-2 text-slate-700">
                                        <?php
                                        $rawValue = (string) ($value ?? '');
                                        $displayValue = $rawValue;
                                        if ($rawValue !== '' && str_ends_with((string) $field, '_at')) {
                                            $displayValue = format_dashboard_datetime($rawValue);
                                        }
                                        ?>
                                        <?= e($displayValue) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</main>

<script>
(() => {
    const updateSelectionState = (checkboxSelector, counterId, submitButtonId, selectAllId) => {
        const checkboxes = Array.from(document.querySelectorAll(checkboxSelector));
        const selectedCount = checkboxes.filter((checkbox) => checkbox.checked).length;

        const counter = document.getElementById(counterId);
        if (counter) {
            counter.textContent = String(selectedCount);
        }

        const submitButton = document.getElementById(submitButtonId);
        if (submitButton) {
            submitButton.disabled = selectedCount === 0;
            submitButton.classList.toggle('opacity-50', selectedCount === 0);
            submitButton.classList.toggle('cursor-not-allowed', selectedCount === 0);
        }

        const selectAll = document.getElementById(selectAllId);
        if (selectAll && checkboxes.length > 0) {
            const allChecked = checkboxes.every((checkbox) => checkbox.checked);
            selectAll.checked = allChecked;
        }
    };

    const selectAllData = document.getElementById('select-all-data');
    if (selectAllData) {
        selectAllData.addEventListener('change', () => {
            document.querySelectorAll('.data-row-checkbox').forEach((checkbox) => {
                checkbox.checked = selectAllData.checked;
            });
            updateSelectionState('.data-row-checkbox', 'data-selected-count', 'bulk-data-submit', 'select-all-data');
        });
    }
    document.querySelectorAll('.data-row-checkbox').forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
            updateSelectionState('.data-row-checkbox', 'data-selected-count', 'bulk-data-submit', 'select-all-data');
        });
    });
    updateSelectionState('.data-row-checkbox', 'data-selected-count', 'bulk-data-submit', 'select-all-data');

    const selectAllTrash = document.getElementById('select-all-trash');
    if (selectAllTrash) {
        selectAllTrash.addEventListener('change', () => {
            document.querySelectorAll('.trash-row-checkbox').forEach((checkbox) => {
                checkbox.checked = selectAllTrash.checked;
            });
            updateSelectionState('.trash-row-checkbox', 'trash-selected-count', 'bulk-trash-submit', 'select-all-trash');
        });
    }
    document.querySelectorAll('.trash-row-checkbox').forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
            updateSelectionState('.trash-row-checkbox', 'trash-selected-count', 'bulk-trash-submit', 'select-all-trash');
        });
    });
    updateSelectionState('.trash-row-checkbox', 'trash-selected-count', 'bulk-trash-submit', 'select-all-trash');

    const initInteractiveAnalysisTable = ({
        tableId,
        selectId,
        selectedColumnLabelId,
        openFiltersId,
        closeFiltersId,
        filterModalId,
        filterFieldsId,
        filterCountId,
        moveFirstId,
        moveLeftId,
        moveRightId,
        moveLastId,
        clearFiltersId,
        resetId,
    }) => {
        const table = document.getElementById(tableId);
        if (!table || !table.tHead || table.tBodies.length === 0) {
            return;
        }

        const thead = table.tHead;
        const tbody = table.tBodies[0];
        const headerRow = thead.rows[0] || null;
        const columnSelect = document.getElementById(selectId);
        const selectedColumnLabel = document.getElementById(selectedColumnLabelId);
        const openFiltersButton = document.getElementById(openFiltersId);
        const closeFiltersButton = document.getElementById(closeFiltersId);
        const filterModal = document.getElementById(filterModalId);
        const filterFields = document.getElementById(filterFieldsId);
        const filterCountLabel = document.getElementById(filterCountId);
        const moveFirstButton = document.getElementById(moveFirstId);
        const moveLeftButton = document.getElementById(moveLeftId);
        const moveRightButton = document.getElementById(moveRightId);
        const moveLastButton = document.getElementById(moveLastId);
        const clearFiltersButton = document.getElementById(clearFiltersId);
        const resetButton = document.getElementById(resetId);
        if (
            !headerRow
            || !columnSelect
            || !openFiltersButton
            || !closeFiltersButton
            || !filterModal
            || !filterFields
            || !moveFirstButton
            || !moveLeftButton
            || !moveRightButton
            || !moveLastButton
            || !clearFiltersButton
            || !resetButton
        ) {
            return;
        }

        const originalOrder = Array.from(headerRow.cells, (cell) => cell.textContent ? cell.textContent.trim() : '');
        const getBodyRows = () => Array.from(tbody.rows).filter((row) => row.cells.length === headerRow.cells.length);
        const parseNumericValue = (rawValue) => {
            const normalized = String(rawValue || '')
                .replace(/,/g, '')
                .replace(/%/g, '')
                .trim();
            if (normalized === '') {
                return null;
            }
            const parsed = Number(normalized);
            return Number.isFinite(parsed) ? parsed : null;
        };

        const getColumnIndexByLabel = (label) => Array.from(headerRow.cells).findIndex(
            (cell) => (cell.textContent || '').trim() === label
        );
        const filterControls = [];
        Array.from(headerRow.cells).forEach((cell, columnIndex) => {
            const columnLabel = (cell.textContent || '').trim();
            const fieldRow = document.createElement('div');
            fieldRow.className = 'grid gap-2 md:grid-cols-[minmax(180px,240px)_1fr] md:items-center';

            const fieldLabel = document.createElement('label');
            fieldLabel.className = 'text-xs font-medium text-slate-700';
            fieldLabel.textContent = columnLabel;
            fieldRow.appendChild(fieldLabel);

            const inputWrap = document.createElement('div');
            const columnValues = getBodyRows().map((row) => (row.cells[columnIndex]?.textContent || '').trim());
            const nonEmptyValues = columnValues.filter((value) => value !== '');
            const numericValues = nonEmptyValues
                .map((value) => parseNumericValue(value))
                .filter((value) => value !== null);
            const isNumericColumn = nonEmptyValues.length > 0 && numericValues.length === nonEmptyValues.length;
            const uniqueValues = Array.from(new Set(nonEmptyValues)).sort((a, b) => a.localeCompare(b));

            if (isNumericColumn) {
                inputWrap.className = 'grid grid-cols-2 gap-2';
                const minInput = document.createElement('input');
                minInput.type = 'number';
                minInput.step = 'any';
                minInput.className = 'w-full rounded border border-slate-300 px-2 py-1.5 text-xs text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-300';
                minInput.placeholder = 'Min';
                const maxInput = document.createElement('input');
                maxInput.type = 'number';
                maxInput.step = 'any';
                maxInput.className = 'w-full rounded border border-slate-300 px-2 py-1.5 text-xs text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-300';
                maxInput.placeholder = 'Max';
                inputWrap.appendChild(minInput);
                inputWrap.appendChild(maxInput);
                filterControls.push({ type: 'numeric', columnLabel, minInput, maxInput });
            } else if (uniqueValues.length > 0 && uniqueValues.length <= 10) {
                inputWrap.className = '';
                const select = document.createElement('select');
                select.className = 'w-full rounded border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-300';
                const allOption = document.createElement('option');
                allOption.value = '';
                allOption.textContent = 'All values';
                select.appendChild(allOption);
                uniqueValues.forEach((value) => {
                    const option = document.createElement('option');
                    option.value = value.toLowerCase();
                    option.textContent = value;
                    select.appendChild(option);
                });
                inputWrap.appendChild(select);
                filterControls.push({ type: 'select', columnLabel, select });
            } else {
                inputWrap.className = '';
                const input = document.createElement('input');
                input.type = 'text';
                input.className = 'w-full rounded border border-slate-300 px-2 py-1.5 text-xs text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-300';
                input.placeholder = 'Search...';
                inputWrap.appendChild(input);
                filterControls.push({ type: 'text', columnLabel, input });
            }

            fieldRow.appendChild(inputWrap);
            filterFields.appendChild(fieldRow);
        });

        const applyFilters = () => {
            const allRows = Array.from(tbody.rows);
            const dataRows = allRows.filter((row) => row.cells.length === headerRow.cells.length);
            const messageRow = allRows.find((row) => row.cells.length !== headerRow.cells.length) || null;
            let visibleCount = 0;
            dataRows.forEach((row) => {
                let rowVisible = true;
                filterControls.forEach((control) => {
                    if (!rowVisible) {
                        return;
                    }
                    const index = getColumnIndexByLabel(control.columnLabel);
                    if (index < 0) {
                        return;
                    }
                    const rawCellValue = (row.cells[index]?.textContent || '').trim();
                    const normalizedCellValue = rawCellValue.toLowerCase();
                    if (control.type === 'select') {
                        const selected = (control.select.value || '').trim();
                        if (selected !== '' && normalizedCellValue !== selected) {
                            rowVisible = false;
                        }
                        return;
                    }
                    if (control.type === 'numeric') {
                        const minInput = control.minInput;
                        const maxInput = control.maxInput;
                        const numericValue = parseNumericValue(rawCellValue);
                        if (numericValue === null) {
                            if ((minInput && minInput.value !== '') || (maxInput && maxInput.value !== '')) {
                                rowVisible = false;
                            }
                            return;
                        }
                        if (minInput && minInput.value !== '' && numericValue < Number(minInput.value)) {
                            rowVisible = false;
                            return;
                        }
                        if (maxInput && maxInput.value !== '' && numericValue > Number(maxInput.value)) {
                            rowVisible = false;
                        }
                        return;
                    }
                    const query = control.input.value.trim().toLowerCase();
                    if (query !== '' && !normalizedCellValue.includes(query)) {
                        rowVisible = false;
                    }
                });
                row.style.display = rowVisible ? '' : 'none';
                if (rowVisible) {
                    visibleCount++;
                }
            });
            if (messageRow) {
                if (dataRows.length === 0 || visibleCount === 0) {
                    messageRow.style.display = '';
                    if (dataRows.length > 0 && messageRow.cells.length > 0) {
                        messageRow.cells[0].textContent = 'No rows match current filters.';
                    }
                } else {
                    messageRow.style.display = 'none';
                }
            }

            let activeFilterCount = 0;
            filterControls.forEach((control) => {
                if (control.type === 'numeric') {
                    if (control.minInput.value !== '' || control.maxInput.value !== '') {
                        activeFilterCount++;
                    }
                } else if (control.type === 'select') {
                    if ((control.select.value || '') !== '') {
                        activeFilterCount++;
                    }
                } else if ((control.input.value || '').trim() !== '') {
                    activeFilterCount++;
                }
            });
            if (filterCountLabel) {
                filterCountLabel.textContent = `${activeFilterCount} active`;
            }
        };

        const syncColumnSelector = () => {
            const selectedText = columnSelect.value;
            const currentLabels = Array.from(headerRow.cells, (cell) => cell.textContent ? cell.textContent.trim() : '');
            columnSelect.innerHTML = '';
            currentLabels.forEach((label) => {
                const option = document.createElement('option');
                option.value = label;
                option.textContent = label;
                columnSelect.appendChild(option);
            });
            if (selectedText && currentLabels.includes(selectedText)) {
                columnSelect.value = selectedText;
            } else if (currentLabels.length > 0) {
                columnSelect.value = currentLabels[0];
            }
            const selectedIndex = getSelectedIndex();
            const selectedLabel = columnSelect.value || '-';
            if (selectedColumnLabel) {
                selectedColumnLabel.textContent = `Selected column: ${selectedLabel}`;
            }
            const atStart = selectedIndex <= 0;
            const atEnd = selectedIndex < 0 || selectedIndex >= headerRow.cells.length - 1;
            [moveFirstButton, moveLeftButton].forEach((button) => {
                button.disabled = atStart;
                button.classList.toggle('opacity-50', atStart);
                button.classList.toggle('cursor-not-allowed', atStart);
            });
            [moveRightButton, moveLastButton].forEach((button) => {
                button.disabled = atEnd;
                button.classList.toggle('opacity-50', atEnd);
                button.classList.toggle('cursor-not-allowed', atEnd);
            });
        };

        const moveColumn = (fromIndex, toIndex) => {
            if (fromIndex < 0 || toIndex < 0 || fromIndex === toIndex) {
                return;
            }
            const rows = [...Array.from(thead.rows), ...Array.from(tbody.rows)];
            rows.forEach((row) => {
                if (row.cells.length <= Math.max(fromIndex, toIndex)) {
                    return;
                }
                const sourceCell = row.cells[fromIndex];
                const targetCell = row.cells[toIndex];
                if (!sourceCell || !targetCell) {
                    return;
                }
                if (fromIndex < toIndex) {
                    row.insertBefore(sourceCell, targetCell.nextSibling);
                } else {
                    row.insertBefore(sourceCell, targetCell);
                }
            });
        };

        const getSelectedIndex = () => {
            const selectedLabel = columnSelect.value;
            return Array.from(headerRow.cells).findIndex((cell) => (cell.textContent || '').trim() === selectedLabel);
        };

        moveLeftButton.addEventListener('click', () => {
            const currentIndex = getSelectedIndex();
            if (currentIndex <= 0) {
                return;
            }
            moveColumn(currentIndex, currentIndex - 1);
            syncColumnSelector();
            applyFilters();
        });

        moveRightButton.addEventListener('click', () => {
            const currentIndex = getSelectedIndex();
            if (currentIndex < 0 || currentIndex >= headerRow.cells.length - 1) {
                return;
            }
            moveColumn(currentIndex, currentIndex + 1);
            syncColumnSelector();
            applyFilters();
        });

        moveFirstButton.addEventListener('click', () => {
            const currentIndex = getSelectedIndex();
            if (currentIndex <= 0) {
                return;
            }
            moveColumn(currentIndex, 0);
            syncColumnSelector();
            applyFilters();
        });

        moveLastButton.addEventListener('click', () => {
            const currentIndex = getSelectedIndex();
            const lastIndex = headerRow.cells.length - 1;
            if (currentIndex < 0 || currentIndex >= lastIndex) {
                return;
            }
            moveColumn(currentIndex, lastIndex);
            syncColumnSelector();
            applyFilters();
        });

        const clearAllFilters = () => {
            filterControls.forEach((control) => {
                if (control.type === 'numeric') {
                    control.minInput.value = '';
                    control.maxInput.value = '';
                } else if (control.type === 'select') {
                    control.select.value = '';
                } else {
                    control.input.value = '';
                }
            });
        };

        clearFiltersButton.addEventListener('click', () => {
            clearAllFilters();
            applyFilters();
        });

        resetButton.addEventListener('click', () => {
            originalOrder.forEach((label, targetIndex) => {
                const currentIndex = Array.from(headerRow.cells).findIndex((cell) => (cell.textContent || '').trim() === label);
                if (currentIndex >= 0 && currentIndex !== targetIndex) {
                    moveColumn(currentIndex, targetIndex);
                }
            });
            clearAllFilters();
            syncColumnSelector();
            applyFilters();
        });

        filterControls.forEach((control) => {
            if (control.type === 'numeric') {
                control.minInput.addEventListener('input', applyFilters);
                control.maxInput.addEventListener('input', applyFilters);
            } else if (control.type === 'select') {
                control.select.addEventListener('change', applyFilters);
            } else {
                control.input.addEventListener('input', applyFilters);
            }
        });

        const openFilterModal = () => {
            filterModal.classList.remove('hidden');
            filterModal.classList.add('flex');
        };
        const closeFilterModal = () => {
            filterModal.classList.add('hidden');
            filterModal.classList.remove('flex');
        };
        openFiltersButton.addEventListener('click', openFilterModal);
        closeFiltersButton.addEventListener('click', closeFilterModal);
        filterModal.addEventListener('click', (event) => {
            if (event.target === filterModal) {
                closeFilterModal();
            }
        });

        columnSelect.addEventListener('change', syncColumnSelector);

        syncColumnSelector();
        applyFilters();
    };

    initInteractiveAnalysisTable({
        tableId: 'data-analysis-table',
        selectId: 'data-analysis-column-select',
        selectedColumnLabelId: 'data-analysis-selected-column',
        openFiltersId: 'data-analysis-open-filters',
        closeFiltersId: 'data-analysis-close-filters',
        filterModalId: 'data-analysis-filter-modal',
        filterFieldsId: 'data-analysis-filter-fields',
        filterCountId: 'data-analysis-filter-count',
        moveFirstId: 'data-analysis-move-first',
        moveLeftId: 'data-analysis-move-left',
        moveRightId: 'data-analysis-move-right',
        moveLastId: 'data-analysis-move-last',
        clearFiltersId: 'data-analysis-clear-filters',
        resetId: 'data-analysis-reset',
    });
    initInteractiveAnalysisTable({
        tableId: 'data-analysis-all-table',
        selectId: 'data-analysis-all-column-select',
        selectedColumnLabelId: 'data-analysis-all-selected-column',
        openFiltersId: 'data-analysis-all-open-filters',
        closeFiltersId: 'data-analysis-all-close-filters',
        filterModalId: 'data-analysis-all-filter-modal',
        filterFieldsId: 'data-analysis-all-filter-fields',
        filterCountId: 'data-analysis-all-filter-count',
        moveFirstId: 'data-analysis-all-move-first',
        moveLeftId: 'data-analysis-all-move-left',
        moveRightId: 'data-analysis-all-move-right',
        moveLastId: 'data-analysis-all-move-last',
        clearFiltersId: 'data-analysis-all-clear-filters',
        resetId: 'data-analysis-all-reset',
    });
    initInteractiveAnalysisTable({
        tableId: 'data-not-finished-table',
        selectId: 'data-not-finished-column-select',
        selectedColumnLabelId: 'data-not-finished-selected-column',
        openFiltersId: 'data-not-finished-open-filters',
        closeFiltersId: 'data-not-finished-close-filters',
        filterModalId: 'data-not-finished-filter-modal',
        filterFieldsId: 'data-not-finished-filter-fields',
        filterCountId: 'data-not-finished-filter-count',
        moveFirstId: 'data-not-finished-move-first',
        moveLeftId: 'data-not-finished-move-left',
        moveRightId: 'data-not-finished-move-right',
        moveLastId: 'data-not-finished-move-last',
        clearFiltersId: 'data-not-finished-clear-filters',
        resetId: 'data-not-finished-reset',
    });
})();
</script>

<?php require __DIR__ . '/../views/footer.php'; ?>
