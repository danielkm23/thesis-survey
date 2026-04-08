<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/db.php';

if (!has_valid_participant_session()) {
    redirect('index.php');
}

$pageTitle = 'Study Introduction';
require __DIR__ . '/../views/header.php';
?>

<main class="max-w-3xl mx-auto px-4 py-12">
    <section class="bg-white shadow rounded-xl p-8">
        <p class="text-sm text-slate-500 mb-3">Step 0 of 5</p>
        <h1 class="text-2xl font-bold text-slate-800 mb-3">Study Information and Consent</h1>
        <p class="text-slate-600 mb-3">
            You will complete 4 short tasks and 1 post-survey. In each task, you will review an AI-generated response
            and optional supporting documents before providing your own final response.
        </p>
        <p class="text-slate-600 mb-3">
            Your interaction data (for example, response choices and document viewing behavior) will be recorded for
            research analysis. Do not include personally sensitive information in your answers.
        </p>
        <p class="text-slate-600 mb-6">
            By selecting "Begin Task 1", you confirm that you are at least 16 years old and consent to participate.
        </p>
        <a href="task.php?task=1" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-medium px-5 py-3 rounded-lg transition">
            Begin Task 1
        </a>
    </section>
</main>

<?php require __DIR__ . '/../views/footer.php'; ?>
