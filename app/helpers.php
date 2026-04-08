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
 * Returns one random study condition.
 */
function choose_random_condition(): string
{
    $conditions = ['control', 'passive', 'active'];
    return $conditions[array_rand($conditions)];
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
