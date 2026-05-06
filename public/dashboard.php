<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/db.php';

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

        $trashPayload = json_encode([
            'participant' => $participantRow,
            'task_responses' => $taskRows,
            'document_events' => $eventRows,
            'postsurvey_responses' => $postsurveyRows,
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
        $deleteParticipantStmt = $pdo->prepare('DELETE FROM participants WHERE id = :id');

        $deleteDocumentEventsStmt->execute([':participant_id' => $deleteRowId]);
        $deleteTaskResponsesStmt->execute([':participant_id' => $deleteRowId]);
        $deletePostsurveyStmt->execute([':participant_id' => $deleteRowId]);
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
if (!in_array($currentTab, ['overview', 'data', 'participant', 'trash'], true)) {
    $currentTab = 'overview';
}

$allowedDataTables = [
    'participants',
    'task_responses',
    'document_events',
    'postsurvey_responses',
];
$selectedTable = (string) ($_GET['table'] ?? 'participants');
if (!in_array($selectedTable, $allowedDataTables, true)) {
    $selectedTable = 'participants';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dashboard_action'])) {
    $dashboardAction = (string) ($_POST['dashboard_action'] ?? '');
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

if ($currentTab === 'data') {
    $columnsStmt = $pdo->query('SHOW COLUMNS FROM ' . $selectedTable);
    foreach ($columnsStmt->fetchAll() as $columnRow) {
        $dataColumns[] = (string) $columnRow['Field'];
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

    $rowsStmt = $pdo->prepare(
        'SELECT * FROM ' . $selectedTable . ' ORDER BY `' . $sortColumn . '` ' . strtoupper($sortDirection) . ' LIMIT :limit OFFSET :offset'
    );
    $rowsStmt->bindValue(':limit', $rowsPerPage, PDO::PARAM_INT);
    $rowsStmt->bindValue(':offset', $dataOffset, PDO::PARAM_INT);
    $rowsStmt->execute();
    $dataRows = $rowsStmt->fetchAll();
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
);
$participantSummary = $participantSummaryStmt->fetch() ?: [
    'total_respondents' => 0,
    'completed_respondents' => 0,
];

/**
 * Per-condition participant counts and completion.
 */
$conditionCountsStmt = $pdo->query(
    'SELECT
        condition_name,
        COUNT(*) AS respondents,
        SUM(CASE WHEN completed_at IS NOT NULL THEN 1 ELSE 0 END) AS completed
     FROM participants
     GROUP BY condition_name
     ORDER BY condition_name'
);
$conditionRows = $conditionCountsStmt->fetchAll();

$conditionNames = [];
$respondentsByCondition = [];
$completionByCondition = [];
foreach ($conditionRows as $row) {
    $condition = (string) $row['condition_name'];
    $respondents = (int) $row['respondents'];
    $completed = (int) $row['completed'];
    $conditionNames[] = $condition;
    $respondentsByCondition[$condition] = $respondents;
    $completionByCondition[$condition] = $respondents > 0
        ? ($completed / $respondents) * 100.0
        : 0.0;
}

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
            $isCorrectDecision = ($aiCorrect === 1 && $relianceChoice !== 'did_not_use')
                || ($aiCorrect === 0 && $relianceChoice === 'did_not_use');

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
$taskRowsStmt = $pdo->query(
    'SELECT
        p.condition_name,
        tr.participant_id,
        tr.task_number,
        tr.ai_correct,
        tr.reliance_choice,
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

    // Decision correctness proxy using available fields:
    // - If AI output is correct, relying on it is counted as correct.
    // - If AI output is incorrect, not using it is counted as correct.
    $isCorrectDecision = ($aiCorrect === 1 && $relianceChoice !== 'did_not_use')
        || ($aiCorrect === 0 && $relianceChoice === 'did_not_use');
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

$pageTitle = 'Internal Dashboard';
require __DIR__ . '/../views/header.php';
?>

<main class="max-w-6xl mx-auto px-4 py-8">
    <section class="mb-6 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 mb-1">Internal Survey Dashboard</h1>
            <p class="text-slate-600 text-sm">Internal monitoring view for thesis supervision.</p>
        </div>
        <a
            href="/dashboard/?logout=1"
            class="inline-block bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-3 py-2 rounded-lg transition"
        >
            Log out
        </a>
    </section>

    <section class="mb-6">
        <div class="inline-flex rounded-lg border border-slate-200 bg-white p-1">
            <a
                href="/dashboard/?tab=overview"
                class="px-3 py-1.5 text-sm rounded-md transition <?= $currentTab === 'overview' ? 'accent-bg text-white' : 'text-slate-700 hover:bg-slate-100' ?>"
            >
                Overview
            </a>
            <a
                href="/dashboard/?tab=data"
                class="px-3 py-1.5 text-sm rounded-md transition <?= $currentTab === 'data' ? 'accent-bg text-white' : 'text-slate-700 hover:bg-slate-100' ?>"
            >
                Full Data
            </a>
            <a
                href="/dashboard/?tab=trash"
                class="px-3 py-1.5 text-sm rounded-md transition <?= $currentTab === 'trash' ? 'accent-bg text-white' : 'text-slate-700 hover:bg-slate-100' ?>"
            >
                Trash
            </a>
            <?php if ($currentTab === 'participant' && $participantDetailId !== false && $participantDetailId !== null): ?>
                <a
                    href="/dashboard/?tab=participant&participant_id=<?= e((string) $participantDetailId) ?>"
                    class="px-3 py-1.5 text-sm rounded-md transition accent-bg text-white"
                >
                    Participant <?= e((string) $participantDetailId) ?>
                </a>
            <?php endif; ?>
        </div>
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
        <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
            <article class="bg-white shadow rounded-xl p-5">
                <p class="text-sm text-slate-500">Total respondents</p>
                <p class="text-3xl font-bold text-slate-800 mt-1"><?= e((string) $totalRespondents) ?></p>
            </article>
            <article class="bg-white shadow rounded-xl p-5">
                <p class="text-sm text-slate-500">Completed respondents</p>
                <p class="text-3xl font-bold text-slate-800 mt-1"><?= e((string) $completedRespondents) ?></p>
            </article>
            <article class="bg-white shadow rounded-xl p-5">
                <p class="text-sm text-slate-500">Completion rate</p>
                <p class="text-3xl font-bold text-slate-800 mt-1"><?= e(number_format($completionRate, 1)) ?>%</p>
            </article>
            <article class="bg-white shadow rounded-xl p-5">
                <p class="text-sm text-slate-500">Average confidence</p>
                <p class="text-3xl font-bold text-slate-800 mt-1"><?= e(number_format($overallAvgConfidence, 2)) ?></p>
                <p class="text-xs text-slate-500 mt-1">Based on task responses (1-5 scale)</p>
            </article>
        </section>

        <section class="bg-white shadow rounded-xl p-6 mb-6">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Respondents Per Condition</h2>
            <div class="space-y-3">
                <?php $maxRespondents = max([1, ...array_values($respondentsByCondition)]); ?>
                <?php foreach ($conditionNames as $condition): ?>
                    <?php
                    $value = $respondentsByCondition[$condition] ?? 0;
                    $width = ($value / $maxRespondents) * 100.0;
                    ?>
                    <div>
                        <div class="flex items-center justify-between text-sm mb-1">
                            <span class="font-medium text-slate-700"><?= e($condition) ?></span>
                            <span class="text-slate-600"><?= e((string) $value) ?></span>
                        </div>
                        <div class="w-full h-3 bg-slate-100 rounded">
                        <div class="h-3 accent-bg rounded" style="width: <?= e(number_format($width, 2, '.', '')) ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="bg-white shadow rounded-xl p-6 overflow-x-auto">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Condition Metrics</h2>
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-600">
                        <th class="text-left py-2 pr-4">Condition</th>
                        <th class="text-right py-2 px-2">Completion rate</th>
                        <th class="text-right py-2 px-2">Avg docs opened</th>
                        <th class="text-right py-2 px-2">Avg inspection time (s)</th>
                        <th class="text-right py-2 px-2">% opened relevant doc</th>
                        <th class="text-right py-2 px-2">Decision correctness (%)</th>
                        <th class="text-right py-2 pl-2">Avg confidence</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($conditionNames as $condition): ?>
                        <tr class="border-b border-slate-100 odd:bg-slate-50 last:border-b-0">
                            <td class="py-2 pr-4 font-medium text-slate-800"><?= e($condition) ?></td>
                            <td class="py-2 px-2 text-right text-slate-700"><?= e(number_format($completionByCondition[$condition] ?? 0.0, 1)) ?>%</td>
                            <td class="py-2 px-2 text-right text-slate-700"><?= e(number_format($avgDocsOpenedByCondition[$condition] ?? 0.0, 2)) ?></td>
                            <td class="py-2 px-2 text-right text-slate-700"><?= e(number_format($avgInspectionSecondsByCondition[$condition] ?? 0.0, 2)) ?></td>
                            <td class="py-2 px-2 text-right text-slate-700"><?= e(number_format($openedRelevantPctByCondition[$condition] ?? 0.0, 1)) ?>%</td>
                            <td class="py-2 px-2 text-right text-slate-700"><?= e(number_format($decisionCorrectPctByCondition[$condition] ?? 0.0, 1)) ?>%</td>
                            <td class="py-2 pl-2 text-right text-slate-700"><?= e(number_format($avgConfidenceByCondition[$condition] ?? 0.0, 2)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="text-xs text-slate-500 mt-4">
                Decision correctness is a proxy derived from available fields in <code>task_responses</code>:
                using AI when <code>ai_correct=1</code>, and not using AI when <code>ai_correct=0</code>.
            </p>
        </section>
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
                    href="/export_csv.php?table=<?= e($selectedTable) ?>"
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
                    <p class="text-sm text-slate-500">Correct total (0-4)</p>
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
})();
</script>

<?php require __DIR__ . '/../views/footer.php'; ?>
