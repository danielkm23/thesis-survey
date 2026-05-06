<?php
declare(strict_types=1);

$title = $pageTitle ?? 'MSc Digital Business & Innovation Research Study';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
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
    </header>
