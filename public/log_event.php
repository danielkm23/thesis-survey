<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (!has_valid_participant_session()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid participant session.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput ?: '', true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON payload.']);
    exit;
}

$taskNumber = filter_var($payload['task_number'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$documentKey = (string) ($payload['document_key'] ?? '');
$documentTitle = trim((string) ($payload['document_title'] ?? ''));
$eventType = (string) ($payload['event_type'] ?? '');
$conditionName = trim((string) ($payload['condition_name'] ?? ''));
$viewMs = $payload['view_ms'] ?? null;
$eventOrder = $payload['event_order'] ?? null;
$displayOrder = $payload['display_order'] ?? null;
$isRelevant = $payload['is_relevant'] ?? null;

if ($taskNumber === false || $taskNumber === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid task_number.']);
    exit;
}

if (!preg_match('/^[a-zA-Z0-9_-]{1,100}$/', $documentKey)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid document_key.']);
    exit;
}

if ($documentTitle !== '' && mb_strlen($documentTitle) > 255) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid document_title.']);
    exit;
}

if (!in_array($eventType, ['open', 'close'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid event_type.']);
    exit;
}

if ($conditionName !== '' && !in_array($conditionName, ['control', 'passive', 'active'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid condition_name.']);
    exit;
}

if ($viewMs !== null) {
    $viewMs = filter_var($viewMs, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    if ($viewMs === false) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid view_ms.']);
        exit;
    }
}

if ($eventOrder !== null) {
    $eventOrder = filter_var($eventOrder, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($eventOrder === false) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid event_order.']);
        exit;
    }
}

if ($displayOrder !== null) {
    $displayOrder = filter_var($displayOrder, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($displayOrder === false) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid display_order.']);
        exit;
    }
}

if ($isRelevant !== null && !is_bool($isRelevant)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid is_relevant.']);
    exit;
}

$participantId = (int) session_get('participant_id');

try {
    $pdo = db();
    $hasDocumentTitleColumn = false;
    $hasIsRelevantColumn = false;
    $hasConditionNameColumn = false;
    try {
        $documentTitleCheck = $pdo->query("SHOW COLUMNS FROM document_events LIKE 'document_title'");
        $hasDocumentTitleColumn = $documentTitleCheck !== false && $documentTitleCheck->fetch() !== false;
        $isRelevantCheck = $pdo->query("SHOW COLUMNS FROM document_events LIKE 'is_relevant'");
        $hasIsRelevantColumn = $isRelevantCheck !== false && $isRelevantCheck->fetch() !== false;
        $conditionNameCheck = $pdo->query("SHOW COLUMNS FROM document_events LIKE 'condition_name'");
        $hasConditionNameColumn = $conditionNameCheck !== false && $conditionNameCheck->fetch() !== false;
    } catch (Throwable $e) {
        $hasDocumentTitleColumn = false;
        $hasIsRelevantColumn = false;
        $hasConditionNameColumn = false;
    }

    $columns = ['participant_id', 'task_number', 'document_key'];
    $params = [
        ':participant_id' => $participantId,
        ':task_number' => $taskNumber,
        ':document_key' => $documentKey,
    ];
    if ($hasDocumentTitleColumn) {
        $columns[] = 'document_title';
        $params[':document_title'] = $documentTitle !== '' ? $documentTitle : null;
    }
    if ($hasIsRelevantColumn) {
        $columns[] = 'is_relevant';
        $params[':is_relevant'] = $isRelevant === null ? null : ($isRelevant ? 1 : 0);
    }
    if ($hasConditionNameColumn) {
        $columns[] = 'condition_name';
        $params[':condition_name'] = $conditionName !== '' ? $conditionName : null;
    }
    $columns = array_merge($columns, ['event_type', 'event_time', 'view_ms', 'event_order', 'display_order']);
    $params = array_merge($params, [
        ':event_type' => $eventType,
        ':event_time' => date('Y-m-d H:i:s'),
        ':view_ms' => $viewMs,
        ':event_order' => $eventOrder,
        ':display_order' => $displayOrder,
    ]);
    $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
    $stmt = $pdo->prepare(
        'INSERT INTO document_events (' . implode(', ', $columns) . ')
         VALUES (' . implode(', ', $placeholders) . ')'
    );
    $stmt->execute($params);

    // Lightweight session flags; database remains the main source of truth.
    if ($eventType === 'open') {
        session_set('task_' . $taskNumber . '_open_event_order', $eventOrder ?? 0);
        session_set('task_' . $taskNumber . '_document_opened', true);
        if ($isRelevant === true) {
            session_set('task_' . $taskNumber . '_relevant_document_opened', true);
        }
    }

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to log event.']);
}
