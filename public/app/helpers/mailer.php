<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * SMTP transport settings from config, or [] when incomplete/invalid.
 * Shared by contact-form delivery (Form.php) and magic-link sign-in.
 */
function app_smtp_config(array &$errors = []): array
{
    $requiredConfig = [
        'SMTP_HOST',
        'SMTP_PORT',
        'SMTP_ENCRYPTION',
        'SMTP_USERNAME',
        'SMTP_PASSWORD',
        'SMTP_FROM_EMAIL',
        'SMTP_FROM_NAME',
    ];

    $config = [];
    foreach ($requiredConfig as $key) {
        $config[$key] = function_exists('configValue') ? configValue($key) : '';
        if ($config[$key] === '') {
            $errors[] = 'The form email configuration is incomplete.';
            return [];
        }
    }

    $encryption = strtolower($config['SMTP_ENCRYPTION']);
    $port = (int) $config['SMTP_PORT'];
    if (!filter_var($config['SMTP_FROM_EMAIL'], FILTER_VALIDATE_EMAIL)
        || !in_array($encryption, ['smtps', 'ssl', 'starttls', 'tls'], true)
        || (($encryption === 'smtps' || $encryption === 'ssl') && $port !== 465)
        || (($encryption === 'starttls' || $encryption === 'tls') && $port !== 587)) {
        $errors[] = 'The form email configuration is incomplete.';
        return [];
    }

    return $config;
}

function app_smtp_configured(): bool
{
    return app_smtp_config() !== [];
}

/**
 * Send a plain-text email through the configured SMTP transport.
 * $options: reply_to => [email, name].
 */
function app_send_mail(string $to, string $subject, string $textBody, array $options = []): bool
{
    $config = app_smtp_config();
    if ($config === [] || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
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
        $mail->addAddress($to);
        $replyTo = $options['reply_to'] ?? null;
        if (is_array($replyTo) && !empty($replyTo[0]) && filter_var((string) $replyTo[0], FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo((string) $replyTo[0], (string) ($replyTo[1] ?? ''));
        }
        $mail->Subject = $subject;
        $mail->Body = $textBody;
        $mail->send();
        return true;
    } catch (MailerException) {
        return false;
    }
}
