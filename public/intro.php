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
        <h2 class="text-lg font-semibold text-slate-800 mb-2">Study description</h2>
        <p class="text-slate-600 mb-3">
            You are invited to participate in a research study about how people complete workplace decision tasks using AI-generated responses and supporting documents.
        </p>
        <p class="text-slate-600 mb-6">
            During this study, you will be asked to review short workplace scenarios, consider AI-generated responses, and make decisions based on the information provided. In some cases, you may also choose to consult additional documents before making your decision.
        </p>

        <h2 class="text-lg font-semibold text-slate-800 mb-2">Estimated duration</h2>
        <p class="text-slate-600 mb-6">
            The study will take approximately 10-15 minutes to complete.
        </p>

        <h2 class="text-lg font-semibold text-slate-800 mb-2">Voluntary participation</h2>
        <p class="text-slate-600 mb-6">
            Your participation in this study is completely voluntary.<br>
            You may choose not to participate or to stop participating at any time without penalty.
        </p>

        <h2 class="text-lg font-semibold text-slate-800 mb-2">Confidentiality and anonymity</h2>
        <p class="text-slate-600 mb-6">
            All responses will be collected anonymously and used for research purposes only.<br>
            No personally identifying information will be collected, and your responses will be analyzed in aggregate form.
        </p>

        <h2 class="text-lg font-semibold text-slate-800 mb-2">Right to withdraw</h2>
        <p class="text-slate-600 mb-6">
            You may stop the study at any time by closing your browser window.<br>
            Any data collected up to that point may still be used in anonymized form unless you request otherwise.
        </p>

        <h2 class="text-lg font-semibold text-slate-800 mb-2">Instructions</h2>
        <p class="text-slate-600 mb-3">
            Please read each task carefully and respond as you normally would in a workplace situation.<br>
            You may consult any available information before making your decision.
        </p>
        <p class="text-slate-600 mb-6">
            There are no trick questions.<br>
            We are interested in your natural decision-making process.
        </p>

        <h2 class="text-lg font-semibold text-slate-800 mb-2">Consent confirmation</h2>
        <p class="text-slate-600 mb-2">Before continuing, please confirm that:</p>
        <ul class="list-disc pl-6 text-slate-600 mb-6 space-y-1">
            <li>You are at least 18 years old</li>
            <li>You have read and understood the information above</li>
            <li>You agree to participate in this study voluntarily</li>
        </ul>
        <form method="get" action="task.php">
            <input type="hidden" name="task" value="1">
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
                I agree to participate and continue
            </button>
        </form>
    </section>
</main>

<script>
    (function () {
        var consentCheckbox = document.getElementById('consent-confirmation');
        var continueButton = document.getElementById('consent-continue-button');
        if (!consentCheckbox || !continueButton) {
            return;
        }

        function syncConsentState() {
            continueButton.disabled = !consentCheckbox.checked;
            if (consentCheckbox.checked) {
                continueButton.classList.remove('opacity-60', 'cursor-not-allowed');
            } else {
                continueButton.classList.add('opacity-60', 'cursor-not-allowed');
            }
        }

        consentCheckbox.addEventListener('change', syncConsentState);
        syncConsentState();
    })();
</script>

<?php require __DIR__ . '/../views/footer.php'; ?>
