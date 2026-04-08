<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/db.php';

if (!has_valid_participant_session()) {
    redirect('index.php');
}

if (session_get('postsurvey_started_at') === null) {
    session_set('postsurvey_started_at', date('Y-m-d H:i:s'));
}

$pageTitle = 'Post-Survey';
require __DIR__ . '/../views/header.php';
?>

<main class="max-w-4xl mx-auto px-4 py-8">
    <section class="bg-white shadow rounded-xl p-4 mb-4">
        <div class="flex items-center justify-between mb-2">
            <p class="text-sm text-slate-500">Step 5 of 5</p>
            <p class="text-sm text-slate-500">Post-Survey</p>
        </div>
        <div class="w-full h-2 bg-slate-200 rounded">
            <div class="h-2 bg-blue-600 rounded" style="width: 100%"></div>
        </div>
    </section>

    <section class="bg-white shadow rounded-xl p-6 mb-6">
        <h1 class="text-2xl font-bold text-slate-800 mb-2">Post-Survey</h1>
        <p class="text-slate-600">Please complete this short survey before finishing the study.</p>
    </section>

    <form id="postsurvey-form" method="post" action="save_postsurvey.php" class="space-y-6">
        <section class="bg-white shadow rounded-xl p-6">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">A. Critical AI Literacy</h2>
            <p class="text-sm text-slate-600 mb-4">
                Scale: 1 = Strongly disagree, 2 = Disagree, 3 = Somewhat disagree, 4 = Neutral,
                5 = Somewhat agree, 6 = Agree, 7 = Strongly agree
            </p>

            <?php
            $aiLitItems = [
                1 => 'I understand that AI-generated responses can sound confident even when they are incorrect.',
                2 => 'I know that AI systems may omit important contextual information.',
                3 => 'I feel confident evaluating whether AI-generated information is reliable.',
                4 => 'I understand that AI systems can reflect biases in their training data.',
                5 => 'I know when it is necessary to verify AI-generated information.',
            ];
            ?>

            <div class="space-y-5">
                <?php foreach ($aiLitItems as $index => $item): ?>
                    <fieldset>
                        <legend class="text-slate-800 mb-2"><?= e($item) ?></legend>
                        <div class="flex flex-wrap gap-4 text-slate-700">
                            <?php for ($i = 1; $i <= 7; $i++): ?>
                                <label class="flex items-center gap-2">
                                    <input type="radio" name="ai_lit_<?= $index ?>" value="<?= $i ?>" required class="h-4 w-4">
                                    <span><?= $i ?></span>
                                </label>
                            <?php endfor; ?>
                        </div>
                    </fieldset>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="bg-white shadow rounded-xl p-6">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">B. Cognitive Reflection Test (CRT)</h2>

            <div class="space-y-5">
                <div>
                    <label class="block text-slate-800 mb-2">
                        1. A bat and a ball cost EUR 1.10 in total. The bat costs EUR 1 more than the ball.
                        How much does the ball cost?
                    </label>
                    <input
                        type="number"
                        step="0.01"
                        name="crt_1"
                        required
                        class="w-full max-w-xs rounded-lg border border-slate-300 px-3 py-2"
                    >
                </div>

                <div>
                    <label class="block text-slate-800 mb-2">
                        2. If it takes 5 machines 5 minutes to make 5 widgets, how long would it take
                        100 machines to make 100 widgets?
                    </label>
                    <input
                        type="number"
                        step="0.01"
                        name="crt_2"
                        required
                        class="w-full max-w-xs rounded-lg border border-slate-300 px-3 py-2"
                    >
                </div>

                <div>
                    <label class="block text-slate-800 mb-2">
                        3. In a lake, there is a patch of lily pads. Every day, the patch doubles in size.
                        If it takes 48 days to cover the whole lake, how long would it take to cover half the lake?
                    </label>
                    <input
                        type="number"
                        step="0.01"
                        name="crt_3"
                        required
                        class="w-full max-w-xs rounded-lg border border-slate-300 px-3 py-2"
                    >
                </div>
            </div>
        </section>

        <section class="bg-white shadow rounded-xl p-6">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">C. Background Questions</h2>

            <div class="space-y-5">
                <fieldset>
                    <legend class="text-slate-800 mb-2">AI experience</legend>
                    <div class="space-y-2 text-slate-700">
                        <label class="flex items-center gap-2">
                            <input type="radio" name="ai_experience" value="never" required class="h-4 w-4">
                            <span>Never used AI tools</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" name="ai_experience" value="occasionally" required class="h-4 w-4">
                            <span>Occasionally use AI tools</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" name="ai_experience" value="regularly" required class="h-4 w-4">
                            <span>Regularly use AI tools</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" name="ai_experience" value="daily" required class="h-4 w-4">
                            <span>Use AI tools daily</span>
                        </label>
                    </div>
                </fieldset>

                <div>
                    <label for="age" class="block text-slate-800 mb-2">Age</label>
                    <input
                        id="age"
                        type="number"
                        min="16"
                        max="100"
                        name="age"
                        required
                        class="w-full max-w-xs rounded-lg border border-slate-300 px-3 py-2"
                    >
                    <p id="age-error" class="mt-2 text-sm text-red-600 hidden">
                        Age must be between 16 and 100.
                    </p>
                </div>

                <fieldset>
                    <legend class="text-slate-800 mb-2">Gender</legend>
                    <div class="space-y-2 text-slate-700">
                        <label class="flex items-center gap-2">
                            <input type="radio" name="gender" value="male" required class="h-4 w-4">
                            <span>Male</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" name="gender" value="female" required class="h-4 w-4">
                            <span>Female</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" name="gender" value="non_binary" required class="h-4 w-4">
                            <span>Non-binary</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" name="gender" value="prefer_not_to_say" required class="h-4 w-4">
                            <span>Prefer not to say</span>
                        </label>
                    </div>
                </fieldset>

                <fieldset>
                    <legend class="text-slate-800 mb-2">Education</legend>
                    <div class="space-y-2 text-slate-700">
                        <label class="flex items-center gap-2">
                            <input type="radio" name="education" value="high_school" required class="h-4 w-4">
                            <span>High school</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" name="education" value="bachelors" required class="h-4 w-4">
                            <span>Bachelor's degree</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" name="education" value="masters" required class="h-4 w-4">
                            <span>Master's degree</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" name="education" value="phd" required class="h-4 w-4">
                            <span>PhD</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" name="education" value="other" required class="h-4 w-4">
                            <span>Other</span>
                        </label>
                    </div>
                </fieldset>
            </div>
        </section>

        <section class="bg-white shadow rounded-xl p-6">
            <p id="postsurvey-error" class="mb-4 text-sm text-red-600 hidden">
                Please complete all required fields before submitting.
            </p>
            <button
                type="submit"
                id="postsurvey-submit-button"
                class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-5 py-3 rounded-lg transition"
            >
                Submit Post-Survey
            </button>
        </section>
    </form>
</main>

<script>
    (function () {
        var form = document.getElementById('postsurvey-form');
        var ageInput = document.getElementById('age');
        var ageError = document.getElementById('age-error');
        var submitButton = document.getElementById('postsurvey-submit-button');
        var postsurveyError = document.getElementById('postsurvey-error');

        function validateAge() {
            var value = ageInput.value.trim();
            var age = Number(value);
            var isValid = value !== '' && Number.isInteger(age) && age >= 16 && age <= 100;

            if (isValid) {
                ageError.classList.add('hidden');
                ageInput.classList.remove('border-red-500');
            } else {
                ageError.classList.remove('hidden');
                ageInput.classList.add('border-red-500');
            }

            return isValid;
        }

        ageInput.addEventListener('blur', validateAge);
        ageInput.addEventListener('input', function () {
            if (!ageError.classList.contains('hidden')) {
                validateAge();
            }
        });

        form.addEventListener('submit', function (event) {
            if (!validateAge()) {
                event.preventDefault();
                ageInput.focus();
                postsurveyError.classList.remove('hidden');
                return;
            }

            if (!form.checkValidity()) {
                event.preventDefault();
                postsurveyError.classList.remove('hidden');
                return;
            }

            postsurveyError.classList.add('hidden');

            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Submitting...';
                submitButton.classList.add('opacity-60', 'cursor-not-allowed');
            }
        });
    })();
</script>

<?php require __DIR__ . '/../views/footer.php'; ?>
