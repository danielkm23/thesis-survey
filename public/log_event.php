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
$eventType = (string) ($payload['event_type'] ?? '');
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

if (!in_array($eventType, ['open', 'close'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid event_type.']);
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
    $stmt = db()->prepare(
        'INSERT INTO document_events
            (participant_id, task_number, document_key, event_type, event_time, view_ms, event_order, display_order)
         VALUES
            (:participant_id, :task_number, :document_key, :event_type, :event_time, :view_ms, :event_order, :display_order)'
    );

    $stmt->execute([
        ':participant_id' => $participantId,
        ':task_number' => $taskNumber,
        ':document_key' => $documentKey,
        ':event_type' => $eventType,
        ':event_time' => date('Y-m-d H:i:s'),
        ':view_ms' => $viewMs,
        ':event_order' => $eventOrder,
        ':display_order' => $displayOrder,
    ]);

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
