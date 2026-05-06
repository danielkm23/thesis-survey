<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/db.php';

if (!has_valid_participant_session()) {
    redirect('index.php');
}

if (session_get('postsurvey_started_at') === null) {
    session_set('postsurvey_started_at', date('Y-m-d H:i:s'));
}

function required_int_post(string $key, int $min, int $max): int
{
    $value = filter_input(INPUT_POST, $key, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => $min, 'max_range' => $max],
    ]);

    if ($value === false || $value === null) {
        http_response_code(400);
        exit('Invalid value for ' . $key . '.');
    }

    return $value;
}

function required_numeric_post(string $key): string
{
    $raw = trim((string) ($_POST[$key] ?? ''));
    if ($raw === '' || !is_numeric($raw)) {
        http_response_code(400);
        exit('Invalid value for ' . $key . '.');
    }

    return $raw;
}

function required_choice_post(string $key, array $allowedValues): string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    if (!in_array($value, $allowedValues, true)) {
        http_response_code(400);
        exit('Invalid value for ' . $key . '.');
    }

    return $value;
}

function has_required_keys(array $data, array $keys): bool
{
    foreach ($keys as $key) {
        if (!array_key_exists($key, $data)) {
            return false;
        }
    }

    return true;
}

$allowedSteps = ['ai', 'crt', 'instruction_notice', 'demographics'];
$step = (string) ($_GET['step'] ?? 'ai');
if (!in_array($step, $allowedSteps, true)) {
    $step = 'ai';
}

$storedAnswers = session_get('postsurvey_answers', []);
if (!is_array($storedAnswers)) {
    $storedAnswers = [];
}

$participantId = (int) session_get('participant_id', 0);
if ($participantId > 0) {
    try {
        $existingPostsurveyStmt = db()->prepare(
            'SELECT *
             FROM postsurvey_responses
             WHERE participant_id = :participant_id
             ORDER BY id DESC
             LIMIT 1'
        );
        $existingPostsurveyStmt->execute([
            ':participant_id' => $participantId,
        ]);
        $existingPostsurveyResponse = $existingPostsurveyStmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($existingPostsurveyResponse)) {
            $dbAnswerKeys = [
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
            ];
            foreach ($dbAnswerKeys as $key) {
                if (!array_key_exists($key, $storedAnswers) && array_key_exists($key, $existingPostsurveyResponse)) {
                    $storedAnswers[$key] = $existingPostsurveyResponse[$key];
                }
            }
        }
    } catch (Throwable $e) {
        // Keep page usable even if DB prefill fails.
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 'ai') {
        $allowedAiExperience = ['never', 'less_than_monthly', 'few_times_per_month', 'few_times_per_week', 'daily'];
        $storedAnswers['ai_lit_1'] = required_int_post('ai_lit_1', 1, 5);
        $storedAnswers['ai_lit_2'] = required_int_post('ai_lit_2', 1, 5);
        $storedAnswers['ai_lit_3'] = required_int_post('ai_lit_3', 1, 5);
        $storedAnswers['ai_lit_4'] = required_int_post('ai_lit_4', 1, 5);
        $storedAnswers['ai_experience'] = required_choice_post('ai_experience', $allowedAiExperience);
        session_set('postsurvey_answers', $storedAnswers);
        redirect('postsurvey.php?step=crt');
    }

    if ($step === 'crt') {
        $storedAnswers['crt_1'] = required_numeric_post('crt_1');
        $storedAnswers['crt_2'] = required_numeric_post('crt_2');
        $storedAnswers['crt_3'] = required_numeric_post('crt_3');
        session_set('postsurvey_answers', $storedAnswers);
        redirect('postsurvey.php?step=instruction_notice');
    }

    if ($step === 'instruction_notice') {
        $storedAnswers['serious_effort'] = required_int_post('serious_effort', 1, 5);
        $storedAnswers['instructions_clarity'] = required_int_post('instructions_clarity', 1, 5);
        $storedAnswers['task_realism'] = required_int_post('task_realism', 1, 5);
        $storedAnswers['instruction_notice'] = required_int_post('instruction_notice', 1, 5);
        session_set('postsurvey_answers', $storedAnswers);
        redirect('postsurvey.php?step=demographics');
    }
}

$requiredAiKeys = ['ai_lit_1', 'ai_lit_2', 'ai_lit_3', 'ai_lit_4'];
$requiredCrtKeys = ['crt_1', 'crt_2', 'crt_3'];
$requiredInstructionNoticeKeys = ['serious_effort', 'instructions_clarity', 'task_realism', 'instruction_notice'];
$hasAiAnswers = has_required_keys($storedAnswers, $requiredAiKeys);
$hasCrtAnswers = has_required_keys($storedAnswers, $requiredCrtKeys);
$hasInstructionNoticeAnswer = has_required_keys($storedAnswers, $requiredInstructionNoticeKeys);
$prefillAiExperience = (string) ($storedAnswers['ai_experience'] ?? '');
$prefillAge = (string) ($storedAnswers['age'] ?? '');
$prefillGender = (string) ($storedAnswers['gender'] ?? '');
$prefillEducation = (string) ($storedAnswers['education'] ?? '');

if ($step === 'crt' && !$hasAiAnswers) {
    redirect('postsurvey.php?step=ai');
}

if (($step === 'instruction_notice' || $step === 'demographics') && !$hasAiAnswers) {
    redirect('postsurvey.php?step=ai');
}

if (($step === 'instruction_notice' || $step === 'demographics') && !$hasCrtAnswers) {
    redirect('postsurvey.php?step=crt');
}

if ($step === 'demographics' && !$hasInstructionNoticeAnswer) {
    redirect('postsurvey.php?step=instruction_notice');
}

$stepNumbers = [
    'ai' => 1,
    'crt' => 2,
    'instruction_notice' => 3,
    'demographics' => 4,
];
$enabledTaskNumbers = [1, 2]; // Temporary MVP gate (keep in sync with task flow)
$totalTasks = count($enabledTaskNumbers);
$totalPostSurveyParts = count($stepNumbers);
$currentStepNumber = $stepNumbers[$step];
$taskPhasesPerTask = 2; // review + decision
$totalTaskPhases = $totalTasks * $taskPhasesPerTask;
$currentStudyStep = $totalTaskPhases + $currentStepNumber;
$totalStudySteps = $totalTaskPhases + $totalPostSurveyParts;
$progressPercent = (int) round(($currentStudyStep / max(1, $totalStudySteps)) * 100);

$aiLitItems = [
    1 => [
        'question' => 'AI-generated responses can sound convincing even when they are inaccurate.',
    ],
    2 => [
        'question' => 'When information from an AI system is important, it is worth checking its accuracy.',
    ],
    3 => [
        'question' => 'AI systems may produce information without relying on verified or reliable sources.',
    ],
    4 => [
        'question' => 'Even when an AI response appears clear, it may still be incomplete or uncertain.',
    ],
];
$likertLabels = [
    1 => 'Strongly disagree',
    2 => 'Disagree',
    3 => 'Neither agree nor disagree',
    4 => 'Agree',
    5 => 'Strongly agree',
];

$taskEvalItems = [
    [
        'field' => 'serious_effort',
        'text' => 'I answered the tasks carefully and made a serious effort to choose the most accurate response.',
    ],
    [
        'field' => 'instructions_clarity',
        'text' => 'The instructions and response options were clear.',
    ],
    [
        'field' => 'task_realism',
        'text' => 'The tasks felt realistic and similar to real workplace situations.',
    ],
    [
        'field' => 'instruction_notice',
        'text' => 'I noticed messages that encouraged me to review, verify, or check the AI-generated draft before choosing a response.',
    ],
];

$surveyCardClass = 'bg-white shadow rounded-xl p-4 sm:p-5';
$surveyTitleClass = 'text-base sm:text-lg leading-6 font-semibold text-slate-800 mb-3';
$surveyIntroClass = 'text-sm sm:text-base font-normal text-slate-600 mb-4';
$surveySectionHeadingClass = 'text-base font-semibold text-slate-800 mb-2';
$surveyHelperTextClass = 'text-sm text-slate-600 mb-2';
$surveyFooterCardClass = 'bg-white shadow rounded-xl p-4 sm:p-5';
$surveyFooterActionsClass = 'flex flex-col sm:flex-row items-stretch sm:items-center gap-3';
$surveyFooterButtonClass = 'w-full sm:w-auto accent-bg accent-bg-hover text-white font-medium px-5 py-3 rounded-lg transition';
$surveyBackButtonClass = 'inline-block w-full sm:w-auto text-center bg-slate-100 hover:bg-slate-200 text-slate-800 font-medium px-5 py-3 rounded-lg transition';
$surveyAutosaveClass = 'mt-2 text-xs text-slate-500';
$crtQuestionBlockClass = 'space-y-2.5';
$crtInputRowClass = 'flex items-center gap-2 w-full max-w-sm';
$crtUnitLabelClass = 'w-16 shrink-0 text-right text-sm font-medium text-slate-700';
$crtInputClass = 'h-10 w-full min-w-0 max-w-[14rem] rounded-lg border border-slate-300 px-3 text-sm';

$pageTitle = 'Post-Survey';
require __DIR__ . '/../views/header.php';
?>

<main class="max-w-6xl mx-auto px-4 py-8">
    <section class="mb-3 px-0.5">
        <p class="text-sm text-slate-700 mb-1.5">Step <?= e((string) $currentStudyStep) ?> of <?= e((string) $totalStudySteps) ?> — <?= e((string) $progressPercent) ?>% complete</p>
        <div class="w-full h-[6px] bg-slate-200 rounded">
            <div class="h-[6px] accent-bg rounded" style="width: <?= e((string) $progressPercent) ?>%"></div>
        </div>
    </section>

    <?php if ($step === 'ai'): ?>
        <form id="postsurvey-step-form" method="post" action="postsurvey.php?step=ai" class="space-y-4">
            <section class="<?= e($surveyCardClass) ?>">
                <h2 class="<?= e($surveyTitleClass) ?>">Questions about evaluating AI-generated information</h2>
                <p class="<?= e($surveyIntroClass) ?>">
                    Please indicate how much you agree or disagree with the following statements about evaluating information generated by AI systems.
                </p>
                <div class="overflow-x-auto hidden md:block">
                    <table class="min-w-full border border-slate-200 rounded-lg overflow-hidden">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="text-left text-sm font-semibold text-slate-700 px-2 py-1.5 border-b border-slate-200 w-1/2">
                                    Statement
                                </th>
                                <?php foreach ($likertLabels as $value => $label): ?>
                                    <th class="text-center text-[11px] sm:text-xs leading-snug font-semibold text-slate-700 px-1 py-1.5 border-b border-slate-200 min-w-[4.75rem] sm:min-w-[5.5rem] md:min-w-[6.25rem]">
                                        <?= e($value . ' - ' . $label) ?>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($aiLitItems as $index => $item): ?>
                                <tr class="group h-14 border-b border-slate-200 last:border-b-0 hover:bg-slate-50 transition-colors">
                                    <td class="align-middle text-slate-800 text-sm px-2 py-1.5 break-words">
                                        <?= e($item['question']) ?>
                                    </td>
                                    <?php foreach ($likertLabels as $optionValue => $label): ?>
                                        <td class="text-center px-1 py-1.5 group-hover:bg-slate-50 hover:bg-slate-100 transition-colors align-middle">
                                            <input
                                                type="radio"
                                                name="ai_lit_<?= $index ?>"
                                                value="<?= $optionValue ?>"
                                                required
                                                class="h-4 w-4 cursor-pointer"
                                                aria-label="<?= e('Item ' . $index . ', ' . $optionValue . ' - ' . $label) ?>"
                                                <?= (string) $optionValue === (string) ($storedAnswers['ai_lit_' . $index] ?? '') ? 'checked' : '' ?>
                                            >
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="md:hidden">
                    <?php foreach ($aiLitItems as $index => $item): ?>
                        <div class="bg-white rounded-xl border border-slate-200 p-3 mb-2.5 last:mb-0 shadow-sm">
                            <p class="text-sm text-slate-800 mb-2"><?= e($item['question']) ?></p>
                            <?php foreach ($likertLabels as $optionValue => $label): ?>
                                <?php $isChecked = (string) $optionValue === (string) ($storedAnswers['ai_lit_' . $index] ?? ''); ?>
                                <label class="flex items-center gap-3 p-2.5 rounded-lg border mb-1.5 last:mb-0 text-sm cursor-pointer <?= $isChecked ? 'border-blue-500 bg-blue-50 text-slate-900' : 'border-slate-200 text-slate-700' ?>">
                                    <input
                                        type="radio"
                                        name="ai_lit_<?= $index ?>"
                                        value="<?= $optionValue ?>"
                                        required
                                        class="h-4 w-4 cursor-pointer"
                                        aria-label="<?= e('Item ' . $index . ', ' . $optionValue . ' - ' . $label) ?>"
                                        <?= $isChecked ? 'checked' : '' ?>
                                    >
                                    <span><?= e($optionValue . ' - ' . $label) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <fieldset class="mt-4">
                    <legend class="<?= e($surveySectionHeadingClass) ?>">How often do you use AI tools (e.g., ChatGPT, Copilot, Gemini)?</legend>
                    <div class="space-y-1 text-sm text-slate-700">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="radio" name="ai_experience" value="never" required class="h-4 w-4" <?= $prefillAiExperience === 'never' ? 'checked' : '' ?>>
                            <span>Never</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="radio" name="ai_experience" value="less_than_monthly" required class="h-4 w-4" <?= $prefillAiExperience === 'less_than_monthly' ? 'checked' : '' ?>>
                            <span>Less than once per month</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="radio" name="ai_experience" value="few_times_per_month" required class="h-4 w-4" <?= $prefillAiExperience === 'few_times_per_month' ? 'checked' : '' ?>>
                            <span>A few times per month</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="radio" name="ai_experience" value="few_times_per_week" required class="h-4 w-4" <?= $prefillAiExperience === 'few_times_per_week' ? 'checked' : '' ?>>
                            <span>A few times per week</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="radio" name="ai_experience" value="daily" required class="h-4 w-4" <?= $prefillAiExperience === 'daily' ? 'checked' : '' ?>>
                            <span>Daily</span>
                        </label>
                    </div>
                </fieldset>
            </section>

            <section class="<?= e($surveyFooterCardClass) ?>">
                <p id="postsurvey-step-error" class="mb-3 text-sm text-red-600 hidden">Please complete all required fields.</p>
                <button
                    type="submit"
                    class="<?= e($surveyFooterButtonClass) ?>"
                >
                    Next →
                </button>
                <p id="postsurvey-autosave-status" class="<?= e($surveyAutosaveClass) ?>" aria-live="polite">All changes saved</p>
            </section>
        </form>
    <?php elseif ($step === 'crt'): ?>
        <form id="postsurvey-step-form" method="post" action="postsurvey.php?step=crt" class="space-y-4">
            <section class="<?= e($surveyCardClass) ?>">
                <h2 class="<?= e($surveyTitleClass) ?>">Short reasoning questions</h2>
                <p class="<?= e($surveyIntroClass) ?>">
                    Please answer the following short reasoning questions.<br>
                    Select the answer you believe is correct.
                </p>

                <div class="space-y-4">
                    <div class="<?= e($crtQuestionBlockClass) ?>">
                        <label class="block <?= e($surveySectionHeadingClass) ?>">
                            1. A bat and a ball cost EUR 1.10 in total. The bat costs EUR 1 more than the ball.
                            How much does the ball cost?
                        </label>
                        <p class="<?= e($surveyHelperTextClass) ?>">Enter your answer in euros.</p>
                        <div class="<?= e($crtInputRowClass) ?>">
                            <span class="<?= e($crtUnitLabelClass) ?>">EUR</span>
                            <input
                                type="text"
                                inputmode="decimal"
                                pattern="[0-9]+(\.[0-9]{1,2})?"
                                name="crt_1"
                                required
                                placeholder="0.00"
                                title="Use dot format, e.g., 0.05"
                                value="<?= e((string) ($storedAnswers['crt_1'] ?? '')) ?>"
                                class="<?= e($crtInputClass) ?>"
                            >
                        </div>
                    </div>

                    <div class="<?= e($crtQuestionBlockClass) ?>">
                        <label class="block <?= e($surveySectionHeadingClass) ?>">
                            2. If it takes 5 machines 5 minutes to make 5 widgets, how long would it take
                            100 machines to make 100 widgets?
                        </label>
                        <p class="<?= e($surveyHelperTextClass) ?>">Enter your answer in whole minutes.</p>
                        <div class="<?= e($crtInputRowClass) ?>">
                            <span class="<?= e($crtUnitLabelClass) ?>">minutes</span>
                            <input
                                type="number"
                                step="1"
                                min="0"
                                inputmode="numeric"
                                pattern="[0-9]+"
                                name="crt_2"
                                required
                                placeholder="0"
                                title="Please enter a whole number."
                                value="<?= e((string) ($storedAnswers['crt_2'] ?? '')) ?>"
                                class="<?= e($crtInputClass) ?>"
                            >
                        </div>
                    </div>

                    <div class="<?= e($crtQuestionBlockClass) ?>">
                        <label class="block <?= e($surveySectionHeadingClass) ?>">
                            3. In a lake, there is a patch of lily pads. Every day, the patch doubles in size.
                            If it takes 48 days to cover the whole lake, how long would it take to cover half the lake?
                        </label>
                        <p class="<?= e($surveyHelperTextClass) ?>">Enter your answer in whole days.</p>
                        <div class="<?= e($crtInputRowClass) ?>">
                            <span class="<?= e($crtUnitLabelClass) ?>">days</span>
                            <input
                                type="number"
                                step="1"
                                min="0"
                                inputmode="numeric"
                                pattern="[0-9]+"
                                name="crt_3"
                                required
                                placeholder="0"
                                title="Please enter a whole number."
                                value="<?= e((string) ($storedAnswers['crt_3'] ?? '')) ?>"
                                class="<?= e($crtInputClass) ?>"
                            >
                        </div>
                    </div>
                </div>
            </section>

            <section class="<?= e($surveyFooterCardClass) ?>">
                <p id="postsurvey-step-error" class="mb-3 text-sm text-red-600 hidden">Please complete all required fields.</p>
                <div class="<?= e($surveyFooterActionsClass) ?>">
                    <a href="postsurvey.php?step=ai" class="<?= e($surveyBackButtonClass) ?>">
                        Back
                    </a>
                    <button
                        type="submit"
                        class="<?= e($surveyFooterButtonClass) ?>"
                    >
                        Next →
                    </button>
                </div>
                <p id="postsurvey-autosave-status" class="<?= e($surveyAutosaveClass) ?>" aria-live="polite">All changes saved</p>
            </section>
        </form>
    <?php elseif ($step === 'instruction_notice'): ?>
        <form id="postsurvey-step-form" method="post" action="postsurvey.php?step=instruction_notice" class="space-y-4">
            <section class="bg-white shadow rounded-xl p-5">
                <h2 class="text-lg font-semibold text-slate-800 mb-3">Task evaluation</h2>
                <p class="text-sm text-slate-600 mb-2">
                    Please indicate how much you agree or disagree with the following statements about the tasks.
                </p>
                <div class="overflow-x-auto hidden md:block">
                    <table class="min-w-full border border-slate-200 rounded-lg overflow-hidden">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="text-left text-sm font-semibold text-slate-700 px-2 py-1.5 border-b border-slate-200 w-1/2">
                                    Statement
                                </th>
                                <?php foreach ($likertLabels as $value => $label): ?>
                                    <th class="text-center text-[11px] sm:text-xs leading-snug font-semibold text-slate-700 px-1 py-1.5 border-b border-slate-200 min-w-[4.75rem] sm:min-w-[5.5rem] md:min-w-[6.25rem]">
                                        <?= e($value . ' - ' . $label) ?>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($taskEvalItems as $rowIndex => $evalItem): ?>
                                <?php
                                $fieldName = $evalItem['field'];
                                $storedVal = (string) ($storedAnswers[$fieldName] ?? '');
                                ?>
                                <tr class="group h-14 border-b border-slate-200 last:border-b-0 hover:bg-slate-50 transition-colors">
                                    <td class="align-middle text-slate-800 text-sm px-2 py-1.5 break-words">
                                        <?= e((string) $evalItem['text']) ?>
                                    </td>
                                    <?php foreach ($likertLabels as $optionValue => $label): ?>
                                        <td class="text-center px-1 py-1.5 group-hover:bg-slate-50 hover:bg-slate-100 transition-colors align-middle">
                                            <input
                                                type="radio"
                                                name="<?= e($fieldName) ?>"
                                                value="<?= $optionValue ?>"
                                                required
                                                class="h-4 w-4 cursor-pointer"
                                                aria-label="<?= e('Task evaluation ' . ($rowIndex + 1) . ', ' . $optionValue . ' - ' . $label) ?>"
                                                <?= (string) $optionValue === $storedVal ? 'checked' : '' ?>
                                            >
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="md:hidden">
                    <?php foreach ($taskEvalItems as $rowIndex => $evalItem): ?>
                        <?php
                        $fieldName = $evalItem['field'];
                        $storedVal = (string) ($storedAnswers[$fieldName] ?? '');
                        ?>
                        <div class="bg-white rounded-xl border border-slate-200 p-3 mb-2.5 last:mb-0 shadow-sm">
                            <p class="text-sm text-slate-800 mb-2"><?= e((string) $evalItem['text']) ?></p>
                            <?php foreach ($likertLabels as $optionValue => $label): ?>
                                <?php $isChecked = (string) $optionValue === $storedVal; ?>
                                <label class="flex items-center gap-3 p-2.5 rounded-lg border mb-1.5 last:mb-0 text-sm cursor-pointer <?= $isChecked ? 'border-blue-500 bg-blue-50 text-slate-900' : 'border-slate-200 text-slate-700' ?>">
                                    <input
                                        type="radio"
                                        name="<?= e($fieldName) ?>"
                                        value="<?= $optionValue ?>"
                                        required
                                        class="h-4 w-4 cursor-pointer"
                                        aria-label="<?= e('Task evaluation ' . ($rowIndex + 1) . ', ' . $optionValue . ' - ' . $label) ?>"
                                        <?= $isChecked ? 'checked' : '' ?>
                                    >
                                    <span><?= e($optionValue . ' - ' . $label) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="bg-white shadow rounded-xl p-5">
                <p id="postsurvey-step-error" class="mb-3 text-sm text-red-600 hidden">Please complete all required fields.</p>
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                    <a href="postsurvey.php?step=crt" class="inline-block w-full sm:w-auto text-center bg-slate-100 hover:bg-slate-200 text-slate-800 font-medium px-5 py-3 rounded-lg transition">
                        Back
                    </a>
                    <button
                        type="submit"
                        class="w-full sm:w-auto accent-bg accent-bg-hover text-white font-medium px-5 py-3 rounded-lg transition"
                    >
                        Next →
                    </button>
                </div>
                <p id="postsurvey-autosave-status" class="mt-2 text-xs text-slate-500" aria-live="polite">All changes saved</p>
            </section>
        </form>
    <?php else: ?>
        <form id="postsurvey-demographics-form" method="post" action="save_postsurvey.php" class="space-y-4">
            <section class="bg-white shadow rounded-xl p-5">
                <h2 class="text-lg font-semibold text-slate-800 mb-3">Demographics</h2>

                <div class="space-y-4">
                    <div>
                        <label for="age" class="block text-base font-medium text-slate-800 mb-2">Age</label>
                        <input
                            id="age"
                            type="number"
                            min="16"
                            max="100"
                            name="age"
                            required
                            value="<?= e($prefillAge) ?>"
                            class="w-full max-w-xs rounded-lg border border-slate-300 px-3 py-2"
                        >
                        <p id="age-error" class="mt-2 text-sm text-red-600 hidden">
                            Age must be between 16 and 100.
                        </p>
                    </div>

                    <fieldset>
                        <legend class="text-base font-medium text-slate-800 mb-2">Gender</legend>
                        <div class="space-y-2 text-slate-700">
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" name="gender" value="male" required class="h-4 w-4" <?= $prefillGender === 'male' ? 'checked' : '' ?>>
                                <span>Male</span>
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" name="gender" value="female" required class="h-4 w-4" <?= $prefillGender === 'female' ? 'checked' : '' ?>>
                                <span>Female</span>
                            </label>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend class="text-base font-medium text-slate-800 mb-2">What is the highest level of education you have completed?</legend>
                        <div class="space-y-2 text-slate-700">
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" name="education" value="secondary_education" required class="h-4 w-4" <?= $prefillEducation === 'secondary_education' ? 'checked' : '' ?>>
                                <span>Secondary education (e.g., high school or equivalent)</span>
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" name="education" value="currently_enrolled_bachelors" required class="h-4 w-4" <?= $prefillEducation === 'currently_enrolled_bachelors' ? 'checked' : '' ?>>
                                <span>Currently enrolled in a Bachelor's program</span>
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" name="education" value="bachelors" required class="h-4 w-4" <?= $prefillEducation === 'bachelors' ? 'checked' : '' ?>>
                                <span>Bachelor's degree</span>
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" name="education" value="masters" required class="h-4 w-4" <?= $prefillEducation === 'masters' ? 'checked' : '' ?>>
                                <span>Master's degree</span>
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" name="education" value="doctoral_degree" required class="h-4 w-4" <?= $prefillEducation === 'doctoral_degree' ? 'checked' : '' ?>>
                                <span>Doctoral degree (PhD or equivalent)</span>
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" name="education" value="prefer_not_to_say" required class="h-4 w-4" <?= $prefillEducation === 'prefer_not_to_say' ? 'checked' : '' ?>>
                                <span>Prefer not to say</span>
                            </label>
                        </div>
                    </fieldset>
                </div>
            </section>

            <section class="bg-white shadow rounded-xl p-5">
                <p id="postsurvey-error" class="mb-4 text-sm text-red-600 hidden">
                    Please complete all required fields before submitting.
                </p>
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                    <a href="postsurvey.php?step=instruction_notice" class="inline-block w-full sm:w-auto text-center bg-slate-100 hover:bg-slate-200 text-slate-800 font-medium px-5 py-3 rounded-lg transition">
                        Back
                    </a>
                    <button
                        type="submit"
                        id="postsurvey-submit-button"
                        class="w-full sm:w-auto accent-bg accent-bg-hover text-white font-medium px-5 py-3 rounded-lg transition"
                    >
                        Submit Post-Survey
                    </button>
                </div>
                <p id="postsurvey-autosave-status" class="mt-2 text-xs text-slate-500" aria-live="polite">All changes saved</p>
            </section>
        </form>
    <?php endif; ?>
</main>

<script>
    (function () {
        var step = <?= json_encode($step, JSON_UNESCAPED_SLASHES) ?>;
        var participantId = <?= (int) session_get('participant_id', 0) ?>;
        var stepForm = document.getElementById('postsurvey-step-form');
        var form = document.getElementById('postsurvey-demographics-form');
        var ageInput = document.getElementById('age');
        var ageError = document.getElementById('age-error');
        var submitButton = document.getElementById('postsurvey-submit-button');
        var postsurveyError = document.getElementById('postsurvey-error');
        var stepError = document.getElementById('postsurvey-step-error');
        var autosaveStatus = document.getElementById('postsurvey-autosave-status');
        var autosaveStatusTimer = null;
        var activeForm = form || stepForm;

        if (!activeForm) {
            return;
        }

        var draftPrefix = 'thesis_postsurvey_draft_' + String(participantId) + '_';
        var draftKey = draftPrefix + step;
        var allDraftKeys = [
            draftPrefix + 'ai',
            draftPrefix + 'crt',
            draftPrefix + 'instruction_notice',
            draftPrefix + 'demographics'
        ];

        function setAutosaveStatus(message) {
            if (!autosaveStatus) {
                return;
            }
            autosaveStatus.textContent = message;
        }

        function saveDraft() {
            var draft = {};
            var fields = activeForm.querySelectorAll('input, textarea, select');
            fields.forEach(function (field) {
                if (!field.name || field.type === 'hidden' || field.type === 'submit' || field.type === 'button') {
                    return;
                }
                if (field.type === 'radio') {
                    if (field.checked) {
                        draft[field.name] = field.value;
                    }
                    return;
                }
                if (field.type === 'checkbox') {
                    draft[field.name] = field.checked ? '1' : '0';
                    return;
                }
                draft[field.name] = field.value;
            });

            try {
                localStorage.setItem(draftKey, JSON.stringify(draft));
                setAutosaveStatus('Draft saved');
                if (autosaveStatusTimer) {
                    clearTimeout(autosaveStatusTimer);
                }
                autosaveStatusTimer = setTimeout(function () {
                    setAutosaveStatus('All changes saved');
                }, 800);
            } catch (error) {
                // Ignore autosave failures silently.
            }
        }

        function restoreDraft() {
            var raw = null;
            try {
                raw = localStorage.getItem(draftKey);
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

            Object.keys(draft).forEach(function (name) {
                var value = String(draft[name]);
                var radios = activeForm.querySelectorAll('input[type="radio"][name="' + name + '"]');
                if (radios.length > 0) {
                    radios.forEach(function (radio) {
                        if (radio.value === value) {
                            radio.checked = true;
                        }
                    });
                    return;
                }

                var field = activeForm.querySelector('[name="' + name + '"]');
                if (!field) {
                    return;
                }

                if (field.type === 'checkbox') {
                    field.checked = value === '1';
                    return;
                }

                field.value = value;
            });
        }

        restoreDraft();
        setAutosaveStatus('All changes saved');

        var autosaveFields = activeForm.querySelectorAll('input, textarea, select');
        autosaveFields.forEach(function (field) {
            if (!field.name || field.type === 'hidden' || field.type === 'submit' || field.type === 'button') {
                return;
            }
            field.addEventListener('change', saveDraft);
            if (field.tagName === 'TEXTAREA' || field.type === 'number' || field.type === 'text') {
                field.addEventListener('input', saveDraft);
            }
        });

        // Enforce dot-based decimal format for CRT money input (crt_1).
        var crt1Input = activeForm.querySelector('input[name="crt_1"]');
        if (crt1Input) {
            function normalizeDotDecimal(rawValue) {
                var value = String(rawValue || '').replace(/,/g, '.').replace(/\s+/g, '');
                value = value.replace(/[^0-9.]/g, '');
                var firstDot = value.indexOf('.');
                if (firstDot !== -1) {
                    value = value.slice(0, firstDot + 1) + value.slice(firstDot + 1).replace(/\./g, '');
                }
                return value;
            }

            crt1Input.addEventListener('input', function () {
                var normalized = normalizeDotDecimal(crt1Input.value);
                if (crt1Input.value !== normalized) {
                    crt1Input.value = normalized;
                }
                saveDraft();
            });

            crt1Input.addEventListener('blur', function () {
                var normalized = normalizeDotDecimal(crt1Input.value);
                if (normalized === '') {
                    crt1Input.value = '';
                    saveDraft();
                    return;
                }

                var numericValue = Number(normalized);
                if (!Number.isNaN(numericValue)) {
                    crt1Input.value = numericValue.toFixed(2);
                    saveDraft();
                }
            });

            // Normalize once on page load in case old draft used comma format.
            crt1Input.value = normalizeDotDecimal(crt1Input.value);
        }

        // Enforce whole-number-only format for CRT inputs (crt_2 and crt_3).
        function attachWholeNumberNormalizer(inputName) {
            var input = activeForm.querySelector('input[name="' + inputName + '"]');
            if (!input) {
                return;
            }

            function normalizeWholeNumber(rawValue) {
                return String(rawValue || '').replace(/\D+/g, '');
            }

            input.addEventListener('input', function () {
                var normalized = normalizeWholeNumber(input.value);
                if (input.value !== normalized) {
                    input.value = normalized;
                }
                saveDraft();
            });

            input.addEventListener('blur', function () {
                var normalized = normalizeWholeNumber(input.value);
                if (normalized === '') {
                    input.value = '';
                    saveDraft();
                    return;
                }

                input.value = String(parseInt(normalized, 10));
                saveDraft();
            });

            input.value = normalizeWholeNumber(input.value);
        }

        attachWholeNumberNormalizer('crt_2');
        attachWholeNumberNormalizer('crt_3');

        function clearDynamicFieldErrors() {
            var dynamicErrors = activeForm.querySelectorAll('[data-dynamic-field-error="true"]');
            dynamicErrors.forEach(function (el) {
                el.remove();
            });

            var marked = activeForm.querySelectorAll('[data-dynamic-invalid="true"]');
            marked.forEach(function (el) {
                el.classList.remove('border-red-500', 'border-red-300', 'rounded-md', 'p-2');
                el.removeAttribute('data-dynamic-invalid');
            });
        }

        function appendInlineError(targetEl, message) {
            var error = document.createElement('p');
            error.className = 'mt-1 text-xs text-red-600';
            error.textContent = message;
            error.setAttribute('data-dynamic-field-error', 'true');
            if (targetEl.parentNode) {
                targetEl.parentNode.insertBefore(error, targetEl.nextSibling);
            }
        }

        function validateWithInlineErrors() {
            clearDynamicFieldErrors();
            if (stepError) {
                stepError.classList.add('hidden');
            }

            var invalidFields = Array.from(activeForm.querySelectorAll(':invalid'));
            var processedRadioGroups = {};
            var firstInvalid = null;

            invalidFields.forEach(function (field) {
                if (field.type === 'radio') {
                    if (processedRadioGroups[field.name]) {
                        return;
                    }
                    processedRadioGroups[field.name] = true;
                    var fieldset = field.closest('fieldset');
                    if (fieldset) {
                        fieldset.classList.add('border', 'border-red-300', 'rounded-md', 'p-2');
                        fieldset.setAttribute('data-dynamic-invalid', 'true');
                        appendInlineError(fieldset, 'Please select one option.');
                        if (!firstInvalid) {
                            firstInvalid = field;
                        }
                    }
                    return;
                }

                field.classList.add('border-red-500');
                field.setAttribute('data-dynamic-invalid', 'true');
                appendInlineError(field, 'Please complete this field.');
                if (!firstInvalid) {
                    firstInvalid = field;
                }
            });

            if (invalidFields.length > 0 && stepError) {
                stepError.classList.remove('hidden');
            }

            return {
                isValid: invalidFields.length === 0,
                firstInvalidField: firstInvalid
            };
        }

        if (!form || !ageInput || !ageError || !postsurveyError) {
            activeForm.addEventListener('submit', function (event) {
                var validation = validateWithInlineErrors();
                if (!validation.isValid) {
                    event.preventDefault();
                    if (validation.firstInvalidField && typeof validation.firstInvalidField.scrollIntoView === 'function') {
                        validation.firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        if (typeof validation.firstInvalidField.focus === 'function') {
                            validation.firstInvalidField.focus();
                        }
                    }
                    return;
                }
                try {
                    localStorage.removeItem(draftKey);
                } catch (error) {
                    // Ignore cleanup failures silently.
                }
            });
            return;
        }

        function validateAge() {
            var value = ageInput.value.trim();
            var age = Number(value);
            var isValid = value !== '' && Number.isInteger(age) && age >= 16 && age <= 100;

            if (isValid) {
                ageError.classList.add('hidden');
                ageInput.classList.remove('border-red-500');
            } else {
                ageError.classList.remove('hidden');
                ageInput.classList.add('border-red-500');
            }

            return isValid;
        }

        ageInput.addEventListener('blur', validateAge);
        ageInput.addEventListener('input', function () {
            if (!ageError.classList.contains('hidden')) {
                validateAge();
            }
        });

        form.addEventListener('submit', function (event) {
            clearDynamicFieldErrors();
            if (!validateAge()) {
                event.preventDefault();
                ageInput.focus();
                postsurveyError.classList.remove('hidden');
                ageInput.classList.add('border-red-500');
                return;
            }
            ageInput.classList.remove('border-red-500');

            if (!form.checkValidity()) {
                event.preventDefault();
                postsurveyError.classList.remove('hidden');
                var validation = validateWithInlineErrors();
                if (validation.firstInvalidField && typeof validation.firstInvalidField.scrollIntoView === 'function') {
                    validation.firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    if (typeof validation.firstInvalidField.focus === 'function') {
                        validation.firstInvalidField.focus();
                    }
                }
                return;
            }

            postsurveyError.classList.add('hidden');

            try {
                localStorage.removeItem(draftKey);
                allDraftKeys.forEach(function (key) {
                    localStorage.removeItem(key);
                });
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

<?php require __DIR__ . '/../views/footer.php'; ?>
