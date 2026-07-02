<?php
declare(strict_types=1);

$form = class_exists('Form') ? Form::find((int) ($section['form_id'] ?? 0)) : false;
if (!$form || ($form['status'] ?? '') !== 'active') {
    return;
}
$fields = Form::fields((int) $form['id']);
$state = $formStates[(int) $form['id']] ?? ['success' => false, 'errors' => [], 'values' => []];
$sectionId = 'form-section-' . (int) $section['id'];
$formId = 'site-form-' . (int) $form['id'] . '-' . (int) $section['id'];
$siteKey = Form::recaptchaSiteKey($form);
?>
<section class="managed-section form-section" aria-labelledby="<?= e($sectionId) ?>">
    <h2 id="<?= e($sectionId) ?>"><?= e($section['heading'] ?: $form['title']) ?></h2>
    <?php if (trim((string) ($form['description'] ?? '')) !== ''): ?>
        <p><?= nl2br(e(trim((string) $form['description']))) ?></p>
    <?php endif; ?>

    <?php if (!empty($state['success'])): ?>
        <div class="form-status form-status-success" role="status" aria-live="polite">
            <h3>Submitted.</h3>
            <p><?= e(trim((string) ($form['success_message'] ?? '')) ?: 'Thanks. Your submission was received.') ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($state['errors'])): ?>
        <div class="form-status form-status-error" role="alert" aria-live="assertive">
            <h3>Check the form.</h3>
            <ul>
                <?php foreach ((array) $state['errors'] as $error): ?>
                    <li><?= e((string) $error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form id="<?= e($formId) ?>" class="contact-form site-managed-form" method="post" action="/<?= e($page['slug']) ?>" novalidate data-recaptcha-site-key="<?= e($siteKey) ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="form_id" value="<?= (int) $form['id'] ?>">
        <input type="hidden" name="g-recaptcha-response" value="">
        <div class="field field-honeypot" aria-hidden="true">
            <label for="<?= e($formId) ?>-website">Website</label>
            <input id="<?= e($formId) ?>-website" name="website" type="text" tabindex="-1" autocomplete="off">
        </div>

        <?php foreach ($fields as $field): ?>
            <?php
            $key = (string) $field['field_key'];
            $inputId = $formId . '-' . $key;
            $value = Form::valueFor($state, $key);
            $required = !empty($field['is_required']);
            $type = (string) $field['field_type'];
            ?>
            <div class="field">
                <?php if ($type === 'checkbox'): ?>
                    <label class="toggle-opt" for="<?= e($inputId) ?>">
                        <input id="<?= e($inputId) ?>" name="<?= e($key) ?>" type="checkbox" value="1" <?= $value !== '' || (($form['form_type'] ?? '') === Form::TYPE_NEWSLETTER && $key === 'consent') ? 'checked' : '' ?>>
                        <?= e($field['label']) ?>
                    </label>
                <?php else: ?>
                    <label for="<?= e($inputId) ?>"><?= e($field['label']) ?><?= $required ? ' *' : '' ?></label>
                    <?php if ($type === 'textarea'): ?>
                        <textarea id="<?= e($inputId) ?>" name="<?= e($key) ?>" rows="6" <?= $required ? 'required' : '' ?> placeholder="<?= e($field['placeholder'] ?? '') ?>"><?= e($value) ?></textarea>
                    <?php elseif ($type === 'select'): ?>
                        <select id="<?= e($inputId) ?>" name="<?= e($key) ?>" <?= $required ? 'required' : '' ?>>
                            <option value="">Choose one</option>
                            <?php foreach (Form::optionsForField($field) as $option): ?>
                                <option value="<?= e($option['value'] ?? '') ?>" <?= $value === (string) ($option['value'] ?? '') ? 'selected' : '' ?>><?= e($option['label'] ?? $option['value'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input id="<?= e($inputId) ?>" name="<?= e($key) ?>" type="<?= $type === 'email' ? 'email' : 'text' ?>" value="<?= e($value) ?>" <?= $required ? 'required' : '' ?> placeholder="<?= e($field['placeholder'] ?? '') ?>">
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (trim((string) ($field['help_text'] ?? '')) !== ''): ?>
                    <small><?= e($field['help_text']) ?></small>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <?php if ($siteKey !== ''): ?>
            <p class="privacy-note">This form is protected by reCAPTCHA and the Google <a href="https://policies.google.com/privacy">Privacy Policy</a> and <a href="https://policies.google.com/terms">Terms of Service</a> apply.</p>
        <?php endif; ?>
        <button class="button button-primary" type="submit"><?= e($form['submit_label'] ?? 'Submit') ?></button>
    </form>
</section>
<?php if ($siteKey !== ''): ?>
<script>
window.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById(<?= json_encode($formId) ?>);
    if (!form || !window.grecaptcha) return;
    form.addEventListener('submit', function (event) {
        var tokenInput = form.querySelector('input[name="g-recaptcha-response"]');
        var siteKey = form.getAttribute('data-recaptcha-site-key');
        if (!tokenInput || !siteKey || tokenInput.value) return;
        event.preventDefault();
        window.grecaptcha.ready(function () {
            window.grecaptcha.execute(siteKey, { action: 'contact_submit' }).then(function (token) {
                tokenInput.value = token;
                form.submit();
            });
        });
    });
});
</script>
<script src="https://www.google.com/recaptcha/api.js?render=<?= e(rawurlencode($siteKey)) ?>" async defer></script>
<?php endif; ?>
