<?php
declare(strict_types=1);

/**
 * Escapes output for safe HTML rendering.
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Stores a value in the participant session state.
 */
function session_set(string $key, mixed $value): void
{
    $_SESSION[$key] = $value;
}

/**
 * Reads a value from participant session state.
 */
function session_get(string $key, mixed $default = null): mixed
{
    return $_SESSION[$key] ?? $default;
}

/**
 * Generates a readable participant code.
 */
function generate_participant_code(): string
{
    return 'P-' . strtoupper(bin2hex(random_bytes(4)));
}

/**
 * Generates a sequential test participant code (for example TEST-0001).
 */
function generate_test_participant_code(PDO $pdo): string
{
    $prefix = defined('TEST_PARTICIPANT_PREFIX') ? (string) TEST_PARTICIPANT_PREFIX : 'TEST-';
    $startPos = max(1, strlen($prefix) + 1);
    $prefixLike = $prefix . '%';
    $prefixRegex = '^' . preg_quote($prefix, '/') . '[0-9]+$';

    $stmt = $pdo->prepare(
        'SELECT MAX(CAST(SUBSTRING(participant_code, :start_pos) AS UNSIGNED)) AS max_suffix
         FROM participants
         WHERE participant_code LIKE :prefix_like
           AND participant_code REGEXP :prefix_regex'
    );
    $stmt->bindValue(':start_pos', $startPos, PDO::PARAM_INT);
    $stmt->bindValue(':prefix_like', $prefixLike, PDO::PARAM_STR);
    $stmt->bindValue(':prefix_regex', $prefixRegex, PDO::PARAM_STR);
    $stmt->execute();

    $maxSuffix = (int) ($stmt->fetchColumn() ?? 0);
    $nextSuffix = $maxSuffix + 1;

    return $prefix . str_pad((string) $nextSuffix, 4, '0', STR_PAD_LEFT);
}

/**
 * Chooses a condition with balancing toward equal completed counts.
 *
 * Primary criterion: fewest fully completed participants per condition.
 * Tie-breaker: fewest started participants per condition.
 * Final tie: random among remaining tied conditions.
 */
function choose_balanced_condition(PDO $pdo, bool $excludeTestParticipants = true): string
{
    $conditions = ['control', 'passive', 'active'];
    $startedByCondition = array_fill_keys($conditions, 0);
    $completedByCondition = array_fill_keys($conditions, 0);

    $sql = 'SELECT
                condition_name,
                COUNT(*) AS started_count,
                SUM(CASE WHEN completed_at IS NOT NULL THEN 1 ELSE 0 END) AS completed_count
            FROM participants
            WHERE condition_name IN (\'control\', \'passive\', \'active\')';

    if ($excludeTestParticipants) {
        $sql .= ' AND participant_code NOT LIKE :test_prefix';
    }

    $sql .= ' GROUP BY condition_name';

    $stmt = $pdo->prepare($sql);
    if ($excludeTestParticipants) {
        $testPrefix = defined('TEST_PARTICIPANT_PREFIX') ? (string) TEST_PARTICIPANT_PREFIX : 'TEST-';
        $stmt->bindValue(':test_prefix', $testPrefix . '%', PDO::PARAM_STR);
    }
    $stmt->execute();

    foreach ($stmt->fetchAll() as $row) {
        $condition = (string) ($row['condition_name'] ?? '');
        if (!array_key_exists($condition, $startedByCondition)) {
            continue;
        }
        $startedByCondition[$condition] = (int) ($row['started_count'] ?? 0);
        $completedByCondition[$condition] = (int) ($row['completed_count'] ?? 0);
    }

    $minCompleted = min($completedByCondition);
    $leastCompleted = array_keys(array_filter(
        $completedByCondition,
        static fn (int $count): bool => $count === $minCompleted
    ));

    if (count($leastCompleted) === 1) {
        return $leastCompleted[0];
    }

    $candidateStarted = [];
    foreach ($leastCompleted as $condition) {
        $candidateStarted[$condition] = $startedByCondition[$condition] ?? PHP_INT_MAX;
    }

    $minStarted = min($candidateStarted);
    $leastStarted = array_keys(array_filter(
        $candidateStarted,
        static fn (int $count): bool => $count === $minStarted
    ));

    return $leastStarted[array_rand($leastStarted)];
}

/**
 * Redirects and stops script execution.
 */
function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

/**
 * Checks if required participant fields exist in session.
 */
function has_valid_participant_session(): bool
{
    return session_get('participant_id') !== null
        && session_get('participant_code') !== null
        && session_get('condition_name') !== null;
}

/**
 * Clears participant/task session state while keeping unrelated session data.
 */
function clear_participant_session_state(): void
{
    $keysToClear = [
        'participant_id',
        'participant_code',
        'condition_name',
        'is_test_participant',
        'postsurvey_answers',
        'postsurvey_started_at',
        'doc_order',
        'response_option_order',
        'raffle_entry_status',
    ];

    foreach ($keysToClear as $key) {
        unset($_SESSION[$key]);
    }

    foreach (array_keys($_SESSION) as $key) {
        if (str_starts_with((string) $key, 'task_')) {
            unset($_SESSION[$key]);
        }
    }
}
