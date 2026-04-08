<?php
declare(strict_types=1);

$title = $pageTitle ?? 'Thesis Experiment';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen">
    <header class="bg-white border-b border-slate-200">
        <div class="max-w-5xl mx-auto px-4 py-4">
            <p class="font-semibold">Thesis Prototype</p>
        </div>
    </header>
