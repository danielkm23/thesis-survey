<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/db.php';

if (has_valid_participant_session()) {
    redirect('intro.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = db();
    $conditionName = choose_random_condition();
    $startedAt = date('Y-m-d H:i:s');

    $participantId = null;
    $participantCode = '';

    // Retry a few times in case of unique code collision.
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $participantCode = generate_participant_code();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO participants (participant_code, condition_name, started_at, completed_at)
                 VALUES (:participant_code, :condition_name, :started_at, NULL)'
            );
            $stmt->execute([
                ':participant_code' => $participantCode,
                ':condition_name' => $conditionName,
                ':started_at' => $startedAt,
            ]);

            $participantId = (int) $pdo->lastInsertId();
            break;
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
        }
    }

    if ($participantId === null) {
        http_response_code(500);
        exit('Could not create participant. Please try again.');
    }

    session_set('participant_id', $participantId);
    session_set('participant_code', $participantCode);
    session_set('condition_name', $conditionName);

    redirect('intro.php');
}

$pageTitle = 'Thesis Experiment';
require __DIR__ . '/../views/header.php';
?>

<main class="max-w-3xl mx-auto px-4 py-12">
    <section class="bg-white shadow rounded-xl p-8">
        <h1 class="text-2xl font-bold text-slate-800 mb-6">Welcome to the thesis study</h1>
        <form method="post" action="">
            <button type="submit" class="inline-block accent-bg accent-bg-hover text-white font-medium px-5 py-3 rounded-lg transition">
                Start Study
            </button>
        </form>
    </section>
</main>

<?php require __DIR__ . '/../views/footer.php'; ?>
