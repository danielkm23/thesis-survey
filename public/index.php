<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/db.php';

$requestPath = rtrim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
if ($requestPath === '') {
    $requestPath = '/';
}
$isTestPath = preg_match('#(?:^|/)test$#', $requestPath) === 1;
$showTestEntry = TEST_MODE_ENABLED && $isTestPath;

if ($showTestEntry) {
    clear_participant_session_state();
} elseif (has_valid_participant_session()) {
    redirect('intro.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = db();
    $startMode = (string) ($_POST['start_mode'] ?? 'live');
    $isTestSession = $startMode === 'test';
    $conditionName = '';
    if ($isTestSession) {
        if (!$showTestEntry) {
            http_response_code(403);
            exit('Invalid test mode entry.');
        }
        $requestedCondition = trim((string) ($_POST['test_condition'] ?? ''));
        $allowedConditions = ['control', 'passive', 'active'];
        if (!in_array($requestedCondition, $allowedConditions, true)) {
            http_response_code(400);
            exit('Invalid test condition.');
        }
        $conditionName = $requestedCondition;
    }
    $startedAt = date('Y-m-d H:i:s');

    $participantId = null;
    $participantCode = '';
    $assignmentLockName = 'thesis_survey_condition_assignment_lock';
    $assignmentLockAcquired = false;

    try {
        if (!$isTestSession) {
            // Serialize condition assignment to reduce tie-clustering on concurrent starts.
            $lockStmt = $pdo->prepare('SELECT GET_LOCK(:lock_name, 5)');
            $lockStmt->execute([':lock_name' => $assignmentLockName]);
            $assignmentLockAcquired = ((int) $lockStmt->fetchColumn()) === 1;
            $conditionName = choose_balanced_condition($pdo, true);
        }

        // Retry a few times in case of unique code collision.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $participantCode = generate_participant_code();
            if ($isTestSession) {
                $participantCode = generate_test_participant_code($pdo);
            }

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
    } finally {
        if ($assignmentLockAcquired) {
            try {
                $unlockStmt = $pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
                $unlockStmt->execute([':lock_name' => $assignmentLockName]);
            } catch (Throwable $e) {
                // Ignore unlock failures; lock is connection-scoped and released on disconnect.
            }
        }
    }

    if ($participantId === null) {
        http_response_code(500);
        exit('Could not create participant. Please try again.');
    }

    clear_participant_session_state();
    session_set('participant_id', $participantId);
    session_set('participant_code', $participantCode);
    session_set('condition_name', $conditionName);
    session_set('is_test_participant', $isTestSession);

    redirect('intro.php');
}

$pageTitle = 'Thesis Experiment';
require __DIR__ . '/../views/header.php';
?>

<main class="max-w-3xl mx-auto px-4 py-12">
    <section class="bg-white shadow rounded-xl p-8">
        <h1 class="text-2xl font-bold text-slate-800 mb-6">Welcome to the thesis study</h1>
        <form method="post" action="">
            <input type="hidden" name="start_mode" value="live">
            <button type="submit" class="inline-block accent-bg accent-bg-hover text-white font-medium px-5 py-3 rounded-lg transition">
                Start Study
            </button>
        </form>
        <?php if ($showTestEntry): ?>
            <hr class="my-6 border-slate-200">
            <h2 class="text-lg font-semibold text-slate-800 mb-3">Test Session</h2>
            <p class="text-sm text-slate-600 mb-4">Starts a test participant that is excluded from analysis by default.</p>
            <form method="post" action="" class="space-y-3">
                <input type="hidden" name="start_mode" value="test">
                <div>
                    <label for="test_condition" class="block text-sm font-medium text-slate-700 mb-1">Condition</label>
                    <select id="test_condition" name="test_condition" class="rounded-lg border border-slate-300 px-3 py-2">
                        <option value="control">control</option>
                        <option value="passive">passive</option>
                        <option value="active">active</option>
                    </select>
                </div>
                <button type="submit" class="inline-block bg-slate-700 hover:bg-slate-800 text-white font-medium px-4 py-2 rounded-lg transition">
                    Start Test Session
                </button>
            </form>
        <?php endif; ?>
    </section>
</main>

<?php require __DIR__ . '/../views/footer.php'; ?>
