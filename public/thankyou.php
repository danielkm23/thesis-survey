<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/db.php';

if (!has_valid_participant_session()) {
    redirect('index.php');
}

$participantId = (int) session_get('participant_id', 0);
$raffleStatus = (string) session_get('raffle_entry_status', '');
$raffleError = '';
$participationCodeStatus = '';
$participationCodeError = '';
$participationCodeValue = '';

try {
    $pdo = db();
    $participantCodeCheck = $pdo->query("SHOW COLUMNS FROM participants LIKE 'study_participation_code'");
    $hasParticipationCodeColumn = $participantCodeCheck !== false && $participantCodeCheck->fetch() !== false;
    if ($hasParticipationCodeColumn && $participantId > 0) {
        $existingCodeStmt = $pdo->prepare(
            'SELECT study_participation_code
             FROM participants
             WHERE id = :participant_id
             LIMIT 1'
        );
        $existingCodeStmt->execute([':participant_id' => $participantId]);
        $existingCode = $existingCodeStmt->fetchColumn();
        if (is_string($existingCode)) {
            $participationCodeValue = trim($existingCode);
        }
    }
} catch (Throwable $e) {
    // Keep page usable even if optional participation-code prefill fails.
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'raffle');
    if ($action === 'save_participation_code') {
        $participationCodeValue = trim((string) ($_POST['study_participation_code'] ?? ''));
        if ($participationCodeValue === '') {
            $participationCodeError = 'Please enter the participation code you were given.';
        } elseif (mb_strlen($participationCodeValue) > 100) {
            $participationCodeError = 'Participation code is too long (max 100 characters).';
        } else {
            try {
                $pdo = db();
                $participantCodeCheck = $pdo->query("SHOW COLUMNS FROM participants LIKE 'study_participation_code'");
                $hasParticipationCodeColumn = $participantCodeCheck !== false && $participantCodeCheck->fetch() !== false;
                if (!$hasParticipationCodeColumn) {
                    $pdo->exec('ALTER TABLE participants ADD COLUMN study_participation_code VARCHAR(100) NULL');
                }
                $saveCodeStmt = $pdo->prepare(
                    'UPDATE participants
                     SET study_participation_code = :study_participation_code
                     WHERE id = :participant_id'
                );
                $saveCodeStmt->execute([
                    ':study_participation_code' => $participationCodeValue,
                    ':participant_id' => $participantId,
                ]);
                $participationCodeStatus = 'saved';
            } catch (Throwable $e) {
                $participationCodeError = 'We could not save your participation code right now. Please try again.';
            }
        }
    } else {
        $raffleAction = (string) ($_POST['raffle_action'] ?? '');
        if ($raffleAction === 'skip') {
            session_set('raffle_entry_status', 'skipped');
            $raffleStatus = 'skipped';
        } elseif ($raffleAction === 'enter') {
            $email = trim((string) ($_POST['raffle_email'] ?? ''));
            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $raffleError = 'Please provide a valid email address.';
            } else {
                try {
                    $pdo = db();
                    $pdo->exec(
                        'CREATE TABLE IF NOT EXISTS raffle_entries (
                            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                            participant_id INT UNSIGNED NOT NULL UNIQUE,
                            email VARCHAR(255) NOT NULL,
                            created_at DATETIME NOT NULL,
                            updated_at DATETIME NULL,
                            INDEX idx_raffle_entries_email (email)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
                    );
                    $now = date('Y-m-d H:i:s');
                    $stmt = $pdo->prepare(
                        'INSERT INTO raffle_entries (participant_id, email, created_at, updated_at)
                         VALUES (:participant_id, :email, :created_at, NULL)
                         ON DUPLICATE KEY UPDATE email = VALUES(email), updated_at = :updated_at'
                    );
                    $stmt->execute([
                        ':participant_id' => $participantId,
                        ':email' => $email,
                        ':created_at' => $now,
                        ':updated_at' => $now,
                    ]);
                    session_set('raffle_entry_status', 'entered');
                    $raffleStatus = 'entered';
                } catch (Throwable $e) {
                    $raffleError = 'We could not save your raffle entry right now. Please try again.';
                }
            }
        }
    }
}

$pageTitle = 'Thank You';
require __DIR__ . '/../views/header.php';
?>

<main class="max-w-3xl mx-auto px-4 py-12">
    <section class="bg-white shadow rounded-xl p-8">
        <h1 class="text-2xl font-bold text-slate-800 mb-3">Thank you for completing the study.</h1>
        <p class="text-slate-600 mb-2">Your task responses and post-survey answers were recorded successfully.</p>
    </section>

    <section class="bg-white shadow rounded-xl p-8 mt-4">
        <h2 class="text-xl font-semibold text-slate-800 mb-3">Enter the raffle</h2>
        <p class="text-slate-600 mb-3">
            Thank you for completing the study. If you would like to enter the raffle for the €50 VVV Cadeaubon, please leave your email address below.
        </p>
        <p class="text-slate-600 mb-3">
            Your email address will only be used to contact the raffle winner and will be stored separately from your survey responses.
        </p>
        <p class="text-slate-600 mb-4">
            To enter the raffle, you must complete the full study and provide a valid email address. Duplicate, incomplete, or clearly invalid entries may be removed.
        </p>

        <?php if ($raffleStatus === 'entered'): ?>
            <p class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800 mb-4">
                Your raffle entry has been submitted.
            </p>
        <?php elseif ($raffleStatus === 'skipped'): ?>
            <p class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 mb-4">
                You chose to skip raffle entry.
            </p>
        <?php endif; ?>

        <?php if ($raffleError !== ''): ?>
            <p class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 mb-4">
                <?= e($raffleError) ?>
            </p>
        <?php endif; ?>

        <form method="post" action="thankyou.php" class="space-y-4">
            <input type="hidden" name="action" value="raffle">
            <div>
                <label for="raffle_email" class="block text-sm font-semibold text-slate-700 mb-1">Email address for raffle entry</label>
                <input
                    type="email"
                    id="raffle_email"
                    name="raffle_email"
                    placeholder="name@example.com"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
            </div>

            <div class="flex flex-wrap gap-2">
                <button
                    type="submit"
                    name="raffle_action"
                    value="enter"
                    class="inline-block accent-bg accent-bg-hover text-white font-medium px-4 py-2 rounded-lg transition"
                >
                    Enter raffle
                </button>
                <button
                    type="submit"
                    name="raffle_action"
                    value="skip"
                    class="inline-block bg-slate-200 hover:bg-slate-300 text-slate-800 font-medium px-4 py-2 rounded-lg transition"
                >
                    Skip raffle entry
                </button>
            </div>
        </form>
    </section>

    <section class="bg-white shadow rounded-xl p-8 mt-4">
        <h2 class="text-xl font-semibold text-slate-800 mb-3">Study participation code</h2>
        <p class="text-slate-600 mb-4">
            If you were given a study participation code, enter it here.
        </p>

        <?php if ($participationCodeStatus === 'saved'): ?>
            <p class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800 mb-4">
                Participation code saved.
            </p>
        <?php endif; ?>

        <?php if ($participationCodeError !== ''): ?>
            <p class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 mb-4">
                <?= e($participationCodeError) ?>
            </p>
        <?php endif; ?>

        <form method="post" action="thankyou.php" class="space-y-4">
            <input type="hidden" name="action" value="save_participation_code">
            <div>
                <label for="study_participation_code" class="block text-sm font-semibold text-slate-700 mb-1">Participation code</label>
                <input
                    type="text"
                    id="study_participation_code"
                    name="study_participation_code"
                    maxlength="100"
                    value="<?= e($participationCodeValue) ?>"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
            </div>
            <button
                type="submit"
                class="inline-block accent-bg accent-bg-hover text-white font-medium px-4 py-2 rounded-lg transition"
            >
                Save code
            </button>
        </form>

        <p class="text-slate-500 text-sm mt-4">You may now close this window.</p>
    </section>
</main>

<?php require __DIR__ . '/../views/footer.php'; ?>
