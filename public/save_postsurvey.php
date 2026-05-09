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
$seriousEffort = required_mcq_value('serious_effort');
$instructionsClarity = required_mcq_value('instructions_clarity');
$instructionNotice = required_mcq_value('instruction_notice');
$taskRealism = required_mcq_value('task_realism');

$crt1 = required_numeric_response('crt_1');
$crt2 = required_integer_response('crt_2');
$crt3 = required_integer_response('crt_3');

$allowedAiExperience = ['never', 'less_than_monthly', 'few_times_per_month', 'few_times_per_week', 'daily'];
$allowedGender = ['male', 'female', 'prefer_not_to_say'];
$allowedEducation = ['secondary_education', 'currently_enrolled_bachelors', 'bachelors', 'masters', 'doctoral_degree', 'prefer_not_to_say'];

$sessionAnswers = session_get('postsurvey_answers', []);
$aiExperience = (string) ($_POST['ai_experience'] ?? (is_array($sessionAnswers) ? ($sessionAnswers['ai_experience'] ?? '') : ''));
$gender = (string) ($_POST['gender'] ?? '');
$education = (string) ($_POST['education'] ?? '');
$age = filter_input(INPUT_POST, 'age', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 16, 'max_range' => 100],
]);

// Backwards compatibility for legacy DB values (older versions used different labels).
$aiExperience = strtolower(trim($aiExperience));
$legacyAiExperienceMap = [
    'regularly' => 'few_times_per_week',
    'occasionally' => 'few_times_per_month',
];
if (isset($legacyAiExperienceMap[$aiExperience])) {
    $aiExperience = $legacyAiExperienceMap[$aiExperience];
}

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
$participantExistsStmt = $pdo->prepare(
    'SELECT id
     FROM participants
     WHERE id = :participant_id
     LIMIT 1'
);
$participantExistsStmt->execute([
    ':participant_id' => $participantId,
]);
$participantExists = $participantExistsStmt->fetchColumn() !== false;
if ($participantId <= 0 || !$participantExists) {
    unset($_SESSION['participant_id'], $_SESSION['participant_code'], $_SESSION['condition_name'], $_SESSION['postsurvey_answers'], $_SESSION['postsurvey_started_at']);
    http_response_code(409);
    exit('Your participant session is no longer valid. Please restart the study from the beginning.');
}

$existingPostsurveyStmt = $pdo->prepare(
    'SELECT id
     FROM postsurvey_responses
     WHERE participant_id = :participant_id
     LIMIT 1'
);
$existingPostsurveyStmt->execute([
    ':participant_id' => $participantId,
]);
$alreadySubmitted = $existingPostsurveyStmt->fetchColumn() !== false;
if ($alreadySubmitted) {
    redirect('thankyou.php');
}

$requiredPostsurveyColumns = [
    'participant_id',
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
    'submitted_at',
    'duration_seconds',
    'short_time_flag',
];

try {
    $columnRows = $pdo->query('SHOW COLUMNS FROM postsurvey_responses')->fetchAll(PDO::FETCH_ASSOC);
    $existingColumns = [];
    foreach ($columnRows as $columnRow) {
        $columnName = isset($columnRow['Field']) ? (string) $columnRow['Field'] : '';
        if ($columnName !== '') {
            $existingColumns[$columnName] = true;
        }
    }
    $missingColumns = array_values(array_filter(
        $requiredPostsurveyColumns,
        static fn (string $columnName): bool => !isset($existingColumns[$columnName])
    ));
    if (!empty($missingColumns)) {
        error_log('save_postsurvey schema mismatch, missing columns: ' . implode(', ', $missingColumns));
        http_response_code(500);
        exit(
            'Database schema is outdated. Missing postsurvey columns: '
            . implode(', ', $missingColumns)
            . '. Run sql/live_align_survey_schema.sql and try again.'
        );
    }
} catch (Throwable $e) {
    error_log('save_postsurvey schema check failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Could not verify database schema for post-survey saving. Please contact the researcher.');
}

$insertSql = 'INSERT INTO postsurvey_responses (
    participant_id,
    ai_lit_1,
    ai_lit_2,
    ai_lit_3,
    ai_lit_4,
    serious_effort,
    instructions_clarity,
    instruction_notice,
    task_realism,
    crt_1,
    crt_2,
    crt_3,
    ai_experience,
    age,
    gender,
    education,
    submitted_at,
    duration_seconds,
    short_time_flag
) VALUES (
    :participant_id,
    :ai_lit_1,
    :ai_lit_2,
    :ai_lit_3,
    :ai_lit_4,
    :serious_effort,
    :instructions_clarity,
    :instruction_notice,
    :task_realism,
    :crt_1,
    :crt_2,
    :crt_3,
    :ai_experience,
    :age,
    :gender,
    :education,
    :submitted_at,
    :duration_seconds,
    :short_time_flag
)';

$params = [
    ':participant_id' => $participantId,
    ':ai_lit_1' => $aiLit1,
    ':ai_lit_2' => $aiLit2,
    ':ai_lit_3' => $aiLit3,
    ':ai_lit_4' => $aiLit4,
    ':serious_effort' => $seriousEffort,
    ':instructions_clarity' => $instructionsClarity,
    ':instruction_notice' => $instructionNotice,
    ':task_realism' => $taskRealism,
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
];

$insert = $pdo->prepare($insertSql);
try {
    $insert->execute($params);
} catch (PDOException $e) {
    $sqlState = (string) $e->getCode();
    $driverMsg = isset($e->errorInfo[2]) ? (string) $e->errorInfo[2] : '';
    $isIntegrity =
        $sqlState === '23000'
        || str_contains($driverMsg, 'cannot be null')
        || str_contains($driverMsg, "doesn't have a default value");

    if ($isIntegrity) {
        $duplicateCheckStmt = $pdo->prepare(
            'SELECT id
             FROM postsurvey_responses
             WHERE participant_id = :participant_id
             LIMIT 1'
        );
        $duplicateCheckStmt->execute([
            ':participant_id' => $participantId,
        ]);
        if ($duplicateCheckStmt->fetchColumn() !== false) {
            redirect('thankyou.php');
        }

        error_log('save_postsurvey insert failed: ' . $e->getMessage());
        http_response_code(500);
        exit('Failed to save post-survey responses due to database constraints. Please contact the researcher.');
    }
    throw $e;
}

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
