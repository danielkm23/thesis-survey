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

function validate_int_in_range(mixed $value, int $min, int $max): ?int
{
    if (is_int($value)) {
        return $value >= $min && $value <= $max ? $value : null;
    }

    if (!is_string($value)) {
        return null;
    }

    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $validated = filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => $min, 'max_range' => $max],
    ]);

    return $validated === false ? null : $validated;
}

function required_mcq_value(string $key): int
{
    $sessionAnswers = session_get('postsurvey_answers', []);
    $rawValue = $_POST[$key] ?? (is_array($sessionAnswers) ? ($sessionAnswers[$key] ?? null) : null);
    $value = validate_int_in_range($rawValue, 1, 5);
    if ($value === false || $value === null) {
        http_response_code(400);
        exit('Invalid value for ' . $key . '.');
    }

    return $value;
}

function required_numeric_response(string $key): string
{
    $sessionAnswers = session_get('postsurvey_answers', []);
    $raw = trim((string) ($_POST[$key] ?? (is_array($sessionAnswers) ? ($sessionAnswers[$key] ?? '') : '')));
    if ($raw === '' || !is_numeric($raw)) {
        http_response_code(400);
        exit('Invalid value for ' . $key . '.');
    }

    return $raw;
}

function required_integer_response(string $key): string
{
    $sessionAnswers = session_get('postsurvey_answers', []);
    $raw = trim((string) ($_POST[$key] ?? (is_array($sessionAnswers) ? ($sessionAnswers[$key] ?? '') : '')));
    if ($raw === '' || filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false) {
        http_response_code(400);
        exit('Invalid value for ' . $key . '. Please provide a whole number.');
    }

    return $raw;
}

$aiLit1 = required_mcq_value('ai_lit_1');
$aiLit2 = required_mcq_value('ai_lit_2');
$aiLit3 = required_mcq_value('ai_lit_3');
$aiLit4 = required_mcq_value('ai_lit_4');
$aiLit5 = required_mcq_value('ai_lit_5');
$aiLit6 = required_mcq_value('ai_lit_6');
$instructionNotice = required_mcq_value('instruction_notice');
$taskRealism = required_mcq_value('task_realism');

$crt1 = required_numeric_response('crt_1');
$crt2 = required_integer_response('crt_2');
$crt3 = required_integer_response('crt_3');

$allowedAiExperience = ['never', 'less_than_monthly', 'few_times_per_month', 'few_times_per_week', 'daily'];
$allowedGender = ['male', 'female'];
$allowedEducation = ['secondary_education', 'currently_enrolled_bachelors', 'bachelors', 'masters', 'doctoral_degree', 'prefer_not_to_say'];

$aiExperience = (string) ($_POST['ai_experience'] ?? '');
$gender = (string) ($_POST['gender'] ?? '');
$education = (string) ($_POST['education'] ?? '');
$age = filter_input(INPUT_POST, 'age', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 16, 'max_range' => 100],
]);

if (!in_array($aiExperience, $allowedAiExperience, true)) {
    http_response_code(400);
    exit('Invalid AI experience value.');
}

if ($age === false || $age === null) {
    http_response_code(400);
    exit('Invalid age value. Age must be between 16 and 100.');
}

if (!in_array($gender, $allowedGender, true)) {
    http_response_code(400);
    exit('Invalid gender value.');
}

if (!in_array($education, $allowedEducation, true)) {
    http_response_code(400);
    exit('Invalid education value.');
}

$participantId = (int) session_get('participant_id');
$submittedAt = date('Y-m-d H:i:s');
$submittedTs = time();

$postsurveyStartedAt = session_get('postsurvey_started_at');
$postsurveyDurationSeconds = null;
$postsurveyShortTimeFlag = 0;
if (is_string($postsurveyStartedAt) && $postsurveyStartedAt !== '') {
    $postsurveyStartedTs = strtotime($postsurveyStartedAt);
    if ($postsurveyStartedTs !== false) {
        $postsurveyDurationSeconds = max(0, $submittedTs - $postsurveyStartedTs);
        $postsurveyShortTimeFlag = $postsurveyDurationSeconds < 45 ? 1 : 0;
    }
}

$pdo = db();
$hasAiLit6Column = false;
$hasInstructionNoticeColumn = false;
$hasTaskRealismColumn = false;
try {
    $aiLit6Check = $pdo->query("SHOW COLUMNS FROM postsurvey_responses LIKE 'ai_lit_6'");
    $hasAiLit6Column = $aiLit6Check !== false && $aiLit6Check->fetch() !== false;
    $instructionNoticeCheck = $pdo->query("SHOW COLUMNS FROM postsurvey_responses LIKE 'instruction_notice'");
    $hasInstructionNoticeColumn = $instructionNoticeCheck !== false && $instructionNoticeCheck->fetch() !== false;
    $taskRealismCheck = $pdo->query("SHOW COLUMNS FROM postsurvey_responses LIKE 'task_realism'");
    $hasTaskRealismColumn = $taskRealismCheck !== false && $taskRealismCheck->fetch() !== false;
} catch (Throwable $e) {
    $hasAiLit6Column = false;
    $hasInstructionNoticeColumn = false;
    $hasTaskRealismColumn = false;
}

$columns = [
    'participant_id',
    'ai_lit_1',
    'ai_lit_2',
    'ai_lit_3',
    'ai_lit_4',
    'ai_lit_5',
];

$params = [
    ':participant_id' => $participantId,
    ':ai_lit_1' => $aiLit1,
    ':ai_lit_2' => $aiLit2,
    ':ai_lit_3' => $aiLit3,
    ':ai_lit_4' => $aiLit4,
    ':ai_lit_5' => $aiLit5,
];

if ($hasAiLit6Column) {
    $columns[] = 'ai_lit_6';
    $params[':ai_lit_6'] = $aiLit6;
}

if ($hasInstructionNoticeColumn) {
    $columns[] = 'instruction_notice';
    $params[':instruction_notice'] = $instructionNotice;
}

if ($hasTaskRealismColumn) {
    $columns[] = 'task_realism';
    $params[':task_realism'] = $taskRealism;
}

$columns = array_merge($columns, [
    'crt_1',
    'crt_2',
    'crt_3',
    'ai_experience',
    'age',
    'gender',
    'education',
    'submitted_at',
    'duration_seconds',
    'short_time_flag',
]);

$params = array_merge($params, [
    ':crt_1' => $crt1,
    ':crt_2' => $crt2,
    ':crt_3' => $crt3,
    ':ai_experience' => $aiExperience,
    ':age' => $age,
    ':gender' => $gender,
    ':education' => $education,
    ':submitted_at' => $submittedAt,
    ':duration_seconds' => $postsurveyDurationSeconds,
    ':short_time_flag' => $postsurveyShortTimeFlag,
]);

$placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
$insertSql = sprintf(
    'INSERT INTO postsurvey_responses (%s) VALUES (%s)',
    implode(', ', $columns),
    implode(', ', $placeholders)
);

$insert = $pdo->prepare($insertSql);
$insert->execute($params);

$updateParticipant = $pdo->prepare(
    'UPDATE participants
     SET completed_at = :completed_at
     WHERE id = :participant_id'
);

$updateParticipant->execute([
    ':completed_at' => $submittedAt,
    ':participant_id' => $participantId,
]);

unset($_SESSION['postsurvey_answers'], $_SESSION['postsurvey_started_at']);

redirect('thankyou.php');
