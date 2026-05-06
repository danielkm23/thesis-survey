<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/db.php';

if (!has_valid_participant_session()) {
    redirect('index.php');
}

$rawTask = $_GET['task'] ?? null;
$taskNumber = filter_var($rawTask, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

$tasks = require __DIR__ . '/../data/tasks.php';
$enabledTaskNumbers = [1, 2]; // Temporary MVP gate
$totalTasks = count($enabledTaskNumbers);
$errorMessage = null;
$task = null;
$existingTaskResponse = null;
$documentsInDisplayOrder = [];
$responseOptions = [];
$initialOpenEventOrder = 0;

if (
    $taskNumber === false
    || $taskNumber === null
    || !in_array($taskNumber, $enabledTaskNumbers, true)
    || !isset($tasks[$taskNumber])
) {
    $errorMessage = 'Invalid task. Please return to the introduction page and try again.';
} else {
    $participantId = (int) session_get('participant_id', 0);
    $completedTaskNumbers = [];
    if ($participantId > 0) {
        $completedTasksStmt = db()->prepare(
            'SELECT DISTINCT task_number
             FROM task_responses
             WHERE participant_id = :participant_id'
        );
        $completedTasksStmt->execute([
            ':participant_id' => $participantId,
        ]);
        $completedTaskNumbers = array_values(array_filter(
            array_map('intval', $completedTasksStmt->fetchAll(PDO::FETCH_COLUMN)),
            static fn (int $value): bool => in_array($value, $enabledTaskNumbers, true)
        ));
    }

    $firstPendingTaskNumber = null;
    foreach ($enabledTaskNumbers as $enabledTaskNumber) {
        if (!in_array($enabledTaskNumber, $completedTaskNumbers, true)) {
            $firstPendingTaskNumber = $enabledTaskNumber;
            break;
        }
    }

    // Allow navigating back to already completed task pages.
    // Only block attempts to jump ahead of the first unfinished task.
    if ($firstPendingTaskNumber !== null && $taskNumber > $firstPendingTaskNumber) {
        redirect('task.php?task=' . $firstPendingTaskNumber);
    }

    if ($participantId > 0) {
        $existingTaskStmt = db()->prepare(
            'SELECT final_response, reliance_choice, confidence, verification_intention, active_reflection
             FROM task_responses
             WHERE participant_id = :participant_id AND task_number = :task_number
             ORDER BY id DESC
             LIMIT 1'
        );
        $existingTaskStmt->execute([
            ':participant_id' => $participantId,
            ':task_number' => $taskNumber,
        ]);
        $existingTaskResponse = $existingTaskStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    $task = $tasks[$taskNumber];

    // Keep a simple task-start timestamp in session for each task.
    $taskSessionKey = 'task_' . $taskNumber . '_started_at';
    if (session_get($taskSessionKey) === null) {
        session_set($taskSessionKey, date('Y-m-d H:i:s'));
    }

    $documents = $task['documents'] ?? [];
    if (!is_array($documents) || count($documents) !== 3) {
        $errorMessage = 'Task configuration error: each task must contain exactly 3 documents.';
    } else {
        $documentsByKey = [];
        $relevantCount = 0;

        foreach ($documents as $document) {
            if (!isset($document['key'], $document['title'], $document['content'], $document['relevant'])) {
                $errorMessage = 'Task configuration error: document fields are incomplete.';
                break;
            }

            $documentKey = (string) $document['key'];
            $documentsByKey[$documentKey] = $document;
            if (!empty($document['relevant'])) {
                $relevantCount++;
            }
        }

        if ($errorMessage === null && $relevantCount !== 1) {
            $errorMessage = 'Task configuration error: each task must have exactly 1 relevant document.';
        }

        if ($errorMessage === null) {
            if (!isset($_SESSION['doc_order']) || !is_array($_SESSION['doc_order'])) {
                $_SESSION['doc_order'] = [];
            }

            $storedOrder = $_SESSION['doc_order'][$taskNumber] ?? null;
            $isStoredOrderValid = is_array($storedOrder)
                && count($storedOrder) === 3
                && count(array_unique($storedOrder)) === 3;

            if ($isStoredOrderValid) {
                foreach ($storedOrder as $storedKey) {
                    if (!isset($documentsByKey[$storedKey])) {
                        $isStoredOrderValid = false;
                        break;
                    }
                }
            }

            if (!$isStoredOrderValid) {
                $storedOrder = array_keys($documentsByKey);
                shuffle($storedOrder);
                $_SESSION['doc_order'][$taskNumber] = $storedOrder;
            }

            foreach ($storedOrder as $index => $documentKey) {
                $document = $documentsByKey[$documentKey];
                $document['display_order'] = $index + 1;
                $documentsInDisplayOrder[] = $document;
            }
        }
    }

    $initialOpenEventOrder = (int) session_get('task_' . $taskNumber . '_open_event_order', 0);

    if ($errorMessage === null) {
        $configuredResponseOptions = $task['response_options'] ?? [];
        if (!is_array($configuredResponseOptions) || count($configuredResponseOptions) !== 4) {
            $errorMessage = 'Task configuration error: each task must contain exactly 4 response options.';
        } else {
            $seenResponseKeys = [];
            foreach ($configuredResponseOptions as $responseOption) {
                if (!isset($responseOption['key'], $responseOption['text'])) {
                    $errorMessage = 'Task configuration error: response option fields are incomplete.';
                    break;
                }
                $optionKey = trim((string) $responseOption['key']);
                $optionText = trim((string) $responseOption['text']);
                if ($optionKey === '' || $optionText === '' || isset($seenResponseKeys[$optionKey])) {
                    $errorMessage = 'Task configuration error: response options must have unique non-empty keys and text.';
                    break;
                }
                $seenResponseKeys[$optionKey] = true;
                $responseOptions[] = [
                    'key' => $optionKey,
                    'text' => $optionText,
                ];
            }

            if ($errorMessage === null) {
                if (!isset($_SESSION['response_option_order']) || !is_array($_SESSION['response_option_order'])) {
                    $_SESSION['response_option_order'] = [];
                }

                $optionByKey = [];
                foreach ($responseOptions as $responseOption) {
                    $optionByKey[$responseOption['key']] = $responseOption;
                }

                $allOptionKeys = array_values(array_keys($optionByKey));
                $nonOtherKeys = array_values(array_filter(
                    $allOptionKeys,
                    static fn (string $key): bool => $key !== 'other'
                ));
                $storedOptionOrder = $_SESSION['response_option_order'][$taskNumber] ?? null;
                $isStoredOptionOrderValid = is_array($storedOptionOrder)
                    && count($storedOptionOrder) === count($nonOtherKeys)
                    && count(array_unique($storedOptionOrder)) === count($nonOtherKeys);

                if ($isStoredOptionOrderValid) {
                    foreach ($storedOptionOrder as $storedOptionKey) {
                        if (!in_array($storedOptionKey, $nonOtherKeys, true)) {
                            $isStoredOptionOrderValid = false;
                            break;
                        }
                    }
                }

                if (!$isStoredOptionOrderValid) {
                    $storedOptionOrder = $nonOtherKeys;
                    shuffle($storedOptionOrder);
                    $_SESSION['response_option_order'][$taskNumber] = $storedOptionOrder;
                }

                $displayOrderKeys = $storedOptionOrder;
                if (isset($optionByKey['other'])) {
                    $displayOrderKeys[] = 'other';
                }
                $orderedOptions = [];
                foreach ($displayOrderKeys as $index => $storedOptionKey) {
                    if (!isset($optionByKey[$storedOptionKey])) {
                        continue;
                    }
                    $displayLetter = chr(ord('A') + $index);
                    $orderedOptions[] = [
                        'key' => $optionByKey[$storedOptionKey]['key'],
                        'text' => $optionByKey[$storedOptionKey]['text'],
                        'display_letter' => $displayLetter,
                    ];
                }
                $responseOptions = $orderedOptions;
            }
        }
    }
}

$conditionName = (string) session_get('condition_name', '');
$isActiveCondition = $conditionName === 'active';
$showPassiveNotice = $conditionName === 'passive' && !$isActiveCondition;
$taskView = (string) ($_GET['view'] ?? 'review');
if (!in_array($taskView, ['review', 'decision'], true)) {
    $taskView = 'review';
}
$reviewErrorMessage = '';
$taskDraftSessionKey = $taskNumber !== false && $taskNumber !== null
    ? ('task_' . $taskNumber . '_review_draft')
    : '';
$taskDraft = $taskDraftSessionKey !== '' ? session_get($taskDraftSessionKey, []) : [];
$draftVerificationIntention = is_array($taskDraft) ? trim((string) ($taskDraft['verification_intention'] ?? '')) : '';
$prefillConfidence = is_array($existingTaskResponse)
    ? trim((string) ($existingTaskResponse['confidence'] ?? ''))
    : '';
$prefillVerificationIntention = '';
if (is_array($existingTaskResponse)) {
    $prefillVerificationIntention = trim((string) ($existingTaskResponse['verification_intention'] ?? ''));
    if ($prefillVerificationIntention === '') {
        $activeReflection = trim((string) ($existingTaskResponse['active_reflection'] ?? ''));
        if ($activeReflection !== '') {
            $reflectionLines = preg_split('/\R+/', $activeReflection) ?: [];
            foreach ($reflectionLines as $reflectionLine) {
                if (str_starts_with($reflectionLine, 'verification_intention=')) {
                    $prefillVerificationIntention = trim((string) substr($reflectionLine, strlen('verification_intention=')));
                    break;
                }
            }
        }
    }
}
$prefillSelectedResponseOption = '';
$prefillCustomResponseText = '';
$responseOptionOrderPayload = '';
if (!empty($responseOptions)) {
    $responseOptionOrderPayload = json_encode(array_map(
        static fn (array $responseOption): array => [
            'display_letter' => (string) ($responseOption['display_letter'] ?? ''),
            'option_key' => (string) ($responseOption['key'] ?? ''),
        ],
        $responseOptions
    ), JSON_UNESCAPED_UNICODE);
    if (!is_string($responseOptionOrderPayload)) {
        $responseOptionOrderPayload = '[]';
    }
}
if (is_array($existingTaskResponse)) {
    $activeReflection = trim((string) ($existingTaskResponse['active_reflection'] ?? ''));
    if ($activeReflection !== '') {
        $reflectionLines = preg_split('/\R+/', $activeReflection) ?: [];
        foreach ($reflectionLines as $reflectionLine) {
            if (str_starts_with($reflectionLine, 'selected_response_option=')) {
                $prefillSelectedResponseOption = trim((string) substr($reflectionLine, strlen('selected_response_option=')));
            } elseif (str_starts_with($reflectionLine, 'custom_response_text=')) {
                $prefillCustomResponseText = trim((string) substr($reflectionLine, strlen('custom_response_text=')));
            }
        }
    }
}
$reviewVerificationValue = $draftVerificationIntention !== '' ? $draftVerificationIntention : $prefillVerificationIntention;

if ($taskView === 'review' && $_SERVER['REQUEST_METHOD'] === 'POST' && $errorMessage === null) {
    $submittedFormStep = (string) ($_POST['form_step'] ?? '');
    if ($submittedFormStep === 'review') {
        redirect('task.php?task=' . $taskNumber . '&view=decision');
    }
}

$roleSummaryText = 'You work in finance/admin and provide policy guidance. A manager makes final approval decisions when required.';
$taskStepIndex = $taskNumber !== false && $taskNumber !== null
    ? array_search($taskNumber, $enabledTaskNumbers, true)
    : false;
$taskStep = $taskStepIndex === false ? 1 : ($taskStepIndex + 1);
$postsurveyParts = 4;
$taskPhasesPerTask = 2; // review + decision
$currentTaskPhase = $taskView === 'decision' ? 2 : 1;
$currentStudyStep = (($taskStep - 1) * $taskPhasesPerTask) + $currentTaskPhase;
$totalStudySteps = ($totalTasks * $taskPhasesPerTask) + $postsurveyParts;
$progressPercent = (int) round(($currentStudyStep / max(1, $totalStudySteps)) * 100);

$pageTitle = 'Task ' . ($taskNumber ?: '');
require __DIR__ . '/../views/header.php';
?>

<main class="max-w-6xl mx-auto px-4 pt-2 pb-4">
    <style>
        /* Safari-consistent custom radios */
        #task-form .radio-option {
            display: flex;
            align-items: flex-start;
            gap: 0.7rem;
            cursor: pointer;
        }

        #task-form .custom-radio {
            -webkit-appearance: none;
            appearance: none;
            width: 1.2rem;
            height: 1.2rem;
            border: 2px solid #cbd5e1;
            border-radius: 9999px;
            background: #fff;
            margin-top: 0.18rem;
            flex: 0 0 auto;
            position: relative;
            outline: none;
        }

        #task-form .custom-radio:checked {
            border-color: #2563eb;
            background: #2563eb;
        }

        #task-form .custom-radio:checked::after {
            content: '';
            position: absolute;
            width: 0.42rem;
            height: 0.42rem;
            border-radius: 9999px;
            background: #fff;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        #task-form .radio-option-text {
            line-height: 1.5;
        }

        #task-form .radio-help {
            margin-left: 1.9rem;
        }

        .task-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 0.9rem;
            padding: 0.9rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
        }

        .task-card-title {
            font-size: 1rem;
            line-height: 1.5rem;
            font-weight: 600;
            letter-spacing: normal;
            text-transform: none;
            color: #475569;
            margin-bottom: 0.5rem;
        }

        .task-card-title-sentence {
            letter-spacing: normal;
            text-transform: none;
        }

        .message-card {
            border: 1px solid #dbeafe;
            background: #f8fbff;
            border-radius: 0.75rem;
            padding: 0.55rem;
        }

        .message-header {
            margin-bottom: 0.4rem;
            color: #334155;
            font-size: 0.8rem;
        }

        .draft-card {
            border: 1px solid #e2e8f0;
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 0.75rem;
            padding: 0.75rem;
        }

        .doc-button-card {
            border: 1px solid #cbd5e1;
            border-radius: 0.75rem;
            background: #fff;
            padding: 0.45rem 0.65rem;
            transition: background-color 0.15s ease, border-color 0.15s ease;
            min-height: 2.3rem;
            font-size: 0.95rem;
            line-height: 1.35;
        }

        .doc-button-card:hover {
            background: #f8fafc;
            border-color: #94a3b8;
        }

        .reference-details summary {
            cursor: pointer;
            font-weight: 600;
            font-size: 0.875rem;
            color: #334155;
            list-style: none;
            user-select: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0;
            border: 0;
            border-radius: 0;
            background: transparent;
            transition: color 0.15s ease;
        }

        .reference-details summary:hover {
            color: #0f172a;
        }

        .reference-details summary::-webkit-details-marker {
            display: none;
        }

        .reference-details summary::marker {
            display: none;
        }

        .reference-details summary::after {
            content: 'Show';
            font-size: 0.75rem;
            font-weight: 700;
            color: #475569;
            letter-spacing: 0.01em;
        }

        .reference-details[open] summary::after {
            content: 'Hide';
        }

        @media (max-width: 767px) {
            .task-card {
                padding: 0.8rem;
            }

            .task-card-title {
                font-size: 0.95rem;
                margin-bottom: 0.45rem;
            }

            .doc-button-card {
                padding: 0.45rem 0.65rem;
                min-height: 2.3rem;
                font-size: 0.95rem;
                line-height: 1.35;
            }

            .reference-details {
                padding-top: 0.45rem;
                padding-bottom: 0.45rem;
            }

            .reference-details summary {
                font-size: 0.875rem;
                line-height: 1.2;
            }
        }

        .document-modal-content {
            color: #334155;
            line-height: 1.65;
            font-size: 0.98rem;
        }

        .document-modal-content p {
            margin: 0 0 0.9rem 0;
        }

        .document-modal-content p:last-child {
            margin-bottom: 0;
        }

        .document-modal-subheading {
            margin: 0.7rem 0 0.45rem 0;
            font-size: 0.83rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #475569;
        }

        .document-modal-list {
            margin: 0 0 0.95rem 0;
            padding-left: 1.2rem;
            list-style-type: disc;
        }

        .document-modal-list li {
            margin-bottom: 0.4rem;
        }

        .document-modal-list li:last-child {
            margin-bottom: 0;
        }

    </style>

    <?php if ($errorMessage !== null): ?>
        <section class="bg-white shadow rounded-xl p-8">
            <h1 class="text-xl font-semibold text-slate-800 mb-2">Task Error</h1>
            <p class="text-slate-600 mb-6"><?= e($errorMessage) ?></p>
            <a href="intro.php" class="inline-block bg-slate-700 hover:bg-slate-800 text-white px-4 py-2 rounded-lg">
                Back to Introduction
            </a>
        </section>
    <?php else: ?>
        <section class="mb-2 md:mb-3 px-0.5">
            <p class="text-sm text-slate-700 mb-1.5">Step <?= e((string) $currentStudyStep) ?> of <?= e((string) $totalStudySteps) ?> — <?= e((string) $progressPercent) ?>% complete</p>
            <div class="w-full h-[6px] bg-slate-200 rounded">
                <div class="h-[6px] accent-bg rounded" style="width: <?= e((string) $progressPercent) ?>%"></div>
            </div>
        </section>

        <section class="task-card mb-2 md:mb-2.5">
            <h1 class="text-xl md:text-[1.65rem] font-bold text-slate-800 mb-1.5 md:mb-1.5"><?= e($task['title']) ?></h1>
            <?php if ($taskView === 'review'): ?>
                <?php if ((int) $task['number'] === 1): ?>
                    <p class="text-base text-slate-700 leading-6">You work in finance administration and advise colleagues on expense policy, but managers make final approval decisions.</p>
                    <p class="text-base text-slate-700 leading-6 mt-2">Read the colleague&rsquo;s message and the AI-generated draft response. Company documents are available if needed. This information will remain available on the next page, where you choose your response.</p>
                <?php else: ?>
                    <p class="text-base text-slate-700 leading-6">You work in operations and help colleagues follow company policy for meetings, data handling, and client communication.</p>
                    <p class="text-base text-slate-700 leading-6 mt-2">Read the colleague&rsquo;s message and the AI-generated draft response. Company documents are available if needed. This information will remain available on the next page, where you choose your response.</p>
                <?php endif; ?>
            <?php else: ?>
                <p class="text-base text-slate-700 leading-6">Please answer as you would in a realistic workplace situation. Your goal is to give an accurate internal reply based on the information available.</p>
            <?php endif; ?>
        </section>

        <?php if ($taskView === 'review'): ?>
            <form id="task-form" method="post" action="task.php?task=<?= e((string) $task['number']) ?>&view=review">
                <input type="hidden" name="form_step" value="review">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-2 md:gap-2.5 items-start">
                    <div class="md:col-span-2 space-y-2">
                        <section class="task-card">
                            <h2 class="task-card-title">Message from colleague</h2>
                            <div class="message-card">
                                <div class="message-header">
                                    <span class="font-semibold">From:</span> Colleague
                                    <span class="mx-2 text-slate-400">|</span>
                                    <span class="font-semibold">Subject:</span> Quick policy check request
                                </div>
                                <p class="text-slate-700 leading-[1.35] whitespace-pre-line"><?= e($task['scenario']) ?></p>
                            </div>
                        </section>

                        <section class="task-card">
                            <h2 class="task-card-title">Draft reply generated by AI assistant</h2>
                            <?php if ($showPassiveNotice): ?>
                                <div class="mb-2.5 rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-sm text-amber-900">
                                    AI-generated replies may contain incomplete or incorrect interpretations. Review carefully before using.
                                </div>
                            <?php endif; ?>
                            <?php $aiOutputText = trim((string) preg_replace("/\R{3,}/", "\n\n", (string) $task['ai_output'])); ?>
                            <div class="draft-card text-slate-700 leading-[1.35]">
                                <?= nl2br(e($aiOutputText)) ?>
                            </div>
                        </section>

                        <section class="task-card">
                            <button
                                type="submit"
                                id="task-submit-button"
                                class="w-full sm:w-auto accent-bg accent-bg-hover text-white font-medium px-5 py-3 rounded-lg transition"
                            >
                                Continue to response options →
                            </button>
                        </section>
                    </div>

                    <aside class="md:col-span-1 md:sticky md:top-5 self-start">
                        <section class="task-card">
                            <h2 class="task-card-title task-card-title-sentence">Available company documents</h2>
                            <div class="space-y-1.5">
                                <?php foreach ($documentsInDisplayOrder as $document): ?>
                                    <button
                                        type="button"
                                        data-open-doc-modal="true"
                                        data-doc-key="<?= e($document['key']) ?>"
                                        data-doc-title="<?= e($document['title']) ?>"
                                        data-doc-content="<?= e($document['content']) ?>"
                                        data-doc-relevant="<?= !empty($document['relevant']) ? '1' : '0' ?>"
                                        data-display-order="<?= e((string) $document['display_order']) ?>"
                                        class="doc-button-card w-full text-left flex items-start gap-1.5 md:gap-2"
                                    >
                                        <span aria-hidden="true">📄</span>
                                        <span class="text-slate-700"><?= e($document['display_order'] . '. ' . $document['title']) ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    </aside>
                </div>
            </form>
        <?php else: ?>
            <form id="task-form" method="post" action="save_task.php">
                <input type="hidden" name="task_number" value="<?= e((string) $task['number']) ?>">
                <input type="hidden" name="response_option_order" value="<?= e($responseOptionOrderPayload) ?>">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-2 md:gap-2.5 items-start">
                    <div class="md:col-span-2 space-y-2">
                        <?php
                        $decisionSummaryText = trim((string) ($task['decision_summary'] ?? ''));
                        if ($decisionSummaryText === '') {
                            $decisionSummaryText = 'Colleague request: open the sections below for the full message and AI draft.';
                        }
                        $decisionAiOutputText = trim((string) preg_replace("/\R{3,}/", "\n\n", (string) ($task['ai_output'] ?? '')));
                        ?>
                        <section class="task-card py-2">
                            <p class="text-base text-slate-700 leading-6"><?= e($decisionSummaryText) ?></p>
                        </section>

                        <details class="task-card reference-details">
                            <summary>Message from colleague</summary>
                            <div class="mt-2 pt-2 border-slate-200 border-t">
                                <div class="message-card">
                                    <div class="message-header">
                                        <span class="font-semibold">From:</span> Colleague
                                        <span class="mx-2 text-slate-400">|</span>
                                        <span class="font-semibold">Subject:</span> Quick policy check request
                                    </div>
                                    <p class="text-slate-700 leading-[1.35] whitespace-pre-line"><?= e($task['scenario']) ?></p>
                                </div>
                            </div>
                        </details>

                        <details class="task-card reference-details">
                            <summary>Draft reply generated by AI assistant</summary>
                            <div class="mt-2 pt-2 border-t border-slate-200">
                                <?php if ($showPassiveNotice): ?>
                                    <div class="mb-2.5 rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-sm text-amber-900">
                                        AI-generated replies may contain incomplete or incorrect interpretations. Review carefully before using.
                                    </div>
                                <?php endif; ?>
                                <div class="draft-card text-slate-700 leading-[1.35]">
                                    <?= nl2br(e($decisionAiOutputText)) ?>
                                </div>
                            </div>
                        </details>

                        <section class="task-card md:hidden">
                            <h2 class="task-card-title task-card-title-sentence">Available company documents</h2>
                            <div class="space-y-1.5">
                                <?php foreach ($documentsInDisplayOrder as $document): ?>
                                    <button
                                        type="button"
                                        data-open-doc-modal="true"
                                        data-doc-key="<?= e($document['key']) ?>"
                                        data-doc-title="<?= e($document['title']) ?>"
                                        data-doc-content="<?= e($document['content']) ?>"
                                        data-doc-relevant="<?= !empty($document['relevant']) ? '1' : '0' ?>"
                                        data-display-order="<?= e((string) $document['display_order']) ?>"
                                        class="doc-button-card w-full text-left flex items-start gap-1.5 md:gap-2"
                                    >
                                        <span aria-hidden="true">📄</span>
                                        <span class="text-slate-700"><?= e($document['display_order'] . '. ' . $document['title']) ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </section>

                        <section class="task-card">
                            <?php if ($isActiveCondition): ?>
                                <div id="verification-intention-group" class="mb-3.5">
                                    <label for="verification_intention" class="block text-base font-semibold text-slate-800 mb-2">Before using the AI draft, briefly state what you would consider changing or checking.</label>
                                    <div class="space-y-1.5 text-slate-700">
                                        <input
                                            type="text"
                                            id="verification_intention"
                                            name="verification_intention"
                                            maxlength="180"
                                            required
                                            class="w-full rounded-lg border border-slate-300 px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            placeholder="I would consider..."
                                            value="<?= e($reviewVerificationValue) ?>"
                                        >
                                        <p class="text-xs text-slate-500">A few words are enough. You may write “nothing” if you would not change or check anything.</p>
                                    </div>
                                    <p id="verification-intention-error" class="mt-2 text-sm text-red-600 hidden">Please briefly state what you would consider changing or checking.</p>
                                </div>
                            <?php endif; ?>

                            <fieldset id="selected-response-fieldset" class="mb-4">
                                <legend class="text-base font-semibold text-slate-800 mb-3">Which response would you send to the colleague?</legend>
                                <div class="space-y-1.5 text-slate-700">
                                    <?php foreach ($responseOptions as $responseOption): ?>
                                        <label class="block rounded-lg border border-slate-200 px-2.5 py-1.5 hover:bg-slate-50">
                                            <span class="radio-option">
                                                <input type="radio" name="selected_response_option" value="<?= e($responseOption['key']) ?>" class="custom-radio" required <?= $prefillSelectedResponseOption === $responseOption['key'] ? 'checked' : '' ?>>
                                                <span class="radio-option-text text-sm md:text-base"><?= e((string) ($responseOption['display_letter'] ?? '')) ?>. <?= e($responseOption['text']) ?></span>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <p id="selected-response-error" class="mt-2 text-sm text-red-600 hidden">Please choose one response option.</p>
                            </fieldset>

                            <div id="custom-response-wrapper" class="<?= $prefillSelectedResponseOption === 'other' ? '' : 'hidden' ?> mb-4">
                                <label for="custom_response_text" class="block text-base font-semibold text-slate-800 mb-1.5">Briefly write what you would send instead.</label>
                                <textarea
                                    id="custom_response_text"
                                    name="custom_response_text"
                                    rows="2"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-y"
                                    placeholder="Briefly write what you would send instead."
                                ><?= e($prefillCustomResponseText) ?></textarea>
                                <p id="custom-response-error" class="mt-2 text-sm text-red-600 hidden">Please provide your custom response when selecting option D.</p>
                            </div>

                            <fieldset id="confidence-fieldset" class="mb-4">
                                <legend class="text-base font-semibold text-slate-800 mb-3 break-words">How confident are you that this response is correct?</legend>
                                <div class="grid grid-cols-5 gap-1.5 text-slate-700">
                                    <?php foreach (['1', '2', '3', '4', '5'] as $confidenceOption): ?>
                                        <label class="flex flex-col items-center justify-center gap-1 rounded-lg border border-slate-200 px-1.5 py-1.5 hover:bg-slate-50">
                                            <span class="radio-option">
                                                <input type="radio" name="confidence" value="<?= e($confidenceOption) ?>" class="custom-radio" required <?= $prefillConfidence === $confidenceOption ? 'checked' : '' ?>>
                                            </span>
                                            <span class="text-xs md:text-sm font-medium text-slate-700"><?= e($confidenceOption) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <div class="mt-2 flex items-center justify-between text-xs text-slate-500">
                                    <span>1 = Not confident at all</span>
                                    <span>5 = Extremely confident</span>
                                </div>
                                <p id="confidence-error" class="mt-2 text-sm text-red-600 hidden">Please select a confidence value.</p>
                            </fieldset>

                            <button
                                type="submit"
                                id="task-submit-button"
                                class="w-full sm:w-auto accent-bg accent-bg-hover text-white font-medium px-5 py-3 rounded-lg transition"
                            >
                                Continue →
                            </button>
                            <p id="task-autosave-status" class="mt-2 text-xs text-slate-500" aria-live="polite">All changes saved</p>
                        </section>
                    </div>

                    <aside class="hidden md:block md:col-span-1 md:sticky md:top-5 self-start">
                        <section class="task-card">
                            <h2 class="task-card-title task-card-title-sentence">Available company documents</h2>
                            <div class="space-y-1.5">
                                <?php foreach ($documentsInDisplayOrder as $document): ?>
                                    <button
                                        type="button"
                                        data-open-doc-modal="true"
                                        data-doc-key="<?= e($document['key']) ?>"
                                        data-doc-title="<?= e($document['title']) ?>"
                                        data-doc-content="<?= e($document['content']) ?>"
                                        data-doc-relevant="<?= !empty($document['relevant']) ? '1' : '0' ?>"
                                        data-display-order="<?= e((string) $document['display_order']) ?>"
                                        class="doc-button-card w-full text-left flex items-start gap-1.5 md:gap-2"
                                    >
                                        <span aria-hidden="true">📄</span>
                                        <span class="text-slate-700"><?= e($document['display_order'] . '. ' . $document['title']) ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    </aside>
                </div>
            </form>
        <?php endif; ?>

        <div
            id="document-modal-overlay"
            class="hidden fixed inset-0 z-50 bg-slate-900/50 px-4 py-8"
            aria-hidden="true"
        >
            <div class="flex items-start justify-center min-h-full">
                <div
                    id="document-modal-box"
                    class="w-full max-w-3xl bg-white rounded-xl shadow-xl border border-slate-200"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="document-modal-title"
                >
                    <div class="flex items-center justify-between px-4 sm:px-6 py-4 border-b border-slate-200">
                        <h3 id="document-modal-title" class="text-lg sm:text-xl font-semibold text-slate-800 leading-snug"></h3>
                        <button
                            type="button"
                            id="document-modal-close"
                            class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-slate-50 text-slate-700 hover:bg-slate-100 hover:text-slate-900 px-3 py-1.5 text-sm font-medium"
                        >
                            Close
                        </button>
                    </div>
                    <div class="px-4 sm:px-6 py-4 sm:py-5 max-h-[72vh] overflow-y-auto">
                        <div id="document-modal-content" class="document-modal-content"></div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            (function () {
                var taskView = <?= json_encode($taskView) ?>;
                var taskNumber = <?= (int) $task['number'] ?>;
                var participantId = <?= (int) session_get('participant_id', 0) ?>;
                var conditionName = <?= json_encode($conditionName) ?>;
                var isActiveCondition = <?= $isActiveCondition ? 'true' : 'false' ?>;
                var form = document.getElementById('task-form');
                var submitButton = document.getElementById('task-submit-button');
                var autosaveStatus = document.getElementById('task-autosave-status');
                var autosaveTimer = null;
                var selectedResponseError = document.getElementById('selected-response-error');
                var confidenceError = document.getElementById('confidence-error');
                var verificationIntentionError = document.getElementById('verification-intention-error');
                var customResponseError = document.getElementById('custom-response-error');
                var customResponseWrapper = document.getElementById('custom-response-wrapper');

                var overlay = document.getElementById('document-modal-overlay');
                var modalBox = document.getElementById('document-modal-box');
                var closeButton = document.getElementById('document-modal-close');
                var titleEl = document.getElementById('document-modal-title');
                var contentEl = document.getElementById('document-modal-content');
                var docButtons = document.querySelectorAll('[data-open-doc-modal="true"]');
                var openEventOrder = <?= (int) $initialOpenEventOrder ?>;
                var openStartTimesByDocument = {};
                var activeDocumentKey = null;
                var activeDocumentRelevant = false;
                var activeOpenOrder = null;
                var activeDisplayOrder = null;

                function setAutosaveStatus(message) {
                    if (autosaveStatus) {
                        autosaveStatus.textContent = message;
                    }
                }

                function updateAutosaveMessage() {
                    setAutosaveStatus('Draft saved');
                    if (autosaveTimer) {
                        clearTimeout(autosaveTimer);
                    }
                    autosaveTimer = setTimeout(function () {
                        setAutosaveStatus('All changes saved');
                    }, 700);
                }

                function postDocumentEvent(payload) {
                    fetch('log_event.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    }).catch(function () {
                        // Keep task flow uninterrupted if event logging fails.
                    });
                }

                function renderDocumentContent(container, rawContent) {
                    if (!container) {
                        return;
                    }
                    container.innerHTML = '';

                    var text = String(rawContent || '');
                    var normalizedText = text.replace(/\r\n/g, '\n');
                    var blocks = normalizedText.split(/\n\s*\n/);

                    blocks.forEach(function (block) {
                        var trimmedBlock = block.trim();
                        if (!trimmedBlock) {
                            return;
                        }

                        var lines = trimmedBlock.split('\n').map(function (line) {
                            return line.trim();
                        }).filter(function (line) {
                            return line.length > 0;
                        });

                        if (lines.length === 0) {
                            return;
                        }
                        var paragraphBuffer = [];
                        var listBuffer = [];

                        function flushParagraph() {
                            if (paragraphBuffer.length === 0) {
                                return;
                            }
                            var paragraph = document.createElement('p');
                            paragraph.textContent = paragraphBuffer.join(' ');
                            container.appendChild(paragraph);
                            paragraphBuffer = [];
                        }

                        function flushList() {
                            if (listBuffer.length === 0) {
                                return;
                            }
                            var list = document.createElement('ul');
                            list.className = 'document-modal-list';
                            listBuffer.forEach(function (itemText) {
                                var li = document.createElement('li');
                                li.textContent = itemText;
                                list.appendChild(li);
                            });
                            container.appendChild(list);
                            listBuffer = [];
                        }

                        lines.forEach(function (line) {
                            var isHeadingLine = /^(key rules|key points):$/i.test(line);
                            var bulletMatch = line.match(/^[-*]\s+(.+)/);
                            var numberedMatch = line.match(/^\d+[.)]\s+(.+)/);

                            if (isHeadingLine) {
                                flushParagraph();
                                flushList();
                                var heading = document.createElement('h4');
                                heading.className = 'document-modal-subheading';
                                heading.textContent = line;
                                container.appendChild(heading);
                                return;
                            }

                            if (bulletMatch || numberedMatch) {
                                flushParagraph();
                                listBuffer.push((bulletMatch ? bulletMatch[1] : numberedMatch[1]).trim());
                                return;
                            }

                            flushList();
                            paragraphBuffer.push(line);
                        });

                        flushParagraph();
                        flushList();
                    });
                }

                function openModal(button) {
                    if (!overlay || !titleEl || !contentEl) {
                        return;
                    }
                    var title = button.dataset.docTitle || '';
                    var content = button.dataset.docContent || '';
                    var documentKey = button.dataset.docKey || '';
                    var isRelevant = button.dataset.docRelevant === '1';
                    var displayOrder = parseInt(button.dataset.displayOrder || '0', 10);

                    openEventOrder += 1;
                    openStartTimesByDocument[documentKey] = Date.now();
                    activeDocumentKey = documentKey;
                    activeDocumentRelevant = isRelevant;
                    activeOpenOrder = openEventOrder;
                    activeDisplayOrder = displayOrder;

                    postDocumentEvent({
                        task_number: taskNumber,
                        document_key: documentKey,
                        document_title: title,
                        condition_name: conditionName,
                        event_type: 'open',
                        event_order: activeOpenOrder,
                        display_order: activeDisplayOrder,
                        is_relevant: isRelevant
                    });

                    titleEl.textContent = title;
                    renderDocumentContent(contentEl, content);
                    overlay.classList.remove('hidden');
                    overlay.setAttribute('aria-hidden', 'false');
                }

                function closeModal() {
                    if (!overlay) {
                        return;
                    }
                    if (activeDocumentKey !== null) {
                        var startedAt = openStartTimesByDocument[activeDocumentKey] || Date.now();
                        var viewMs = Math.max(0, Date.now() - startedAt);
                        postDocumentEvent({
                            task_number: taskNumber,
                            document_key: activeDocumentKey,
                            document_title: titleEl ? titleEl.textContent : '',
                            condition_name: conditionName,
                            event_type: 'close',
                            view_ms: viewMs,
                            event_order: activeOpenOrder,
                            display_order: activeDisplayOrder,
                            is_relevant: activeDocumentRelevant
                        });
                        activeDocumentKey = null;
                        activeDocumentRelevant = false;
                        activeOpenOrder = null;
                        activeDisplayOrder = null;
                    }
                    overlay.classList.add('hidden');
                    overlay.setAttribute('aria-hidden', 'true');
                }

                if (docButtons.length > 0 && overlay && modalBox && closeButton) {
                    docButtons.forEach(function (button) {
                        button.addEventListener('click', function () {
                            openModal(button);
                        });
                    });
                    closeButton.addEventListener('click', closeModal);
                    overlay.addEventListener('click', function (event) {
                        if (!modalBox.contains(event.target)) {
                            closeModal();
                        }
                    });
                    document.addEventListener('keydown', function (event) {
                        if (event.key === 'Escape' && !overlay.classList.contains('hidden')) {
                            closeModal();
                        }
                    });
                }

                if (!form) {
                    return;
                }

                var autosaveKey = 'thesis_task_' + String(participantId) + '_' + taskView + '_' + String(taskNumber);

                function restoreAutosave() {
                    var raw = null;
                    try {
                        raw = localStorage.getItem(autosaveKey);
                    } catch (error) {
                        raw = null;
                    }
                    if (!raw) {
                        return;
                    }
                    var draft = null;
                    try {
                        draft = JSON.parse(raw);
                    } catch (error) {
                        draft = null;
                    }
                    if (!draft || typeof draft !== 'object') {
                        return;
                    }
                    if (taskView === 'review') {
                    } else {
                        if (typeof draft.selected_response_option === 'string') {
                            var responseRadio = form.querySelector('input[name="selected_response_option"][value="' + draft.selected_response_option + '"]');
                            if (responseRadio) {
                                responseRadio.checked = true;
                            }
                        }
                        if (typeof draft.confidence === 'string') {
                            var confidenceRadio = form.querySelector('input[name="confidence"][value="' + draft.confidence + '"]');
                            if (confidenceRadio) {
                                confidenceRadio.checked = true;
                            }
                        }
                        var customResponse = form.querySelector('textarea[name="custom_response_text"]');
                        if (customResponse && typeof draft.custom_response_text === 'string' && customResponse.value.trim() === '') {
                            customResponse.value = draft.custom_response_text;
                        }
                        if (typeof draft.verification_intention === 'string') {
                            var verificationInput = form.querySelector('input[name="verification_intention"]');
                            if (verificationInput && verificationInput.value.trim() === '') {
                                verificationInput.value = draft.verification_intention;
                            }
                        }
                    }
                    setAutosaveStatus('All changes saved');
                }

                function saveAutosave() {
                    var draft = {};
                    if (taskView === 'review') {
                    } else {
                        var selectedResponse = form.querySelector('input[name="selected_response_option"]:checked');
                        var confidence = form.querySelector('input[name="confidence"]:checked');
                        var customResponse = form.querySelector('textarea[name="custom_response_text"]');
                        var verificationInput = form.querySelector('input[name="verification_intention"]');
                        draft.selected_response_option = selectedResponse ? selectedResponse.value : '';
                        draft.confidence = confidence ? confidence.value : '';
                        draft.custom_response_text = customResponse ? customResponse.value : '';
                        draft.verification_intention = verificationInput ? verificationInput.value : '';
                    }
                    try {
                        localStorage.setItem(autosaveKey, JSON.stringify(draft));
                        updateAutosaveMessage();
                    } catch (error) {
                        // Ignore autosave failures silently.
                    }
                }

                function toggleCustomResponseField() {
                    if (!customResponseWrapper) {
                        return;
                    }
                    var selectedResponse = form.querySelector('input[name="selected_response_option"]:checked');
                    if (selectedResponse && selectedResponse.value === 'other') {
                        customResponseWrapper.classList.remove('hidden');
                    } else {
                        customResponseWrapper.classList.add('hidden');
                    }
                }

                function toggleError(el, shouldShow) {
                    if (!el) {
                        return;
                    }
                    if (shouldShow) {
                        el.classList.remove('hidden');
                    } else {
                        el.classList.add('hidden');
                    }
                }

                function validateForm() {
                    var firstInvalid = null;
                    if (taskView === 'review') {
                        return { isValid: true, firstInvalid: null };
                    }

                    var hasSelectedResponse = form.querySelector('input[name="selected_response_option"]:checked') !== null;
                    var hasConfidence = form.querySelector('input[name="confidence"]:checked') !== null;
                    var customResponse = form.querySelector('textarea[name="custom_response_text"]');
                    var selectedResponse = form.querySelector('input[name="selected_response_option"]:checked');
                    var requiresCustomResponse = selectedResponse && selectedResponse.value === 'other';
                    var hasCustomResponse = !requiresCustomResponse || (customResponse && customResponse.value.trim() !== '');
                    var verificationInput = form.querySelector('input[name="verification_intention"]');
                    var hasVerification = !isActiveCondition || (verificationInput && verificationInput.value.trim() !== '');

                    toggleError(selectedResponseError, !hasSelectedResponse);
                    toggleError(confidenceError, !hasConfidence);
                    toggleError(customResponseError, !hasCustomResponse);
                    toggleError(verificationIntentionError, !hasVerification);

                    if (!hasSelectedResponse) {
                        firstInvalid = form.querySelector('input[name="selected_response_option"]');
                    } else if (!hasConfidence) {
                        firstInvalid = form.querySelector('input[name="confidence"]');
                    } else if (!hasCustomResponse) {
                        firstInvalid = customResponse;
                    } else if (!hasVerification) {
                        firstInvalid = verificationInput;
                    }

                    return {
                        isValid: hasSelectedResponse && hasConfidence && hasCustomResponse && hasVerification,
                        firstInvalid: firstInvalid
                    };
                }

                restoreAutosave();
                toggleCustomResponseField();
                form.querySelectorAll('input[type="radio"], input[type="text"], textarea').forEach(function (input) {
                    input.addEventListener('change', saveAutosave);
                    if (input.tagName === 'TEXTAREA') {
                        input.addEventListener('input', saveAutosave);
                    }
                });
                form.querySelectorAll('input[name="selected_response_option"]').forEach(function (radio) {
                    radio.addEventListener('change', toggleCustomResponseField);
                });

                form.addEventListener('submit', function (event) {
                    var validation = validateForm();
                    if (!validation.isValid) {
                        event.preventDefault();
                        if (validation.firstInvalid && typeof validation.firstInvalid.scrollIntoView === 'function') {
                            validation.firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            if (typeof validation.firstInvalid.focus === 'function') {
                                validation.firstInvalid.focus();
                            }
                        }
                        return;
                    }

                    try {
                        localStorage.removeItem(autosaveKey);
                    } catch (error) {
                        // Ignore cleanup failures silently.
                    }

                    if (submitButton) {
                        submitButton.disabled = true;
                        submitButton.textContent = 'Submitting...';
                        submitButton.classList.add('opacity-60', 'cursor-not-allowed');
                    }
                });
            })();
        </script>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../views/footer.php'; ?>
