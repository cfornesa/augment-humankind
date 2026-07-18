<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

class Form
{
    public const TYPE_EMAIL = 'email';
    public const TYPE_NEWSLETTER = 'newsletter';

    public static function tableReady(): bool
    {
        return function_exists('ah_table_exists') ? ah_table_exists('forms') : true;
    }

    public static function all(): array
    {
        if (!self::tableReady()) {
            return [];
        }
        return db()->query('SELECT * FROM forms ORDER BY title ASC, id ASC')->fetchAll();
    }

    public static function active(): array
    {
        if (!self::tableReady()) {
            return [];
        }
        return db()->query("SELECT * FROM forms WHERE status = 'active' ORDER BY title ASC, id ASC")->fetchAll();
    }

    public static function find(int $id): array|false
    {
        if (!self::tableReady()) {
            return false;
        }
        $stmt = db()->prepare('SELECT * FROM forms WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function findByKey(string $key): array|false
    {
        if (!self::tableReady()) {
            return false;
        }
        $stmt = db()->prepare('SELECT * FROM forms WHERE form_key = ? LIMIT 1');
        $stmt->execute([$key]);
        return $stmt->fetch();
    }

    public static function fields(int $formId): array
    {
        if (function_exists('ah_table_exists') && !ah_table_exists('form_fields')) {
            return [];
        }
        $stmt = db()->prepare('SELECT * FROM form_fields WHERE form_id = ? ORDER BY sort_order ASC, id ASC');
        $stmt->execute([$formId]);
        return $stmt->fetchAll();
    }

    public static function field(int $id): array|false
    {
        $stmt = db()->prepare('SELECT * FROM form_fields WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO forms
                (form_key, title, description, form_type, status, recipient_email,
                 recaptcha_site_key, encrypted_recaptcha_secret, recaptcha_minimum_score,
                 success_message, submit_label)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            self::normalizeKey((string) $data['form_key']),
            trim((string) $data['title']),
            self::nullIfBlank($data['description'] ?? null),
            self::validType((string) ($data['form_type'] ?? self::TYPE_EMAIL)),
            self::validStatus((string) ($data['status'] ?? 'active')),
            self::nullIfBlank($data['recipient_email'] ?? null),
            self::nullIfBlank($data['recaptcha_site_key'] ?? null),
            $data['encrypted_recaptcha_secret'] ?? null,
            self::minimumScore($data['recaptcha_minimum_score'] ?? 0.5),
            self::nullIfBlank($data['success_message'] ?? null),
            trim((string) ($data['submit_label'] ?? 'Submit')) ?: 'Submit',
        ]);
        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $existing = self::find($id);
        if (!$existing) {
            throw new InvalidArgumentException('Form not found.');
        }

        $encryptedSecret = $existing['encrypted_recaptcha_secret'] ?? null;
        $postedSecret = trim((string) ($data['recaptcha_secret'] ?? ''));
        if ($postedSecret !== '') {
            $encryptedSecret = encrypt_string($postedSecret, ai_encryption_key());
        } elseif (!empty($data['clear_recaptcha_secret'])) {
            $encryptedSecret = null;
        }

        $stmt = db()->prepare(
            'UPDATE forms
                SET title = ?, description = ?, form_type = ?, status = ?, recipient_email = ?,
                    recaptcha_site_key = ?, encrypted_recaptcha_secret = ?,
                    recaptcha_minimum_score = ?, success_message = ?, submit_label = ?
              WHERE id = ?'
        );
        $stmt->execute([
            trim((string) $data['title']),
            self::nullIfBlank($data['description'] ?? null),
            self::validType((string) ($data['form_type'] ?? self::TYPE_EMAIL)),
            self::validStatus((string) ($data['status'] ?? 'active')),
            self::nullIfBlank($data['recipient_email'] ?? null),
            self::nullIfBlank($data['recaptcha_site_key'] ?? null),
            $encryptedSecret,
            self::minimumScore($data['recaptcha_minimum_score'] ?? 0.5),
            self::nullIfBlank($data['success_message'] ?? null),
            trim((string) ($data['submit_label'] ?? 'Submit')) ?: 'Submit',
            $id,
        ]);
    }

    public static function createField(int $formId, array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO form_fields
                (form_id, field_key, label, field_type, help_text, placeholder, options_json, is_required, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $formId,
            self::normalizeKey((string) $data['field_key']),
            trim((string) $data['label']),
            self::validFieldType((string) ($data['field_type'] ?? 'text')),
            self::nullIfBlank($data['help_text'] ?? null),
            self::nullIfBlank($data['placeholder'] ?? null),
            self::optionsJson((string) ($data['options_text'] ?? '')),
            !empty($data['is_required']) ? 1 : 0,
            (int) ($data['sort_order'] ?? 0),
        ]);
        return (int) db()->lastInsertId();
    }

    public static function updateField(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE form_fields
                SET field_key = ?, label = ?, field_type = ?, help_text = ?, placeholder = ?,
                    options_json = ?, is_required = ?, sort_order = ?
              WHERE id = ?'
        );
        $stmt->execute([
            self::normalizeKey((string) $data['field_key']),
            trim((string) $data['label']),
            self::validFieldType((string) ($data['field_type'] ?? 'text')),
            self::nullIfBlank($data['help_text'] ?? null),
            self::nullIfBlank($data['placeholder'] ?? null),
            self::optionsJson((string) ($data['options_text'] ?? '')),
            !empty($data['is_required']) ? 1 : 0,
            (int) ($data['sort_order'] ?? 0),
            $id,
        ]);
    }

    public static function deleteField(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM form_fields WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function signups(int $formId): array
    {
        if (function_exists('ah_table_exists') && !ah_table_exists('newsletter_subscribers')) {
            return [];
        }
        $stmt = db()->prepare('SELECT * FROM newsletter_subscribers WHERE form_id = ? ORDER BY created_at DESC, id DESC');
        $stmt->execute([$formId]);
        return $stmt->fetchAll();
    }

    public static function handlePageSubmission(array $page, array $sections): array
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return [];
        }

        $formId = (int) ($_POST['form_id'] ?? 0);
        if ($formId <= 0) {
            return [];
        }

        $allowed = false;
        foreach ($sections as $section) {
            if (($section['section_kind'] ?? 'content') === 'form' && (int) ($section['form_id'] ?? 0) === $formId) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            return [];
        }

        $form = self::find($formId);
        if (!$form || ($form['status'] ?? 'inactive') !== 'active') {
            return [$formId => ['success' => false, 'errors' => ['This form is not available.'], 'values' => []]];
        }

        $fields = self::fields($formId);
        $errors = [];
        $values = self::postedValues($fields);

        if (function_exists('rate_limit_consume') && function_exists('rate_limit_subject_for_scope')) {
            $limit = rate_limit_consume('contact_submit', rate_limit_subject_for_scope('contact_submit'));
            if (!$limit['allowed']) {
                http_response_code(429);
                header('Retry-After: ' . $limit['retry_after']);
                $errors[] = 'Too many submissions were sent from this browser. Please wait a while and try again.';
            }
        }

        self::validatePostedForm($fields, $values, $errors);
        self::verifyRecaptchaForForm($form, $errors);

        if ($errors !== []) {
            return [$formId => ['success' => false, 'errors' => array_unique($errors), 'values' => $values]];
        }

        if (($form['form_type'] ?? self::TYPE_EMAIL) === self::TYPE_NEWSLETTER) {
            self::storeNewsletterSignup($form, $page, $values);
        } else {
            self::sendEmailSubmission($form, $fields, $values, $errors);
        }

        if ($errors !== []) {
            return [$formId => ['success' => false, 'errors' => array_unique($errors), 'values' => $values]];
        }

        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return [$formId => ['success' => true, 'errors' => [], 'values' => []]];
    }

    public static function valueFor(array $state, string $key, string $fallback = ''): string
    {
        $values = $state['values'] ?? [];
        return is_array($values) ? (string) ($values[$key] ?? $fallback) : $fallback;
    }

    public static function optionsForField(array $field): array
    {
        $raw = trim((string) ($field['options_json'] ?? ''));
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function optionsText(array $field): string
    {
        return implode("\n", array_map(
            static fn (array $option): string => (string) ($option['value'] ?? '') . '|' . (string) ($option['label'] ?? ''),
            self::optionsForField($field)
        ));
    }

    private static function postedValues(array $fields): array
    {
        $values = [];
        foreach ($fields as $field) {
            $key = (string) $field['field_key'];
            if (($field['field_type'] ?? '') === 'checkbox') {
                $values[$key] = !empty($_POST[$key]) ? '1' : '';
                continue;
            }
            $value = $_POST[$key] ?? '';
            $values[$key] = is_string($value) ? trim($value) : '';
        }
        return $values;
    }

    private static function validatePostedForm(array $fields, array $values, array &$errors): void
    {
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            $errors[] = 'The form could not be submitted.';
        }
        if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) ($_POST['csrf_token'] ?? ''))) {
            $errors[] = 'The form session expired. Please try again.';
        }

        foreach ($fields as $field) {
            $key = (string) $field['field_key'];
            $label = (string) $field['label'];
            $type = (string) $field['field_type'];
            $value = trim((string) ($values[$key] ?? ''));

            if (!empty($field['is_required']) && $value === '') {
                $errors[] = $label . ' is required.';
                continue;
            }
            if ($value === '') {
                continue;
            }
            if ($type === 'email' && (!filter_var($value, FILTER_VALIDATE_EMAIL) || strlen($value) > 254)) {
                $errors[] = 'Enter a valid email address.';
            }
            if ($type === 'textarea' && strlen($value) > 3000) {
                $errors[] = $label . ' must be 3000 characters or fewer.';
            }
            if ($type !== 'textarea' && strlen($value) > 500) {
                $errors[] = $label . ' is too long.';
            }
        }
    }

    private static function verifyRecaptchaForForm(array $form, array &$errors): void
    {
        $siteKey = trim((string) ($form['recaptcha_site_key'] ?? '')) ?: (function_exists('configValue') ? configValue('RECAPTCHA_SITE_KEY') : '');
        $secret = self::recaptchaSecret($form);
        if ($siteKey === '' && $secret === '') {
            return;
        }
        if ($secret === '') {
            $errors[] = 'The form verification is not configured.';
            return;
        }

        $token = trim((string) ($_POST['g-recaptcha-response'] ?? ''));
        if ($token === '') {
            $errors[] = 'Please retry the form verification before submitting.';
            return;
        }

        $requestBody = http_build_query([
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $requestBody,
                'timeout' => 8,
            ],
        ]);
        $rawResponse = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);
        $response = is_string($rawResponse) ? json_decode($rawResponse, true) : null;
        if (!is_array($response) || empty($response['success'])) {
            $errors[] = 'The form verification failed. Please try again.';
            return;
        }
        if (($response['action'] ?? '') !== 'contact_submit') {
            $errors[] = 'The form verification did not match this form.';
            return;
        }
        $hostname = strtolower((string) ($response['hostname'] ?? ''));
        $currentHost = function_exists('currentHostname') ? currentHostname() : '';
        if ($currentHost !== '' && $hostname !== '' && $hostname !== $currentHost) {
            $errors[] = 'The form verification did not match this website.';
            return;
        }
        $score = isset($response['score']) ? (float) $response['score'] : 0.0;
        if ($score < (float) ($form['recaptcha_minimum_score'] ?? 0.5)) {
            $errors[] = 'The form verification score was too low. Please try again.';
        }
    }

    public static function recaptchaSiteKey(array $form): string
    {
        return trim((string) ($form['recaptcha_site_key'] ?? '')) ?: (function_exists('configValue') ? configValue('RECAPTCHA_SITE_KEY') : '');
    }

    public static function configurationSources(array $form): array
    {
        $recipient = trim((string) ($form['recipient_email'] ?? ''));
        $siteKey = trim((string) ($form['recaptcha_site_key'] ?? ''));
        $encryptedSecret = trim((string) ($form['encrypted_recaptcha_secret'] ?? ''));
        $envRecipient = function_exists('configValue') ? configValue('CONTACT_TO_EMAIL') : ($_ENV['CONTACT_TO_EMAIL'] ?? '');
        $envSiteKey = function_exists('configValue') ? configValue('RECAPTCHA_SITE_KEY') : ($_ENV['RECAPTCHA_SITE_KEY'] ?? '');
        $envSecret = function_exists('configValue') ? configValue('RECAPTCHA_SECRET_KEY') : ($_ENV['RECAPTCHA_SECRET_KEY'] ?? '');

        return [
            'recipient_email' => self::sourceLabel($recipient !== '', trim((string) $envRecipient) !== ''),
            'recaptcha_site_key' => self::sourceLabel($siteKey !== '', trim((string) $envSiteKey) !== ''),
            'recaptcha_secret' => self::sourceLabel($encryptedSecret !== '', trim((string) $envSecret) !== ''),
        ];
    }

    private static function recaptchaSecret(array $form): string
    {
        $encrypted = trim((string) ($form['encrypted_recaptcha_secret'] ?? ''));
        if ($encrypted !== '') {
            try {
                return decrypt_string($encrypted, ai_encryption_key());
            } catch (Throwable) {
                return '';
            }
        }
        return function_exists('configValue') ? configValue('RECAPTCHA_SECRET_KEY') : '';
    }

    private static function sourceLabel(bool $hasDbValue, bool $hasEnvFallback): string
    {
        if ($hasDbValue) {
            return 'Using saved database value.';
        }
        if ($hasEnvFallback) {
            return 'Using .env fallback.';
        }
        return 'Missing.';
    }

    private static function storeNewsletterSignup(array $form, array $page, array $values): void
    {
        $email = trim((string) ($values['email'] ?? ''));
        if ($email === '') {
            return;
        }
        $consent = array_key_exists('consent', $values) ? (int) ((string) $values['consent'] !== '') : 1;
        $stmt = db()->prepare(
            'INSERT INTO newsletter_subscribers (form_id, page_id, email, consent, source_path)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE consent = VALUES(consent), source_path = VALUES(source_path), updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            (int) $form['id'],
            (int) $page['id'],
            $email,
            $consent,
            parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: null,
        ]);
    }

    private static function sendEmailSubmission(array $form, array $fields, array $values, array &$errors): void
    {
        $recipient = trim((string) ($form['recipient_email'] ?? '')) ?: (function_exists('configValue') ? configValue('CONTACT_TO_EMAIL') : '');
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'The form recipient email is not configured.';
            return;
        }

        $smtpErrors = [];
        $config = self::smtpTransportConfiguration($smtpErrors);
        if ($config === []) {
            $errors[] = $smtpErrors[0] ?? 'The form email configuration is incomplete.';
            return;
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $config['SMTP_HOST'];
            $mail->Port = (int) $config['SMTP_PORT'];
            $mail->SMTPAuth = true;
            $mail->Username = $config['SMTP_USERNAME'];
            $mail->Password = $config['SMTP_PASSWORD'];
            $encryption = strtolower($config['SMTP_ENCRYPTION']);
            $mail->SMTPSecure = ($encryption === 'tls' || $encryption === 'starttls')
                ? PHPMailer::ENCRYPTION_STARTTLS
                : PHPMailer::ENCRYPTION_SMTPS;
            $mail->CharSet = 'UTF-8';
            $mail->setFrom($config['SMTP_FROM_EMAIL'], $config['SMTP_FROM_NAME']);
            $mail->addAddress($recipient);
            if (!empty($values['email']) && filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo((string) $values['email'], (string) ($values['name'] ?? ''));
            }
            $mail->Subject = (function_exists('configValue') ? (configValue('APP_NAME') ?: 'Site') : 'Site') . ' form submission: ' . $form['title'];

            $lines = ['New form submission', '', 'Form: ' . $form['title'], 'Received: ' . gmdate('Y-m-d H:i:s') . ' UTC', ''];
            foreach ($fields as $field) {
                $key = (string) $field['field_key'];
                $lines[] = (string) $field['label'] . ': ' . ((string) ($values[$key] ?? '') !== '' ? (string) $values[$key] : 'Not provided');
            }
            $mail->Body = implode("\n", $lines);
            $mail->send();
        } catch (MailerException) {
            $errors[] = 'The message could not be sent right now. Please try again later.';
        }
    }

    private static function smtpTransportConfiguration(array &$errors): array
    {
        // Shared with magic-link sign-in; see app/helpers/mailer.php.
        return app_smtp_config($errors);
    }

    private static function normalizeKey(string $key): string
    {
        $key = strtolower(trim($key));
        $key = preg_replace('/[^a-z0-9_ -]/', '', $key) ?? '';
        $key = preg_replace('/[\s-]+/', '_', $key) ?? '';
        return trim($key, '_');
    }

    private static function nullIfBlank(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private static function validType(string $type): string
    {
        return in_array($type, [self::TYPE_EMAIL, self::TYPE_NEWSLETTER], true) ? $type : self::TYPE_EMAIL;
    }

    private static function validStatus(string $status): string
    {
        return in_array($status, ['active', 'inactive'], true) ? $status : 'active';
    }

    private static function validFieldType(string $type): string
    {
        return in_array($type, ['text', 'email', 'textarea', 'select', 'checkbox'], true) ? $type : 'text';
    }

    private static function minimumScore(mixed $value): float
    {
        $score = (float) $value;
        return max(0.0, min(1.0, $score));
    }

    private static function optionsJson(string $text): ?string
    {
        $options = [];
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            [$value, $label] = array_pad(explode('|', $line, 2), 2, null);
            $options[] = ['value' => trim($value), 'label' => trim($label ?? $value)];
        }
        return $options === [] ? null : json_encode($options, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
