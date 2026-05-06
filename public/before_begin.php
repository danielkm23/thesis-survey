<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';

if (!has_valid_participant_session()) {
    redirect('index.php');
}

$enabledTaskNumbers = [1, 2];

$pageTitle = 'Before You Begin';
require __DIR__ . '/../views/header.php';
?>

<main class="max-w-3xl mx-auto px-4 py-12">
    <section class="bg-white shadow rounded-xl p-8">
        <h1 class="text-2xl font-bold text-slate-800 mb-4">Before you begin</h1>

        <p class="text-slate-700 mb-4">You will complete two workplace tasks.</p>

        <p class="text-slate-700 mb-2">For each task, you will first see:</p>
        <ol class="list-decimal pl-6 text-slate-700 mb-6 space-y-1">
            <li>your role;</li>
            <li>a message from a colleague;</li>
            <li>an AI-generated draft response;</li>
            <li>available company documents.</li>
        </ol>

        <p class="text-slate-700 mb-4">
            You will then choose the response you would send to the colleague, indicate how confident you are in your choice, and briefly explain your reasoning.
        </p>

        <p class="text-slate-700 mb-4">Please answer as you would in a realistic workplace situation.</p>

        <p class="text-slate-700 mb-4">Click &ldquo;Start Task 1&rdquo; when you are ready.</p>

        <a
            href="task.php?task=1&view=review"
            class="inline-block accent-bg accent-bg-hover text-white font-medium px-5 py-3 rounded-lg transition"
        >
            Start Task 1
        </a>
    </section>
</main>

<?php require __DIR__ . '/../views/footer.php'; ?>
