<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

$language = resolveLanguage($_GET['lang'] ?? null);
$messages = loadTranslations($language);
$csrfToken = ensureCsrfToken();

$resetFlow = $_SESSION['reset_flow'] ?? null;
$currentStep = 1;

if (is_array($resetFlow) && !empty($resetFlow['otp_verified'])) {
    $currentStep = 3;
} elseif (is_array($resetFlow) && !empty($resetFlow['username'])) {
    $currentStep = 2;
}

$jsMessages = [
    'successTitle' => $messages['js_success_title'],
    'errorTitle' => $messages['js_error_title'],
    'networkError' => $messages['js_network_error'],
    'passwordHintTitle' => $messages['js_password_hint_title'],
    'passwordRules' => $messages['password_rules'],
    'processing' => $messages['processing'],
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($language, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($messages['title'], ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://js.hcaptcha.com/1/api.js?hl=<?= urlencode($language) ?>" async defer></script>
    <style>
        :root {
            --brand-navy: #702127;
            --brand-blue: #8f3141;
            --brand-cyan: #a94b5b;
            --brand-mint: #f2dfe3;
            --surface: #ffffff;
            --surface-soft: #fbf5f6;
            --border: rgba(112, 33, 39, 0.12);
            --text-main: #40161a;
            --text-muted: #7a5a5d;
            --success: #1b8b5f;
            --danger: #b94141;
        }

        body {
            min-height: 100vh;
            margin: 0;
            font-family: 'Manrope', sans-serif;
            color: var(--text-main);
            background: #f7f1f2;
        }

        .page-shell {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
        }

        .language-switcher {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 20;
            background: #ffffff;
            border: 1px solid rgba(112, 33, 39, 0.08);
            border-radius: 999px;
            padding: 6px;
        }

        .language-switcher a {
            text-decoration: none;
            color: var(--text-muted);
            padding: 8px 14px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.92rem;
            display: inline-block;
        }

        .language-switcher a.active {
            background: var(--brand-navy);
            color: #fff;
        }

        .wizard-card {
            width: 100%;
            max-width: 980px;
            background: var(--surface);
            border: 1px solid rgba(112, 33, 39, 0.12);
            border-radius: 28px;
            overflow: hidden;
        }

        .wizard-grid {
            display: grid;
            grid-template-columns: 320px 1fr;
        }

        .wizard-aside {
            background: var(--brand-navy);
            color: #fff;
            padding: 40px 30px;
        }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 14px;
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .wizard-title {
            margin-top: 24px;
            font-size: 2rem;
            line-height: 1.15;
            font-weight: 800;
        }

        .wizard-subtitle {
            margin-top: 14px;
            font-size: 0.98rem;
            line-height: 1.7;
            color: rgba(255, 255, 255, 0.84);
            max-width: 240px;
        }

        .step-list {
            margin-top: 32px;
            display: grid;
            gap: 16px;
        }

        .step-chip {
            display: flex;
            gap: 14px;
            align-items: flex-start;
            padding: 14px 16px;
            border-radius: 18px;
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .step-chip.is-active {
            background: #8f3141;
            border-color: rgba(242, 223, 227, 0.48);
        }

        .step-chip.is-complete {
            background: #7c3c45;
            border-color: rgba(255, 255, 255, 0.16);
        }

        .step-number {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 34px;
            font-weight: 800;
            background: rgba(255, 255, 255, 0.14);
        }

        .step-chip.is-active .step-number,
        .step-chip.is-complete .step-number {
            background: #fff;
            color: var(--brand-navy);
        }

        .step-copy h3 {
            font-size: 1rem;
            margin: 1px 0 6px;
            font-weight: 700;
        }

        .step-copy p {
            margin: 0;
            font-size: 0.9rem;
            line-height: 1.55;
            color: rgba(255, 255, 255, 0.78);
        }

        .wizard-main {
            padding: 42px 38px;
            background: #ffffff;
        }

        .step-pane {
            display: none;
        }

        .step-pane.active {
            display: block;
        }

        .section-kicker {
            font-size: 0.84rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--brand-blue);
        }

        .section-title {
            font-size: 1.7rem;
            font-weight: 800;
            margin: 10px 0 8px;
        }

        .section-desc {
            color: var(--text-muted);
            margin-bottom: 28px;
            line-height: 1.7;
        }

        .form-label {
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 8px;
        }

        .form-control {
            border-radius: 16px;
            border: 1px solid var(--border);
            background: #ffffff;
            min-height: 54px;
            padding: 0.9rem 1rem;
            color: var(--text-main);
            box-shadow: none;
        }

        .form-control:focus {
            border-color: rgba(112, 33, 39, 0.35);
            box-shadow: none;
        }

        .form-text {
            color: var(--text-muted);
        }

        .captcha-panel,
        .info-panel {
            background: var(--surface-soft);
            border: 1px solid rgba(112, 33, 39, 0.08);
            border-radius: 18px;
            padding: 18px;
        }

        .hcaptcha-wrap {
            display: flex;
            justify-content: center;
            overflow-x: auto;
        }

        .btn-brand {
            min-height: 54px;
            border: none;
            border-radius: 16px;
            font-weight: 800;
            background: var(--brand-navy);
        }

        .btn-brand:hover,
        .btn-brand:focus {
            background: #5e1b21;
        }

        .alert-inline {
            display: none;
            border-radius: 16px;
            border: 1px solid transparent;
            padding: 14px 16px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .alert-inline.active {
            display: block;
        }

        .alert-inline.error {
            background: rgba(185, 65, 65, 0.1);
            color: var(--danger);
            border-color: rgba(185, 65, 65, 0.18);
        }

        .alert-inline.success {
            background: rgba(27, 139, 95, 0.1);
            color: var(--success);
            border-color: rgba(27, 139, 95, 0.18);
        }

        @media (max-width: 991.98px) {
            .wizard-grid {
                grid-template-columns: 1fr;
            }

            .wizard-aside,
            .wizard-main {
                padding: 28px 22px;
            }

            .wizard-title {
                font-size: 1.7rem;
            }

            .wizard-subtitle {
                max-width: none;
            }
        }
    </style>
</head>
<body>
    <div class="language-switcher" aria-label="<?= htmlspecialchars($messages['language_label'], ENT_QUOTES, 'UTF-8') ?>">
        <a href="?lang=tr" class="<?= $language === 'tr' ? 'active' : '' ?>"><?= htmlspecialchars($messages['lang_tr'], ENT_QUOTES, 'UTF-8') ?></a>
        <a href="?lang=en" class="<?= $language === 'en' ? 'active' : '' ?>"><?= htmlspecialchars($messages['lang_en'], ENT_QUOTES, 'UTF-8') ?></a>
    </div>

    <main class="page-shell">
        <section class="wizard-card">
            <div class="wizard-grid">
                <aside class="wizard-aside">
                    <div class="brand-badge">SSPR / Active Directory</div>
                    <h1 class="wizard-title"><?= htmlspecialchars($messages['title'], ENT_QUOTES, 'UTF-8') ?></h1>
                    <p class="wizard-subtitle"><?= htmlspecialchars($messages['subtitle'], ENT_QUOTES, 'UTF-8') ?></p>

                    <div class="step-list">
                        <div class="step-chip <?= $currentStep === 1 ? 'is-active' : ($currentStep > 1 ? 'is-complete' : '') ?>" data-step-chip="1">
                            <div class="step-number">1</div>
                            <div class="step-copy">
                                <h3><?= htmlspecialchars($messages['step1_title'], ENT_QUOTES, 'UTF-8') ?></h3>
                                <p><?= htmlspecialchars($messages['step1_desc'], ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        </div>
                        <div class="step-chip <?= $currentStep === 2 ? 'is-active' : ($currentStep > 2 ? 'is-complete' : '') ?>" data-step-chip="2">
                            <div class="step-number">2</div>
                            <div class="step-copy">
                                <h3><?= htmlspecialchars($messages['step2_title'], ENT_QUOTES, 'UTF-8') ?></h3>
                                <p><?= htmlspecialchars($messages['step2_desc'], ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        </div>
                        <div class="step-chip <?= $currentStep === 3 ? 'is-active' : '' ?>" data-step-chip="3">
                            <div class="step-number">3</div>
                            <div class="step-copy">
                                <h3><?= htmlspecialchars($messages['step3_title'], ENT_QUOTES, 'UTF-8') ?></h3>
                                <p><?= htmlspecialchars($messages['step3_desc'], ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        </div>
                    </div>
                </aside>

                <div class="wizard-main">
                    <div id="global-alert" class="alert-inline" role="alert"></div>

                    <section class="step-pane <?= $currentStep === 1 ? 'active' : '' ?>" data-step-pane="1">
                        <div class="section-kicker"><?= htmlspecialchars($messages['wizard_step'], ENT_QUOTES, 'UTF-8') ?> 1</div>
                        <h2 class="section-title"><?= htmlspecialchars($messages['step1_title'], ENT_QUOTES, 'UTF-8') ?></h2>
                        <p class="section-desc"><?= htmlspecialchars($messages['step1_desc'], ENT_QUOTES, 'UTF-8') ?></p>

                        <form id="step1-form" novalidate>
                            <input type="hidden" name="action" value="step1">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                            <div class="mb-3">
                                <label for="username" class="form-label"><?= htmlspecialchars($messages['username'], ENT_QUOTES, 'UTF-8') ?></label>
                                <input id="username" name="username" type="text" class="form-control" autocomplete="username" placeholder="<?= htmlspecialchars($messages['username_placeholder'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="tc_kimlik" class="form-label"><?= htmlspecialchars($messages['tc_kimlik'], ENT_QUOTES, 'UTF-8') ?></label>
                                <input id="tc_kimlik" name="tc_kimlik" type="text" inputmode="numeric" class="form-control" placeholder="<?= htmlspecialchars($messages['tc_kimlik_placeholder'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>

                            <div class="mb-4">
                                <label for="phone_last4" class="form-label"><?= htmlspecialchars($messages['phone_last4'], ENT_QUOTES, 'UTF-8') ?></label>
                                <input id="phone_last4" name="phone_last4" type="text" inputmode="numeric" maxlength="4" class="form-control" placeholder="<?= htmlspecialchars($messages['phone_last4_placeholder'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>

                            <div class="captcha-panel mb-4">
                                <div class="form-label"><?= htmlspecialchars($messages['captcha_label'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="hcaptcha-wrap">
                                    <div class="h-captcha" data-sitekey="<?= htmlspecialchars(HCAPTCHA_SITE_KEY, ENT_QUOTES, 'UTF-8') ?>"></div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-brand w-100" data-submit-button>
                                <span data-button-text><?= htmlspecialchars($messages['send_code'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="spinner-border spinner-border-sm ms-2 d-none" aria-hidden="true"></span>
                            </button>
                        </form>
                    </section>

                    <section class="step-pane <?= $currentStep === 2 ? 'active' : '' ?>" data-step-pane="2">
                        <div class="section-kicker"><?= htmlspecialchars($messages['wizard_step'], ENT_QUOTES, 'UTF-8') ?> 2</div>
                        <h2 class="section-title"><?= htmlspecialchars($messages['step2_title'], ENT_QUOTES, 'UTF-8') ?></h2>
                        <p class="section-desc"><?= htmlspecialchars($messages['step2_desc'], ENT_QUOTES, 'UTF-8') ?></p>

                        <div class="info-panel mb-4">
                            <?= htmlspecialchars($messages['otp_sent_info'], ENT_QUOTES, 'UTF-8') ?>
                        </div>

                        <form id="step2-form" novalidate>
                            <input type="hidden" name="action" value="step2">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                            <div class="mb-4">
                                <label for="otp_code" class="form-label"><?= htmlspecialchars($messages['otp_code'], ENT_QUOTES, 'UTF-8') ?></label>
                                <input id="otp_code" name="otp_code" type="text" inputmode="numeric" maxlength="6" class="form-control text-center fs-4" placeholder="<?= htmlspecialchars($messages['otp_code_placeholder'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>

                            <button type="submit" class="btn btn-primary btn-brand w-100" data-submit-button>
                                <span data-button-text><?= htmlspecialchars($messages['verify_code'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="spinner-border spinner-border-sm ms-2 d-none" aria-hidden="true"></span>
                            </button>
                        </form>
                    </section>

                    <section class="step-pane <?= $currentStep === 3 ? 'active' : '' ?>" data-step-pane="3">
                        <div class="section-kicker"><?= htmlspecialchars($messages['wizard_step'], ENT_QUOTES, 'UTF-8') ?> 3</div>
                        <h2 class="section-title"><?= htmlspecialchars($messages['step3_title'], ENT_QUOTES, 'UTF-8') ?></h2>
                        <p class="section-desc"><?= htmlspecialchars($messages['step3_desc'], ENT_QUOTES, 'UTF-8') ?></p>

                        <div class="info-panel mb-4">
                            <strong><?= htmlspecialchars($messages['js_password_hint_title'], ENT_QUOTES, 'UTF-8') ?>:</strong>
                            <?= htmlspecialchars($messages['password_rules'], ENT_QUOTES, 'UTF-8') ?>
                        </div>

                        <form id="step3-form" novalidate>
                            <input type="hidden" name="action" value="step3">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                            <div class="mb-3">
                                <label for="new_password" class="form-label"><?= htmlspecialchars($messages['new_password'], ENT_QUOTES, 'UTF-8') ?></label>
                                <input id="new_password" name="new_password" type="password" class="form-control" autocomplete="new-password" placeholder="<?= htmlspecialchars($messages['new_password_placeholder'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>

                            <div class="mb-2">
                                <label for="new_password_confirm" class="form-label"><?= htmlspecialchars($messages['new_password_confirm'], ENT_QUOTES, 'UTF-8') ?></label>
                                <input id="new_password_confirm" name="new_password_confirm" type="password" class="form-control" autocomplete="new-password" placeholder="<?= htmlspecialchars($messages['new_password_confirm_placeholder'], ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>

                            <div class="form-text mb-4"><?= htmlspecialchars($messages['password_rules'], ENT_QUOTES, 'UTF-8') ?></div>

                            <button type="submit" class="btn btn-primary btn-brand w-100" data-submit-button>
                                <span data-button-text><?= htmlspecialchars($messages['reset_password'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="spinner-border spinner-border-sm ms-2 d-none" aria-hidden="true"></span>
                            </button>
                        </form>
                    </section>

                    <p class="mt-4 mb-0 text-center text-secondary small"><?= htmlspecialchars($messages['footer_note'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
        </section>
    </main>

    <script>
        const currentLang = <?= json_encode($language, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const uiMessages = <?= json_encode($jsMessages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

        function setAlert(message, type) {
            const alertBox = document.getElementById('global-alert');
            alertBox.className = `alert-inline active ${type}`;
            alertBox.textContent = message;
        }

        function clearAlert() {
            const alertBox = document.getElementById('global-alert');
            alertBox.className = 'alert-inline';
            alertBox.textContent = '';
        }

        function setLoading(form, isLoading) {
            const button = form.querySelector('[data-submit-button]');
            const spinner = button.querySelector('.spinner-border');
            const label = button.querySelector('[data-button-text]');
            const originalText = button.dataset.originalText || label.textContent;

            button.dataset.originalText = originalText;
            button.disabled = isLoading;
            spinner.classList.toggle('d-none', !isLoading);
            label.textContent = isLoading ? uiMessages.processing : originalText;
        }

        function activateStep(step) {
            document.querySelectorAll('[data-step-pane]').forEach((pane) => {
                pane.classList.toggle('active', Number(pane.dataset.stepPane) === step);
            });

            document.querySelectorAll('[data-step-chip]').forEach((chip) => {
                const chipStep = Number(chip.dataset.stepChip);
                chip.classList.toggle('is-active', chipStep === step);
                chip.classList.toggle('is-complete', chipStep < step);
            });

            clearAlert();
        }

        async function postForm(form) {
            const response = await fetch(`ajax_handler.php?lang=${encodeURIComponent(currentLang)}`, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin'
            });

            const payload = await response.json();

            if (!response.ok) {
                throw payload;
            }

            return payload;
        }

        document.getElementById('step1-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            clearAlert();
            setLoading(form, true);

            try {
                const payload = await postForm(form);
                setAlert(payload.message, 'success');
                activateStep(2);
            } catch (error) {
                setAlert(error.message || uiMessages.networkError, 'error');
                if (typeof hcaptcha !== 'undefined') {
                    hcaptcha.reset();
                }
            } finally {
                setLoading(form, false);
            }
        });

        document.getElementById('step2-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            clearAlert();
            setLoading(form, true);

            try {
                const payload = await postForm(form);
                setAlert(payload.message, 'success');
                activateStep(3);
            } catch (error) {
                setAlert(error.message || uiMessages.networkError, 'error');
            } finally {
                setLoading(form, false);
            }
        });

        document.getElementById('step3-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            clearAlert();

            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('new_password_confirm').value;

            if (newPassword !== confirmPassword) {
                setAlert(<?= json_encode($messages['error_password_match'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, 'error');
                return;
            }

            const complexity = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;
            if (!complexity.test(newPassword)) {
                setAlert(<?= json_encode($messages['error_password_complexity'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, 'error');
                return;
            }

            setLoading(form, true);

            try {
                const payload = await postForm(form);
                setAlert(payload.message, 'success');
                form.reset();
                if (typeof hcaptcha !== 'undefined') {
                    hcaptcha.reset();
                }
                setTimeout(() => window.location.href = `index.php?lang=${encodeURIComponent(currentLang)}`, 1200);
            } catch (error) {
                setAlert(error.message || uiMessages.networkError, 'error');
            } finally {
                setLoading(form, false);
            }
        });
    </script>
</body>
</html>
