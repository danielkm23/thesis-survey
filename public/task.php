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
$enabledTaskNumbers = [1]; // Temporary MVP gate
$totalTasks = count($enabledTaskNumbers);
$errorMessage = null;
$task = null;
$documentsInDisplayOrder = [];
$initialOpenEventOrder = 0;

if (
    $taskNumber === false
    || $taskNumber === null
    || !in_array($taskNumber, $enabledTaskNumbers, true)
    || !isset($tasks[$taskNumber])
) {
    $errorMessage = 'Invalid task. Please return to the introduction page and try again.';
} else {
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
}

$conditionName = (string) session_get('condition_name', '');
$showPassiveNotice = $conditionName === 'passive';
$isActiveCondition = $conditionName === 'active';
$taskStep = $taskNumber !== false && $taskNumber !== null ? $taskNumber : 1;
$progressPercent = (int) round(($taskStep / max(1, $totalTasks + 1)) * 100);

$pageTitle = 'Task ' . ($taskNumber ?: '');
require __DIR__ . '/../views/header.php';
?>

<main class="max-w-6xl mx-auto px-4 py-6">
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
        <section class="bg-white shadow rounded-xl p-4 mb-4">
            <div class="flex items-center justify-between mb-2">
                <p class="text-sm text-slate-500">Step <?= e((string) $taskStep) ?> of <?= e((string) ($totalTasks + 1)) ?></p>
                <p class="text-sm text-slate-500">Task <?= e((string) $task['number']) ?> of <?= e((string) $totalTasks) ?></p>
            </div>
            <div class="w-full h-2 bg-slate-200 rounded">
                <div class="h-2 bg-blue-600 rounded" style="width: <?= e((string) $progressPercent) ?>%"></div>
            </div>
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <section class="lg:col-span-2 bg-white shadow rounded-xl p-5">
                <h1 class="text-2xl font-bold text-slate-800 mb-4"><?= e($task['title']) ?></h1>

                <div class="mb-4">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 mb-2">Scenario</h2>
                    <p class="text-slate-700 leading-[1.4] whitespace-pre-line"><?= e($task['scenario']) ?></p>
                </div>

                <?php if (!empty($task['work_task'])): ?>
                    <div class="mb-4">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 mb-2">Task</h2>
                        <p class="text-slate-700 leading-[1.4] whitespace-pre-line"><?= e((string) $task['work_task']) ?></p>
                    </div>
                <?php endif; ?>

                <div>
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 mb-2">AI Output</h2>
                    <?php if ($showPassiveNotice): ?>
                        <p class="mb-3 text-[0.9rem] leading-[1.45] font-normal text-gray-500">
                            Note:
                            AI-generated responses may contain inaccuracies or incomplete information. Consider reviewing
                            the available information before making your decision.
                        </p>
                    <?php endif; ?>
                    <div class="p-4 rounded-lg border border-slate-200 bg-slate-50 text-slate-700 leading-[1.4]">
                        <?= nl2br(e($task['ai_output'])) ?>
                    </div>
                </div>
            </section>

            <aside class="bg-white shadow rounded-xl p-5">
                <h2 class="text-base font-semibold text-slate-800 mb-3">Additional information (optional)</h2>
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
                            class="w-full text-left px-4 py-3 rounded-lg border border-slate-200 hover:bg-slate-50"
                        >
                            <?= e($document['display_order'] . '. ' . $document['title']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </aside>
        </div>

        <section class="mt-5 bg-white shadow rounded-xl p-5">
            <form id="task-form" method="post" action="save_task.php">
                <input type="hidden" name="task_number" value="<?= e((string) $task['number']) ?>">

                <?php if ($isActiveCondition): ?>
                    <div class="mb-4">
                        <h3 class="text-base font-semibold text-slate-800 mb-2">Before submitting your response</h3>
                        <p class="text-sm text-slate-600 mb-3">
                            Indicate what you would want to verify before using the AI-generated response.
                        </p>
                        <div class="space-y-1.5 text-slate-700">
                            <label class="radio-option">
                                <input type="radio" name="verification_intention" value="specific_claim_or_number" class="custom-radio" required>
                                <span class="radio-option-text">A specific claim or number</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="verification_intention" value="policy_rule_or_requirement" class="custom-radio">
                                <span class="radio-option-text">A policy rule or requirement</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="verification_intention" value="overall_recommendation" class="custom-radio">
                                <span class="radio-option-text">The overall recommendation</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="verification_intention" value="would_not_verify" class="custom-radio">
                                <span class="radio-option-text">I would not verify anything</span>
                            </label>
                        </div>
                        <p id="verification-intention-error" class="mt-2 text-sm text-red-600 hidden">
                            Please select one verification option before submitting.
                        </p>
                    </div>
                <?php endif; ?>

                <fieldset class="mb-4">
                    <legend class="text-base font-semibold text-slate-800 mb-3">
                        Please select the option that best reflects what you would actually do in this situation.
                    </legend>
                    <div class="space-y-1.5 text-slate-700">
                        <label class="block">
                            <span class="radio-option">
                                <input type="radio" name="reliance_choice" value="use_exact" class="custom-radio" required>
                                <span class="radio-option-text">I used the response exactly as written</span>
                            </span>
                            <span class="radio-help block text-sm text-slate-500">
                                You would send the AI-generated response without making any changes.
                            </span>
                        </label>
                        <label class="block">
                            <span class="radio-option">
                                <input type="radio" name="reliance_choice" value="use_small_changes" class="custom-radio">
                                <span class="radio-option-text">I used the response with small changes (e.g., wording or tone adjustments)</span>
                            </span>
                            <span class="radio-help block text-sm text-slate-500">
                                You made minor edits that did not change the main content.
                            </span>
                        </label>
                        <label class="block">
                            <span class="radio-option">
                                <input type="radio" name="reliance_choice" value="use_substantial_changes" class="custom-radio">
                                <span class="radio-option-text">I used some parts of the response but changed substantial content</span>
                            </span>
                            <span class="radio-help block text-sm text-slate-500">
                                You kept parts of the response but modified key information or structure.
                            </span>
                        </label>
                        <label class="block">
                            <span class="radio-option">
                                <input type="radio" name="reliance_choice" value="did_not_use" class="custom-radio">
                                <span class="radio-option-text">I did not use the response and wrote my own answer</span>
                            </span>
                            <span class="radio-help block text-sm text-slate-500">
                                You ignored the AI-generated response and created a completely new answer.
                            </span>
                        </label>
                    </div>
                    <p id="reliance-error" class="mt-2 text-sm text-red-600 hidden">Please select one reliance option.</p>
                </fieldset>

                <div class="mb-4">
                    <label for="final_response" class="block text-base font-semibold text-slate-800 mb-2">
                        Please write the response you would send in this situation.
                    </label>
                    <p class="text-sm text-slate-600 mb-2">You may use or modify the AI-generated response if you wish.</p>
                    <textarea
                        id="final_response"
                        name="final_response"
                        rows="5"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Write your final response here..."
                        required
                    ></textarea>
                    <p id="final-response-error" class="mt-2 text-sm text-red-600 hidden">Please enter a final response.</p>
                </div>

                <fieldset class="mb-4">
                    <legend class="text-base font-semibold text-slate-800 mb-3">
                        How confident are you in your decision?
                    </legend>
                    <div class="space-y-1.5 text-slate-700">
                        <label class="radio-option">
                            <input type="radio" name="confidence" value="1" class="custom-radio" required>
                            <span class="radio-option-text">1 — Not at all confident</span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="confidence" value="2" class="custom-radio">
                            <span class="radio-option-text">2 — Slightly confident</span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="confidence" value="3" class="custom-radio">
                            <span class="radio-option-text">3 — Moderately confident</span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="confidence" value="4" class="custom-radio">
                            <span class="radio-option-text">4 — Very confident</span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="confidence" value="5" class="custom-radio">
                            <span class="radio-option-text">5 — Extremely confident</span>
                        </label>
                    </div>
                    <p id="confidence-error" class="mt-2 text-sm text-red-600 hidden">Please select a confidence value.</p>
                </fieldset>

                <button
                    type="submit"
                    id="task-submit-button"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-5 py-3 rounded-lg transition"
                >
                    Submit Task
                </button>
            </form>
        </section>

        <div
            id="document-modal-overlay"
            class="hidden fixed inset-0 z-50 bg-slate-900/50 px-4 py-8"
            aria-hidden="true"
        >
            <div class="flex items-start justify-center min-h-full">
                <div
                    id="document-modal-box"
                    class="w-full max-w-2xl bg-white rounded-xl shadow-xl border border-slate-200"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="document-modal-title"
                >
                    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
                        <h3 id="document-modal-title" class="text-lg font-semibold text-slate-800"></h3>
                        <button
                            type="button"
                            id="document-modal-close"
                            class="text-slate-500 hover:text-slate-700 px-2 py-1 rounded"
                        >
                            Close
                        </button>
                    </div>
                    <div class="px-6 py-5 max-h-[65vh] overflow-y-auto">
                        <p id="document-modal-content" class="text-slate-700 leading-relaxed whitespace-pre-line"></p>
                    </div>
                </div>
            </div>
        </div>

        <script>
            (function () {
                var overlay = document.getElementById('document-modal-overlay');
                var modalBox = document.getElementById('document-modal-box');
                var closeButton = document.getElementById('document-modal-close');
                var titleEl = document.getElementById('document-modal-title');
                var contentEl = document.getElementById('document-modal-content');
                var docButtons = document.querySelectorAll('[data-open-doc-modal="true"]');
                var taskNumber = <?= (int) $task['number'] ?>;
                var openEventOrder = <?= (int) $initialOpenEventOrder ?>;
                var openStartTimesByDocument = {};
                var activeDocumentKey = null;
                var activeDocumentRelevant = false;
                var activeOpenOrder = null;
                var activeDisplayOrder = null;
                var form = document.getElementById('task-form');
                var submitButton = document.getElementById('task-submit-button');
                var isActiveCondition = <?= $isActiveCondition ? 'true' : 'false' ?>;
                var relianceError = document.getElementById('reliance-error');
                var finalResponse = document.getElementById('final_response');
                var finalResponseError = document.getElementById('final-response-error');
                var confidenceError = document.getElementById('confidence-error');
                var verificationIntentionError = document.getElementById('verification-intention-error');
                var verificationOptions = form ? form.querySelectorAll('input[name="verification_intention"]') : [];

                function postDocumentEvent(payload) {
                    fetch('log_event.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    }).catch(function () {
                        // Keep task flow uninterrupted if event logging fails.
                    });
                }

                function openModal(button) {
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
                        event_type: 'open',
                        event_order: activeOpenOrder,
                        display_order: activeDisplayOrder,
                        is_relevant: isRelevant
                    });

                    titleEl.textContent = title || '';
                    contentEl.textContent = content || '';
                    overlay.classList.remove('hidden');
                    overlay.setAttribute('aria-hidden', 'false');
                }

                function closeModal() {
                    if (activeDocumentKey !== null) {
                        var startedAt = openStartTimesByDocument[activeDocumentKey] || Date.now();
                        var viewMs = Math.max(0, Date.now() - startedAt);

                        postDocumentEvent({
                            task_number: taskNumber,
                            document_key: activeDocumentKey,
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

                if (form) {
                    function showOrHideError(el, shouldShow) {
                        if (!el) {
                            return;
                        }
                        if (shouldShow) {
                            el.classList.remove('hidden');
                        } else {
                            el.classList.add('hidden');
                        }
                    }

                    function validateTaskForm() {
                        var hasReliance = form.querySelector('input[name="reliance_choice"]:checked') !== null;
                        var hasConfidence = form.querySelector('input[name="confidence"]:checked') !== null;
                        var hasFinalResponse = finalResponse && finalResponse.value.trim() !== '';
                        var hasVerificationIntention = true;

                        if (isActiveCondition) {
                            hasVerificationIntention = form.querySelector('input[name="verification_intention"]:checked') !== null;
                        }

                        showOrHideError(relianceError, !hasReliance);
                        showOrHideError(confidenceError, !hasConfidence);
                        showOrHideError(finalResponseError, !hasFinalResponse);
                        showOrHideError(verificationIntentionError, isActiveCondition && !hasVerificationIntention);

                        return hasReliance && hasConfidence && hasFinalResponse && hasVerificationIntention;
                    }

                    function updateSubmitEnabledState() {
                        if (!submitButton) {
                            return;
                        }
                        if (isActiveCondition) {
                            var hasVerification = form.querySelector('input[name="verification_intention"]:checked') !== null;
                            submitButton.disabled = !hasVerification;
                            if (hasVerification) {
                                submitButton.classList.remove('opacity-60', 'cursor-not-allowed');
                            } else {
                                submitButton.classList.add('opacity-60', 'cursor-not-allowed');
                            }
                        } else {
                            submitButton.disabled = false;
                            submitButton.classList.remove('opacity-60', 'cursor-not-allowed');
                        }
                    }

                    updateSubmitEnabledState();
                    verificationOptions.forEach(function (option) {
                        option.addEventListener('change', updateSubmitEnabledState);
                    });

                    form.addEventListener('submit', function (event) {
                        if (!validateTaskForm()) {
                            event.preventDefault();
                            return;
                        }

                        if (submitButton) {
                            submitButton.disabled = true;
                            submitButton.textContent = 'Submitting...';
                            submitButton.classList.add('opacity-60', 'cursor-not-allowed');
                        }
                    });
                }
            })();
        </script>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../views/footer.php'; ?>
