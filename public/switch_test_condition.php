<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

if (!has_valid_participant_session()) {
    redirect('index.php');
}

if (!TEST_MODE_ENABLED || !(bool) session_get('is_test_participant', false)) {
    http_response_code(403);
    exit('Test condition switching is only available in test mode.');
}

$csrfToken = trim((string) ($_POST['csrf_token'] ?? ''));
$csrfSessionToken = (string) session_get('test_condition_switch_csrf', '');
if ($csrfToken === '' || $csrfSessionToken === '' || !hash_equals($csrfSessionToken, $csrfToken)) {
    http_response_code(403);
    exit('Invalid security token.');
}

$requestedCondition = trim((string) ($_POST['test_condition'] ?? ''));
$allowedConditions = ['control', 'passive', 'active'];
if (!in_array($requestedCondition, $allowedConditions, true)) {
    http_response_code(400);
    exit('Invalid test condition.');
}

$pdo = db();
$participantId = null;
$participantCode = '';
$startedAt = date('Y-m-d H:i:s');

for ($attempt = 0; $attempt < 5; $attempt++) {
    $participantCode = TEST_PARTICIPANT_PREFIX . strtoupper(bin2hex(random_bytes(4)));
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO participants (participant_code, condition_name, started_at, completed_at)
             VALUES (:participant_code, :condition_name, :started_at, NULL)'
        );
        $stmt->execute([
            ':participant_code' => $participantCode,
            ':condition_name' => $requestedCondition,
            ':started_at' => $startedAt,
        ]);
        $participantId = (int) $pdo->lastInsertId();
        break;
    } catch (PDOException $e) {
        if ($e->getCode() !== '23000') {
            throw $e;
        }
    }
}

if ($participantId === null) {
    http_response_code(500);
    exit('Could not start test participant. Please try again.');
}

clear_participant_session_state();
session_set('participant_id', $participantId);
session_set('participant_code', $participantCode);
session_set('condition_name', $requestedCondition);
session_set('is_test_participant', true);

redirect('intro.php');
