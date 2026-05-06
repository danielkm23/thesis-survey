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

$pageTitle = 'Study Introduction';
require __DIR__ . '/../views/header.php';
?>

<main class="max-w-3xl mx-auto px-4 py-12">
    <section class="bg-white shadow rounded-xl p-8">
        <h1 class="text-2xl font-bold text-slate-800 mb-3">Study Information and Consent</h1>
        <p class="text-slate-600 mb-6">
            You are invited to participate in a research study about how people complete workplace decision tasks using AI-generated responses and supporting company documents.
        </p>
        <p class="text-slate-600 mb-6">
            In this study, you will complete two short workplace scenarios. In each scenario, you will read a colleague’s message and an AI-generated draft response, and then decide what response you would send. Company documents may be available during the tasks.
        </p>

        <h2 class="text-lg font-semibold text-slate-800 mb-2">Estimated duration</h2>
        <p class="text-slate-600 mb-6">
            The study takes approximately 8–12 minutes.
        </p>

        <h2 class="text-lg font-semibold text-slate-800 mb-2">Participation incentive</h2>
        <p class="text-slate-600 mb-4">
            As a thank-you for participating, you can enter a raffle for a €50 VVV Cadeaubon after completing the study.
        </p>
        <p class="text-slate-600 mb-6">
            The raffle is not based on your answers, accuracy, confidence ratings, document use, or any specific response option. However, the study involves realistic workplace decision-making tasks, so your responses are only useful if you read the information carefully and answer as you would in a real workplace situation.
        </p>

        <h2 class="text-lg font-semibold text-slate-800 mb-2">Voluntary participation</h2>
        <p class="text-slate-600 mb-6">
            Participation is voluntary. You may stop at any time by closing your browser window.
        </p>

        <h2 class="text-lg font-semibold text-slate-800 mb-2">Confidentiality and anonymity</h2>
        <p class="text-slate-600 mb-6">
            Your survey responses will be collected anonymously and used for research purposes only. No personally identifying information will be collected with your survey responses. Results will be analyzed in aggregate form.
        </p>
        <p class="text-slate-600 mb-6">
            If you choose to enter the raffle, your email address will be collected on a separate final page. Your email address will only be used to contact the raffle winner and will be stored separately from your survey responses.
        </p>

        <h2 class="text-lg font-semibold text-slate-800 mb-2">Instructions</h2>
        <p class="text-slate-600 mb-6">
            Please read each task carefully and respond as you normally would in a workplace situation. All necessary information is provided within the survey.
        </p>

        <h2 class="text-lg font-semibold text-slate-800 mb-2">Consent confirmation</h2>
        <p class="text-slate-600 mb-2">By continuing, you confirm that:</p>
        <ul class="list-disc pl-6 text-slate-600 mb-6 space-y-1">
            <li>You are at least 18 years old.</li>
            <li>You have read and understood the information above.</li>
            <li>You agree to participate voluntarily.</li>
        </ul>
        <form id="intro-consent-form" method="get" action="before_begin.php">
            <label class="flex items-center gap-2 text-slate-700 mb-4">
                <input
                    id="consent-confirmation"
                    type="checkbox"
                    required
                    class="h-4 w-4"
                >
                <span>I consent to participate in this study.</span>
            </label>
            <button
                id="consent-continue-button"
                type="submit"
                class="inline-block accent-bg accent-bg-hover text-white font-medium px-5 py-3 rounded-lg transition opacity-60 cursor-not-allowed"
                disabled
            >
                Continue
            </button>
        </form>
    </section>
</main>

<script>
    (function () {
        var consentCheckbox = document.getElementById('consent-confirmation');
        var continueButton = document.getElementById('consent-continue-button');
        var consentForm = document.getElementById('intro-consent-form');
        var participantId = <?= (int) $participantId ?>;
        var consentStorageKey = 'thesis_intro_consent_' + String(participantId || 'default');
        if (!consentCheckbox || !continueButton) {
            return;
        }

        function loadSavedConsentState() {
            try {
                var saved = localStorage.getItem(consentStorageKey);
                if (saved === '1') {
                    consentCheckbox.checked = true;
                } else if (saved === '0') {
                    consentCheckbox.checked = false;
                }
            } catch (error) {
                // Ignore localStorage read failures silently.
            }
        }

        function saveConsentState() {
            try {
                localStorage.setItem(consentStorageKey, consentCheckbox.checked ? '1' : '0');
            } catch (error) {
                // Ignore localStorage write failures silently.
            }
        }

        function syncConsentState() {
            continueButton.disabled = !consentCheckbox.checked;
            if (consentCheckbox.checked) {
                continueButton.classList.remove('opacity-60', 'cursor-not-allowed');
            } else {
                continueButton.classList.add('opacity-60', 'cursor-not-allowed');
            }
            saveConsentState();
        }

        consentCheckbox.addEventListener('change', syncConsentState);
        if (consentForm) {
            consentForm.addEventListener('submit', function () {
                saveConsentState();
            });
        }
        loadSavedConsentState();
        syncConsentState();
    })();
</script>

<?php require __DIR__ . '/../views/footer.php'; ?>
