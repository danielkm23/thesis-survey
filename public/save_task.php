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

$taskNumber = filter_input(INPUT_POST, 'task_number', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

$allowedRelianceChoices = [
    'use_exact',
    'use_small_changes',
    'use_substantial_changes',
    'did_not_use',
];

$relianceChoice = (string) ($_POST['reliance_choice'] ?? '');
$finalResponse = trim((string) ($_POST['final_response'] ?? ''));
$confidence = filter_input(INPUT_POST, 'confidence', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 5],
]);

if ($taskNumber === false || $taskNumber === null) {
    http_response_code(400);
    exit('Missing or invalid field: task_number.');
}

if (!in_array($relianceChoice, $allowedRelianceChoices, true)) {
    http_response_code(400);
    exit('Missing or invalid field: reliance_choice.');
}

if ($finalResponse === '') {
    http_response_code(400);
    exit('Missing required field: final_response.');
}

if ($confidence === false || $confidence === null) {
    http_response_code(400);
    exit('Missing or invalid field: confidence (must be 1-5).');
}

$tasks = require __DIR__ . '/../data/tasks.php';
$enabledTaskNumbers = [1, 2]; // Temporary MVP gate
if (!in_array($taskNumber, $enabledTaskNumbers, true) || !isset($tasks[$taskNumber])) {
    http_response_code(400);
    exit('Task not found.');
}

$conditionName = (string) session_get('condition_name', '');
$verificationIntention = null;
if ($conditionName === 'active') {
    $allowedVerificationIntentions = [
        'specific_claim_or_number',
        'policy_rule_or_requirement',
        'overall_recommendation',
        'would_not_verify',
    ];
    $verificationIntention = (string) ($_POST['verification_intention'] ?? '');
    if (!in_array($verificationIntention, $allowedVerificationIntentions, true)) {
        http_response_code(400);
        exit('Missing or invalid field: verification_intention for active condition.');
    }
}

$aiCorrect = !empty($tasks[$taskNumber]['ai_correct']) ? 1 : 0;
$participantId = (int) session_get('participant_id');
$taskStartedAt = session_get('task_' . $taskNumber . '_started_at');
if (!is_string($taskStartedAt) || $taskStartedAt === '') {
    http_response_code(400);
    exit('Missing required field: task start timestamp.');
}

$taskStartedTs = strtotime($taskStartedAt);
if ($taskStartedTs === false) {
    http_response_code(400);
    exit('Invalid task start timestamp.');
}

$taskSubmittedTs = time();
$elapsedSeconds = $taskSubmittedTs - $taskStartedTs;
if ($elapsedSeconds < 3) {
    http_response_code(400);
    exit('Submission too fast. Please spend at least 3 seconds on the task.');
}

$taskSubmittedAt = date('Y-m-d H:i:s', $taskSubmittedTs);
session_set('task_' . $taskNumber . '_total_time_seconds', $elapsedSeconds);
$taskShortTimeFlag = $elapsedSeconds < 15 ? 1 : 0;

$pdo = db();
$hasDurationColumns = false;
$hasVerificationColumns = false;
try {
    $durationCheck = $pdo->query("SHOW COLUMNS FROM task_responses LIKE 'duration_seconds'");
    $hasDurationColumns = $durationCheck !== false && $durationCheck->fetch() !== false;
    $verificationCheck = $pdo->query("SHOW COLUMNS FROM task_responses LIKE 'verification_intention'");
    $hasVerificationColumns = $verificationCheck !== false && $verificationCheck->fetch() !== false;
} catch (Throwable $e) {
    $hasDurationColumns = false;
    $hasVerificationColumns = false;
}

if ($hasDurationColumns && $hasVerificationColumns) {
    $stmt = $pdo->prepare(
        'INSERT INTO task_responses
            (participant_id, task_number, ai_correct, reliance_choice, final_response, confidence, active_reflection, verification_intention, task_started_at, task_submitted_at, duration_seconds, short_time_flag)
         VALUES
            (:participant_id, :task_number, :ai_correct, :reliance_choice, :final_response, :confidence, :active_reflection, :verification_intention, :task_started_at, :task_submitted_at, :duration_seconds, :short_time_flag)'
    );

    $stmt->execute([
        ':participant_id' => $participantId,
        ':task_number' => $taskNumber,
        ':ai_correct' => $aiCorrect,
        ':reliance_choice' => $relianceChoice,
        ':final_response' => $finalResponse,
        ':confidence' => $confidence,
        ':active_reflection' => null,
        ':verification_intention' => $verificationIntention,
        ':task_started_at' => $taskStartedAt,
        ':task_submitted_at' => $taskSubmittedAt,
        ':duration_seconds' => $elapsedSeconds,
        ':short_time_flag' => $taskShortTimeFlag,
    ]);
} elseif ($hasDurationColumns) {
    $stmt = $pdo->prepare(
        'INSERT INTO task_responses
            (participant_id, task_number, ai_correct, reliance_choice, final_response, confidence, active_reflection, task_started_at, task_submitted_at, duration_seconds, short_time_flag)
         VALUES
            (:participant_id, :task_number, :ai_correct, :reliance_choice, :final_response, :confidence, :active_reflection, :task_started_at, :task_submitted_at, :duration_seconds, :short_time_flag)'
    );

    $stmt->execute([
        ':participant_id' => $participantId,
        ':task_number' => $taskNumber,
        ':ai_correct' => $aiCorrect,
        ':reliance_choice' => $relianceChoice,
        ':final_response' => $finalResponse,
        ':confidence' => $confidence,
        ':active_reflection' => $conditionName === 'active'
            ? ('verification_intention=' . $verificationIntention)
            : null,
        ':task_started_at' => $taskStartedAt,
        ':task_submitted_at' => $taskSubmittedAt,
        ':duration_seconds' => $elapsedSeconds,
        ':short_time_flag' => $taskShortTimeFlag,
    ]);
} else {
    $stmt = $pdo->prepare(
        'INSERT INTO task_responses
            (participant_id, task_number, ai_correct, reliance_choice, final_response, confidence, active_reflection, task_started_at, task_submitted_at)
         VALUES
            (:participant_id, :task_number, :ai_correct, :reliance_choice, :final_response, :confidence, :active_reflection, :task_started_at, :task_submitted_at)'
    );

    $stmt->execute([
        ':participant_id' => $participantId,
        ':task_number' => $taskNumber,
        ':ai_correct' => $aiCorrect,
        ':reliance_choice' => $relianceChoice,
        ':final_response' => $finalResponse,
        ':confidence' => $confidence,
        ':active_reflection' => $conditionName === 'active'
            ? ('verification_intention=' . $verificationIntention)
            : null,
        ':task_started_at' => $taskStartedAt,
        ':task_submitted_at' => $taskSubmittedAt,
    ]);
}

$lastTaskNumber = max($enabledTaskNumbers);

if ($taskNumber >= $lastTaskNumber) {
    redirect('postsurvey.php');
}

$nextTaskNumber = $taskNumber + 1;
redirect('task.php?task=' . $nextTaskNumber);
