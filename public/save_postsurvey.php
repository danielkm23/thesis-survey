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

function required_scale_value(string $key): int
{
    $value = filter_input(INPUT_POST, $key, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 7],
    ]);

    if ($value === false || $value === null) {
        http_response_code(400);
        exit('Invalid value for ' . $key . '.');
    }

    return $value;
}

function required_numeric_response(string $key): string
{
    $raw = trim((string) ($_POST[$key] ?? ''));
    if ($raw === '' || !is_numeric($raw)) {
        http_response_code(400);
        exit('Invalid value for ' . $key . '.');
    }

    return $raw;
}

$aiLit1 = required_scale_value('ai_lit_1');
$aiLit2 = required_scale_value('ai_lit_2');
$aiLit3 = required_scale_value('ai_lit_3');
$aiLit4 = required_scale_value('ai_lit_4');
$aiLit5 = required_scale_value('ai_lit_5');

$crt1 = required_numeric_response('crt_1');
$crt2 = required_numeric_response('crt_2');
$crt3 = required_numeric_response('crt_3');

$allowedAiExperience = ['never', 'occasionally', 'regularly', 'daily'];
$allowedGender = ['male', 'female', 'non_binary', 'prefer_not_to_say'];
$allowedEducation = ['high_school', 'bachelors', 'masters', 'phd', 'other'];

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

$insert = $pdo->prepare(
    'INSERT INTO postsurvey_responses
        (participant_id, ai_lit_1, ai_lit_2, ai_lit_3, ai_lit_4, ai_lit_5, crt_1, crt_2, crt_3, ai_experience, age, gender, education, submitted_at, duration_seconds, short_time_flag)
     VALUES
        (:participant_id, :ai_lit_1, :ai_lit_2, :ai_lit_3, :ai_lit_4, :ai_lit_5, :crt_1, :crt_2, :crt_3, :ai_experience, :age, :gender, :education, :submitted_at, :duration_seconds, :short_time_flag)'
);

$insert->execute([
    ':participant_id' => $participantId,
    ':ai_lit_1' => $aiLit1,
    ':ai_lit_2' => $aiLit2,
    ':ai_lit_3' => $aiLit3,
    ':ai_lit_4' => $aiLit4,
    ':ai_lit_5' => $aiLit5,
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

$updateParticipant = $pdo->prepare(
    'UPDATE participants
     SET completed_at = :completed_at
     WHERE id = :participant_id'
);

$updateParticipant->execute([
    ':completed_at' => $submittedAt,
    ':participant_id' => $participantId,
]);

redirect('thankyou.php');
