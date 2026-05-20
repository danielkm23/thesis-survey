<?php
declare(strict_types=1);

$title = $pageTitle ?? 'MSc Digital Business & Innovation Research Study';
$showTestConditionSwitcher = defined('TEST_MODE_ENABLED')
    && TEST_MODE_ENABLED
    && function_exists('session_get')
    && (bool) session_get('is_test_participant', false);
$currentConditionName = $showTestConditionSwitcher
    ? (string) session_get('condition_name', '')
    : '';
$testConditionCsrfSessionKey = 'test_condition_switch_csrf';
if ($showTestConditionSwitcher && session_get($testConditionCsrfSessionKey) === null) {
    session_set($testConditionCsrfSessionKey, bin2hex(random_bytes(16)));
}
$testConditionCsrfToken = $showTestConditionSwitcher
    ? (string) session_get($testConditionCsrfSessionKey, '')
    : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <link rel="icon" type="image/png" href="/assets/vu-favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --accent: #0077b3;
            --accent-hover: #5dadd0;
            --accent-secondary: #5dadd0;
        }

        body {
            font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .accent-bg {
            background-color: var(--accent);
        }

        .accent-bg-hover:hover {
            background-color: var(--accent-hover);
        }

        .accent-text {
            color: var(--accent);
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen">
    <header class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 py-2 flex items-center gap-2">
            <img
                src="/assets/vu-logo.png"
                alt="Vrije Universiteit Amsterdam logo"
                class="h-8 w-auto"
            >
            <p class="text-sm font-semibold accent-text">MSc Digital Business &amp; Innovation Research Study</p>
        </div>
        <?php if ($showTestConditionSwitcher): ?>
            <div class="max-w-7xl mx-auto px-4 pb-3">
                <form method="post" action="/switch_test_condition.php" class="flex flex-wrap items-center gap-2 text-sm bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                    <span class="font-semibold text-amber-800">Test Mode</span>
                    <span class="text-amber-700">Current condition: <?= e($currentConditionName) ?></span>
                    <input type="hidden" name="csrf_token" value="<?= e($testConditionCsrfToken) ?>">
                    <label for="test_switch_condition" class="text-amber-800">Switch to</label>
                    <select id="test_switch_condition" name="test_condition" class="rounded border border-amber-300 px-2 py-1 text-slate-800">
                        <option value="control" <?= $currentConditionName === 'control' ? 'selected' : '' ?>>control</option>
                        <option value="passive" <?= $currentConditionName === 'passive' ? 'selected' : '' ?>>passive</option>
                        <option value="active" <?= $currentConditionName === 'active' ? 'selected' : '' ?>>active</option>
                    </select>
                    <button type="submit" class="rounded bg-amber-700 hover:bg-amber-800 text-white px-3 py-1 font-medium">
                        Restart in selected condition
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </header>
