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
 * Excludes test participants and low-quality responses from load counts.
 * Primary criterion: fewest effective assignments per condition
 * (completed + weighted unfinished recent starts).
 * Tie-breaker: fewest fully completed participants per condition.
 * Secondary tie-breaker: fewest unfinished recent starts per condition.
 * Final tie: random among remaining tied conditions.
 */
function choose_balanced_condition(PDO $pdo, bool $excludeTestParticipants = true): string
{
    if (!function_exists('analysis_low_quality_participant_ids')) {
        require_once __DIR__ . '/analysis.php';
    }

    $conditions = ['control', 'passive', 'active'];
    $recentActiveStartsByCondition = array_fill_keys($conditions, 0);
    $completedByCondition = array_fill_keys($conditions, 0);
    $activeStartWindowMinutes = defined('RANDOMIZER_ACTIVE_START_WINDOW_MINUTES')
        ? max(1, (int) RANDOMIZER_ACTIVE_START_WINDOW_MINUTES)
        : 10;
    $activeStartWeight = defined('RANDOMIZER_ACTIVE_START_WEIGHT')
        ? min(1.0, max(0.0, (float) RANDOMIZER_ACTIVE_START_WEIGHT))
        : 0.6;
    $activeConditionWeightScale = defined('RANDOMIZER_ACTIVE_CONDITION_WEIGHT_SCALE')
        ? min(1.0, max(0.0, (float) RANDOMIZER_ACTIVE_CONDITION_WEIGHT_SCALE))
        : 0.85;
    $activeCompletionLead = defined('RANDOMIZER_ACTIVE_COMPLETION_LEAD')
        ? max(0, (int) RANDOMIZER_ACTIVE_COMPLETION_LEAD)
        : 1;
    $recentStartedAfter = date('Y-m-d H:i:s', time() - ($activeStartWindowMinutes * 60));
    $lowQualityParticipantIds = analysis_low_quality_participant_ids($pdo, $excludeTestParticipants);

    $sql = 'SELECT
                condition_name,
                SUM(CASE WHEN completed_at IS NULL AND started_at >= :recent_started_after THEN 1 ELSE 0 END) AS recent_active_starts,
                SUM(CASE WHEN completed_at IS NOT NULL THEN 1 ELSE 0 END) AS completed_count
            FROM participants
            WHERE condition_name IN (\'control\', \'passive\', \'active\')';

    if ($excludeTestParticipants) {
        $sql .= ' AND participant_code NOT LIKE :test_prefix';
    }

    $lowQualityPlaceholders = [];
    foreach (array_values($lowQualityParticipantIds) as $index => $participantId) {
        $placeholder = ':low_quality_participant_id_' . $index;
        $lowQualityPlaceholders[] = $placeholder;
    }
    if ($lowQualityPlaceholders !== []) {
        $sql .= ' AND id NOT IN (' . implode(', ', $lowQualityPlaceholders) . ')';
    }

    $sql .= ' GROUP BY condition_name';

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':recent_started_after', $recentStartedAfter, PDO::PARAM_STR);
    if ($excludeTestParticipants) {
        $testPrefix = defined('TEST_PARTICIPANT_PREFIX') ? (string) TEST_PARTICIPANT_PREFIX : 'TEST-';
        $stmt->bindValue(':test_prefix', $testPrefix . '%', PDO::PARAM_STR);
    }
    foreach (array_values($lowQualityParticipantIds) as $index => $participantId) {
        $stmt->bindValue(':low_quality_participant_id_' . $index, $participantId, PDO::PARAM_INT);
    }
    $stmt->execute();

    foreach ($stmt->fetchAll() as $row) {
        $condition = (string) ($row['condition_name'] ?? '');
        if (!array_key_exists($condition, $recentActiveStartsByCondition)) {
            continue;
        }
        $recentActiveStartsByCondition[$condition] = (int) ($row['recent_active_starts'] ?? 0);
        $completedByCondition[$condition] = (int) ($row['completed_count'] ?? 0);
    }

    $effectiveLoadByCondition = [];
    foreach ($conditions as $condition) {
        $completedCount = (int) ($completedByCondition[$condition] ?? 0);
        $adjustedCompletedCount = $condition === 'active'
            ? max(0, $completedCount - $activeCompletionLead)
            : $completedCount;
        $recentActiveStarts = (int) ($recentActiveStartsByCondition[$condition] ?? 0);
        $conditionStartWeight = $condition === 'active'
            ? ($activeStartWeight * $activeConditionWeightScale)
            : $activeStartWeight;
        $effectiveLoadByCondition[$condition] = $adjustedCompletedCount + ($recentActiveStarts * $conditionStartWeight);
    }

    $minEffectiveLoad = min($effectiveLoadByCondition);
    $leastLoaded = array_keys(array_filter(
        $effectiveLoadByCondition,
        static fn (float $count): bool => abs($count - $minEffectiveLoad) < 0.000001
    ));

    if (count($leastLoaded) === 1) {
        return $leastLoaded[0];
    }

    $candidateCompleted = [];
    foreach ($leastLoaded as $condition) {
        $rawCompleted = (int) ($completedByCondition[$condition] ?? PHP_INT_MAX);
        $candidateCompleted[$condition] = $condition === 'active'
            ? max(0, $rawCompleted - $activeCompletionLead)
            : $rawCompleted;
    }

    $minCompleted = min($candidateCompleted);
    $leastCompleted = array_keys(array_filter(
        $candidateCompleted,
        static fn (int $count): bool => $count === $minCompleted
    ));

    if (count($leastCompleted) === 1) {
        return $leastCompleted[0];
    }

    $candidateStarted = [];
    foreach ($leastCompleted as $condition) {
        $candidateStarted[$condition] = $recentActiveStartsByCondition[$condition] ?? PHP_INT_MAX;
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
    $location = trim($path);
    if ($location === '') {
        $location = '/';
    } elseif (!preg_match('#^(?:https?:)?//#i', $location) && !str_starts_with($location, '/')) {
        $location = '/' . $location;
    }

    header('Location: ' . $location);
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
