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

$selectedResponseOption = trim((string) ($_POST['selected_response_option'] ?? ''));
$customResponseText = trim((string) ($_POST['custom_response_text'] ?? ''));
$finalResponse = '';
$responseOptionOrderRaw = trim((string) ($_POST['response_option_order'] ?? ''));
$confidence = filter_input(INPUT_POST, 'confidence', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 5],
]);

if ($taskNumber === false || $taskNumber === null) {
    http_response_code(400);
    exit('Missing or invalid field: task_number.');
}

if ($selectedResponseOption === '') {
    http_response_code(400);
    exit('Missing required field: selected_response_option.');
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
$task = $tasks[$taskNumber];
$configuredOptions = $task['response_options'] ?? [];
if (!is_array($configuredOptions) || count($configuredOptions) !== 4) {
    http_response_code(400);
    exit('Task response options are misconfigured.');
}
$responseTextByKey = [];
foreach ($configuredOptions as $configuredOption) {
    if (!isset($configuredOption['key'], $configuredOption['text'])) {
        http_response_code(400);
        exit('Task response options are incomplete.');
    }
    $responseTextByKey[(string) $configuredOption['key']] = trim((string) $configuredOption['text']);
}

if (!isset($responseTextByKey[$selectedResponseOption])) {
    http_response_code(400);
    exit('Missing or invalid field: selected_response_option.');
}

$decodedResponseOptionOrder = json_decode($responseOptionOrderRaw, true);
if (!is_array($decodedResponseOptionOrder)) {
    http_response_code(400);
    exit('Missing or invalid field: response_option_order.');
}

$configuredOptionKeys = array_values(array_keys($responseTextByKey));
$seenOptionKeys = [];
$responseOptionOrderForStorage = [];
$selectedDisplayLetter = '';
foreach ($decodedResponseOptionOrder as $entry) {
    if (!is_array($entry)) {
        continue;
    }
    $optionKey = trim((string) ($entry['option_key'] ?? ''));
    $displayLetter = strtoupper(trim((string) ($entry['display_letter'] ?? '')));
    if ($optionKey === '' || !isset($responseTextByKey[$optionKey])) {
        continue;
    }
    if (!preg_match('/^[A-D]$/', $displayLetter)) {
        continue;
    }
    if (isset($seenOptionKeys[$optionKey])) {
        continue;
    }
    $seenOptionKeys[$optionKey] = true;
    $responseOptionOrderForStorage[] = [
        'display_letter' => $displayLetter,
        'option_key' => $optionKey,
    ];
    if ($optionKey === $selectedResponseOption) {
        $selectedDisplayLetter = $displayLetter;
    }
}

if (count($responseOptionOrderForStorage) !== count($configuredOptionKeys) || $selectedDisplayLetter === '') {
    http_response_code(400);
    exit('Invalid response option order payload.');
}

$responseOptionOrderJson = json_encode($responseOptionOrderForStorage, JSON_UNESCAPED_UNICODE);
if (!is_string($responseOptionOrderJson) || $responseOptionOrderJson === '') {
    $responseOptionOrderJson = '[]';
}
$selectedOptionKey = $selectedResponseOption;
$responseCorrectness = null;
$manualCodeRequired = 0;
if ($selectedOptionKey === 'other') {
    $manualCodeRequired = 1;
} else {
    if ($taskNumber === 1) {
        if ($selectedOptionKey === 'correct') {
            $responseCorrectness = 1;
        } elseif (in_array($selectedOptionKey, ['ai_consistent_wrong', 'too_permissive', 'too_strict'], true)) {
            $responseCorrectness = 0;
        }
    } elseif ($taskNumber === 2) {
        if ($selectedOptionKey === 'correct') {
            $responseCorrectness = 1;
        } elseif (in_array($selectedOptionKey, ['ai_consistent_wrong', 'too_strict'], true)) {
            $responseCorrectness = 0;
        }
    }
}

if ($selectedResponseOption === 'other') {
    if ($customResponseText === '') {
        http_response_code(400);
        exit('Missing required field: custom_response_text when option "other" is selected.');
    }
    $finalResponse = $customResponseText;
} else {
    $finalResponse = (string) ($responseTextByKey[$selectedResponseOption] ?? '');
}
$relianceChoice = 'option_' . strtolower($selectedResponseOption);

$conditionName = (string) session_get('condition_name', '');
$verificationIntention = null;
if ($conditionName === 'active') {
    $verificationIntention = (string) ($_POST['verification_intention'] ?? '');
    if (trim($verificationIntention) === '') {
        http_response_code(400);
        exit('Missing or invalid field: verification_intention for active condition.');
    }
    $verificationIntention = trim($verificationIntention);
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
$activeReflectionParts = [
    'selected_response_option=' . $selectedResponseOption,
    'selected_option_key=' . $selectedOptionKey,
    'selected_display_letter=' . $selectedDisplayLetter,
    'response_option_order=' . $responseOptionOrderJson,
    'response_correctness=' . ($responseCorrectness === null ? '' : (string) $responseCorrectness),
    'manual_code_required=' . (string) $manualCodeRequired,
];
if ($customResponseText !== '') {
    $activeReflectionParts[] = 'custom_response_text=' . str_replace(["\r", "\n"], ' ', $customResponseText);
}
if ($conditionName === 'active' && $verificationIntention !== null && $verificationIntention !== '') {
    $activeReflectionParts[] = 'verification_intention=' . $verificationIntention;
}
$activeReflectionPayload = implode("\n", $activeReflectionParts);

$pdo = db();
$existingTaskStmt = $pdo->prepare(
    'SELECT id
     FROM task_responses
     WHERE participant_id = :participant_id AND task_number = :task_number
     LIMIT 1'
);
$existingTaskStmt->execute([
    ':participant_id' => $participantId,
    ':task_number' => $taskNumber,
]);
$alreadySubmitted = $existingTaskStmt->fetchColumn() !== false;

$lastTaskNumber = max($enabledTaskNumbers);
if ($alreadySubmitted) {
    if ($taskNumber >= $lastTaskNumber) {
        redirect('postsurvey.php');
    }

    $nextTaskNumber = $taskNumber + 1;
    redirect('task.php?task=' . $nextTaskNumber);
}

$hasDurationColumns = false;
$hasVerificationColumns = false;
$hasSelectedResponseOptionColumn = false;
$hasSelectedOptionKeyColumn = false;
$hasSelectedDisplayLetterColumn = false;
$hasResponseOptionOrderColumn = false;
$hasResponseCorrectnessColumn = false;
$hasManualCodeRequiredColumn = false;
$hasRelevantDocumentOpenedColumn = false;
$hasNumberDocumentsOpenedColumn = false;
$hasTotalDocumentViewTimeMsColumn = false;
$hasRelevantDocumentViewTimeMsColumn = false;
$hasDecisionJustificationColumn = false;
$hasCustomResponseTextColumn = false;
try {
    $durationCheck = $pdo->query("SHOW COLUMNS FROM task_responses LIKE 'duration_seconds'");
    $hasDurationColumns = $durationCheck !== false && $durationCheck->fetch() !== false;
    $verificationCheck = $pdo->query("SHOW COLUMNS FROM task_responses LIKE 'verification_intention'");
    $hasVerificationColumns = $verificationCheck !== false && $verificationCheck->fetch() !== false;
    $selectedResponseOptionCheck = $pdo->query("SHOW COLUMNS FROM task_responses LIKE 'selected_response_option'");
    $hasSelectedResponseOptionColumn = $selectedResponseOptionCheck !== false && $selectedResponseOptionCheck->fetch() !== false;
    $selectedOptionKeyCheck = $pdo->query("SHOW COLUMNS FROM task_responses LIKE 'selected_option_key'");
    $hasSelectedOptionKeyColumn = $selectedOptionKeyCheck !== false && $selectedOptionKeyCheck->fetch() !== false;
    $selectedDisplayLetterCheck = $pdo->query("SHOW COLUMNS FROM task_responses LIKE 'selected_display_letter'");
    $hasSelectedDisplayLetterColumn = $selectedDisplayLetterCheck !== false && $selectedDisplayLetterCheck->fetch() !== false;
    $responseOptionOrderCheck = $pdo->query("SHOW COLUMNS FROM task_responses LIKE 'response_option_order'");
    $hasResponseOptionOrderColumn = $responseOptionOrderCheck !== false && $responseOptionOrderCheck->fetch() !== false;
    $responseCorrectnessCheck = $pdo->query("SHOW COLUMNS FROM task_responses LIKE 'response_correctness'");
    $hasResponseCorrectnessColumn = $responseCorrectnessCheck !== false && $responseCorrectnessCheck->fetch() !== false;
    $manualCodeRequiredCheck = $pdo->query("SHOW COLUMNS FROM task_responses LIKE 'manual_code_required'");
    $hasManualCodeRequiredColumn = $manualCodeRequiredCheck !== false && $manualCodeRequiredCheck->fetch() !== false;
    $relevantDocumentOpenedCheck = $pdo->query("SHOW COLUMNS FROM task_responses LIKE 'relevant_document_opened'");
    $hasRelevantDocumentOpenedColumn = $relevantDocumentOpenedCheck !== false && $relevantDocumentOpenedCheck->fetch() !== false;
    $numberDocumentsOpenedCheck = $pdo->query("SHOW COLUMNS FROM task_responses LIKE 'number_documents_opened'");
    $hasNumberDocumentsOpenedColumn = $numberDocumentsOpenedCheck !== false && $numberDocumentsOpenedCheck->fetch() !== false;
    $totalDocumentViewTimeMsCheck = $pdo->query("SHOW COLUMNS FROM task_responses LIKE 'total_document_view_time_ms'");
    $hasTotalDocumentViewTimeMsColumn = $totalDocumentViewTimeMsCheck !== false && $totalDocumentViewTimeMsCheck->fetch() !== false;
    $relevantDocumentViewTimeMsCheck = $pdo->query("SHOW COLUMNS FROM task_responses LIKE 'relevant_document_view_time_ms'");
    $hasRelevantDocumentViewTimeMsColumn = $relevantDocumentViewTimeMsCheck !== false && $relevantDocumentViewTimeMsCheck->fetch() !== false;
    $decisionJustificationCheck = $pdo->query("SHOW COLUMNS FROM task_responses LIKE 'decision_justification'");
    $hasDecisionJustificationColumn = $decisionJustificationCheck !== false && $decisionJustificationCheck->fetch() !== false;
    $customResponseTextCheck = $pdo->query("SHOW COLUMNS FROM task_responses LIKE 'custom_response_text'");
    $hasCustomResponseTextColumn = $customResponseTextCheck !== false && $customResponseTextCheck->fetch() !== false;
} catch (Throwable $e) {
    $hasDurationColumns = false;
    $hasVerificationColumns = false;
    $hasSelectedResponseOptionColumn = false;
    $hasSelectedOptionKeyColumn = false;
    $hasSelectedDisplayLetterColumn = false;
    $hasResponseOptionOrderColumn = false;
    $hasResponseCorrectnessColumn = false;
    $hasManualCodeRequiredColumn = false;
    $hasRelevantDocumentOpenedColumn = false;
    $hasNumberDocumentsOpenedColumn = false;
    $hasTotalDocumentViewTimeMsColumn = false;
    $hasRelevantDocumentViewTimeMsColumn = false;
    $hasDecisionJustificationColumn = false;
    $hasCustomResponseTextColumn = false;
}

$relevantDocumentOpened = null;
$numberDocumentsOpened = null;
$totalDocumentViewTimeMs = null;
$relevantDocumentViewTimeMs = null;
$needsDocumentSummaryFields = $hasRelevantDocumentOpenedColumn
    || $hasNumberDocumentsOpenedColumn
    || $hasTotalDocumentViewTimeMsColumn
    || $hasRelevantDocumentViewTimeMsColumn;
if ($needsDocumentSummaryFields) {
    $hasDocumentEventsIsRelevantColumn = false;
    try {
        $documentEventsIsRelevantCheck = $pdo->query("SHOW COLUMNS FROM document_events LIKE 'is_relevant'");
        $hasDocumentEventsIsRelevantColumn = $documentEventsIsRelevantCheck !== false && $documentEventsIsRelevantCheck->fetch() !== false;
    } catch (Throwable $e) {
        $hasDocumentEventsIsRelevantColumn = false;
    }
    if ($hasDocumentEventsIsRelevantColumn) {
        $summaryStmt = $pdo->prepare(
            'SELECT
                COUNT(DISTINCT CASE WHEN event_type = "open" THEN document_key END) AS number_documents_opened,
                COALESCE(SUM(CASE WHEN event_type = "close" THEN view_ms ELSE 0 END), 0) AS total_document_view_time_ms,
                MAX(CASE WHEN event_type = "open" AND is_relevant = 1 THEN 1 ELSE 0 END) AS relevant_document_opened,
                COALESCE(SUM(CASE WHEN event_type = "close" AND is_relevant = 1 THEN view_ms ELSE 0 END), 0) AS relevant_document_view_time_ms
             FROM document_events
             WHERE participant_id = :participant_id AND task_number = :task_number'
        );
        $summaryStmt->execute([
            ':participant_id' => $participantId,
            ':task_number' => $taskNumber,
        ]);
        $summaryRow = $summaryStmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($summaryRow)) {
            $relevantDocumentOpened = ((int) ($summaryRow['relevant_document_opened'] ?? 0)) > 0 ? 1 : 0;
            $numberDocumentsOpened = max(0, (int) ($summaryRow['number_documents_opened'] ?? 0));
            $totalDocumentViewTimeMs = max(0, (int) ($summaryRow['total_document_view_time_ms'] ?? 0));
            $relevantDocumentViewTimeMs = max(0, (int) ($summaryRow['relevant_document_view_time_ms'] ?? 0));
        }
    }
}

if ($hasDurationColumns && $hasVerificationColumns) {
    $stmt = $pdo->prepare(
        'INSERT INTO task_responses
            (participant_id, task_number, ai_correct, reliance_choice, final_response, confidence, active_reflection, verification_intention, task_started_at, task_submitted_at, duration_seconds, short_time_flag)
         VALUES
            (:participant_id, :task_number, :ai_correct, :reliance_choice, :final_response, :confidence, :active_reflection, :verification_intention, :task_started_at, :task_submitted_at, :duration_seconds, :short_time_flag)'
    );

    try {
        $stmt->execute([
            ':participant_id' => $participantId,
            ':task_number' => $taskNumber,
            ':ai_correct' => $aiCorrect,
            ':reliance_choice' => $relianceChoice,
            ':final_response' => $finalResponse,
            ':confidence' => $confidence,
            ':active_reflection' => $activeReflectionPayload,
            ':verification_intention' => $verificationIntention,
            ':task_started_at' => $taskStartedAt,
            ':task_submitted_at' => $taskSubmittedAt,
            ':duration_seconds' => $elapsedSeconds,
            ':short_time_flag' => $taskShortTimeFlag,
        ]);
    } catch (PDOException $e) {
        if ((string) $e->getCode() !== '23000') {
            throw $e;
        }
    }
} elseif ($hasDurationColumns) {
    $stmt = $pdo->prepare(
        'INSERT INTO task_responses
            (participant_id, task_number, ai_correct, reliance_choice, final_response, confidence, active_reflection, task_started_at, task_submitted_at, duration_seconds, short_time_flag)
         VALUES
            (:participant_id, :task_number, :ai_correct, :reliance_choice, :final_response, :confidence, :active_reflection, :task_started_at, :task_submitted_at, :duration_seconds, :short_time_flag)'
    );

    try {
        $stmt->execute([
            ':participant_id' => $participantId,
            ':task_number' => $taskNumber,
            ':ai_correct' => $aiCorrect,
            ':reliance_choice' => $relianceChoice,
            ':final_response' => $finalResponse,
            ':confidence' => $confidence,
            ':active_reflection' => $activeReflectionPayload,
            ':task_started_at' => $taskStartedAt,
            ':task_submitted_at' => $taskSubmittedAt,
            ':duration_seconds' => $elapsedSeconds,
            ':short_time_flag' => $taskShortTimeFlag,
        ]);
    } catch (PDOException $e) {
        if ((string) $e->getCode() !== '23000') {
            throw $e;
        }
    }
} else {
    $stmt = $pdo->prepare(
        'INSERT INTO task_responses
            (participant_id, task_number, ai_correct, reliance_choice, final_response, confidence, active_reflection, task_started_at, task_submitted_at)
         VALUES
            (:participant_id, :task_number, :ai_correct, :reliance_choice, :final_response, :confidence, :active_reflection, :task_started_at, :task_submitted_at)'
    );

    try {
        $stmt->execute([
            ':participant_id' => $participantId,
            ':task_number' => $taskNumber,
            ':ai_correct' => $aiCorrect,
            ':reliance_choice' => $relianceChoice,
            ':final_response' => $finalResponse,
            ':confidence' => $confidence,
            ':active_reflection' => $activeReflectionPayload,
            ':task_started_at' => $taskStartedAt,
            ':task_submitted_at' => $taskSubmittedAt,
        ]);
    } catch (PDOException $e) {
        if ((string) $e->getCode() !== '23000') {
            throw $e;
        }
    }
}

$updateAssignments = [];
$updateParams = [
    ':participant_id' => $participantId,
    ':task_number' => $taskNumber,
];
if ($hasSelectedResponseOptionColumn) {
    $updateAssignments[] = 'selected_response_option = :selected_response_option';
    $updateParams[':selected_response_option'] = $selectedResponseOption;
}
if ($hasSelectedOptionKeyColumn) {
    $updateAssignments[] = 'selected_option_key = :selected_option_key';
    $updateParams[':selected_option_key'] = $selectedOptionKey;
}
if ($hasSelectedDisplayLetterColumn) {
    $updateAssignments[] = 'selected_display_letter = :selected_display_letter';
    $updateParams[':selected_display_letter'] = $selectedDisplayLetter;
}
if ($hasResponseOptionOrderColumn) {
    $updateAssignments[] = 'response_option_order = :response_option_order';
    $updateParams[':response_option_order'] = $responseOptionOrderJson;
}
if ($hasResponseCorrectnessColumn) {
    $updateAssignments[] = 'response_correctness = :response_correctness';
    $updateParams[':response_correctness'] = $responseCorrectness;
}
if ($hasManualCodeRequiredColumn) {
    $updateAssignments[] = 'manual_code_required = :manual_code_required';
    $updateParams[':manual_code_required'] = $manualCodeRequired;
}
if ($hasRelevantDocumentOpenedColumn) {
    $updateAssignments[] = 'relevant_document_opened = :relevant_document_opened';
    $updateParams[':relevant_document_opened'] = $relevantDocumentOpened;
}
if ($hasNumberDocumentsOpenedColumn) {
    $updateAssignments[] = 'number_documents_opened = :number_documents_opened';
    $updateParams[':number_documents_opened'] = $numberDocumentsOpened;
}
if ($hasTotalDocumentViewTimeMsColumn) {
    $updateAssignments[] = 'total_document_view_time_ms = :total_document_view_time_ms';
    $updateParams[':total_document_view_time_ms'] = $totalDocumentViewTimeMs;
}
if ($hasRelevantDocumentViewTimeMsColumn) {
    $updateAssignments[] = 'relevant_document_view_time_ms = :relevant_document_view_time_ms';
    $updateParams[':relevant_document_view_time_ms'] = $relevantDocumentViewTimeMs;
}
if ($hasDecisionJustificationColumn) {
    $updateAssignments[] = 'decision_justification = :decision_justification';
    $updateParams[':decision_justification'] = null;
}
if ($hasCustomResponseTextColumn) {
    $updateAssignments[] = 'custom_response_text = :custom_response_text';
    $updateParams[':custom_response_text'] = $customResponseText !== '' ? $customResponseText : null;
}
if (!empty($updateAssignments)) {
    $updateStmt = $pdo->prepare(
        'UPDATE task_responses
         SET ' . implode(', ', $updateAssignments) . '
         WHERE participant_id = :participant_id AND task_number = :task_number'
    );
    $updateStmt->execute($updateParams);
}

if ($taskNumber >= $lastTaskNumber) {
    redirect('postsurvey.php');
}

$nextTaskNumber = $taskNumber + 1;
redirect('task.php?task=' . $nextTaskNumber);
