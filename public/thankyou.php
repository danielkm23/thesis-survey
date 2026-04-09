<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/db.php';

if (!has_valid_participant_session()) {
    redirect('index.php');
}

$pageTitle = 'Thank You';
require __DIR__ . '/../views/header.php';
?>

<main class="max-w-3xl mx-auto px-4 py-12">
    <section class="bg-white shadow rounded-xl p-8">
        <h1 class="text-2xl font-bold text-slate-800 mb-3">Thank you for completing the study.</h1>
        <p class="text-slate-600 mb-2">Your task responses and post-survey answers were recorded successfully.</p>
        <p class="text-slate-600 mb-2">If you were given a completion code by your researcher, you can now report it.</p>
        <p class="text-slate-600">You may now close this window.</p>
    </section>
</main>

<?php require __DIR__ . '/../views/footer.php'; ?>
