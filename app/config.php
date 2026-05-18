<?php
declare(strict_types=1);

/**
 * Reads env var with a fallback value.
 */
function env_or_default(string $key, string $default): string
{
    $value = getenv($key);
    return $value === false || $value === '' ? $default : $value;
}

// Render/local configuration (prefer environment variables in deployment).
define('DB_HOST', env_or_default('DB_HOST', '127.0.0.1'));
define('DB_PORT', env_or_default('DB_PORT', '3306'));
define('DB_NAME', env_or_default('DB_NAME', 'thesis_survey'));
define('DB_USER', env_or_default('DB_USER', 'root'));
define('DB_PASS', env_or_default('DB_PASS', 'root1234'));

// Test-mode controls (enabled by default; can be disabled with TEST_MODE_ENABLED=0).
define('TEST_MODE_ENABLED', env_or_default('TEST_MODE_ENABLED', '1') === '1');
define('TEST_MODE_KEY', env_or_default('TEST_MODE_KEY', 'TEST-MODE'));
define('TEST_PARTICIPANT_PREFIX', env_or_default('TEST_PARTICIPANT_PREFIX', 'TEST-'));

// Randomizer tie-breaker window: count only unfinished starts within this many minutes.
define('RANDOMIZER_ACTIVE_START_WINDOW_MINUTES', max(1, (int) env_or_default('RANDOMIZER_ACTIVE_START_WINDOW_MINUTES', '30')));
// Randomizer started-session weight (0.0-1.0): lower means a softer margin for likely non-completion.
define('RANDOMIZER_ACTIVE_START_WEIGHT', min(1.0, max(0.0, (float) env_or_default('RANDOMIZER_ACTIVE_START_WEIGHT', '0.6'))));
