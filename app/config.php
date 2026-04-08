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
