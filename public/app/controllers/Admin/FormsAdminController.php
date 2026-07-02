<?php

declare(strict_types=1);

class FormsAdminController
{
    public static function index(): void
    {
        admin_check();
        $formsTableReady = Form::tableReady();
        $forms = Form::all();
        require dirname(__DIR__, 2) . '/views/admin/forms/index.php';
    }

    public static function create(): void
    {
        admin_check();
        if (!Form::tableReady()) {
            $formsTableReady = false;
            $forms = [];
            require dirname(__DIR__, 2) . '/views/admin/forms/index.php';
            return;
        }
        $form = ['status' => 'active', 'form_type' => Form::TYPE_EMAIL, 'recaptcha_minimum_score' => '0.50', 'submit_label' => 'Submit'];
        $fields = [];
        $signups = [];
        $configSources = Form::configurationSources($form);
        $formError = null;
        require dirname(__DIR__, 2) . '/views/admin/forms/form.php';
    }

    public static function store(): void
    {
        admin_check();
        if (!Form::tableReady()) {
            header('Location: /admin/forms');
            exit;
        }
        try {
            $id = Form::create($_POST);
            header('Location: /admin/forms/' . $id . '/edit');
            exit;
        } catch (Throwable $e) {
            $form = $_POST + ['status' => 'active'];
            $fields = [];
            $signups = [];
            $configSources = Form::configurationSources($form);
            $formError = $e->getMessage();
            require dirname(__DIR__, 2) . '/views/admin/forms/form.php';
        }
    }

    public static function edit(string $id): void
    {
        admin_check();
        $form = Form::find((int) $id);
        if (!$form) {
            header('Location: /admin/forms');
            exit;
        }
        $fields = Form::fields((int) $id);
        $signups = ($form['form_type'] ?? '') === Form::TYPE_NEWSLETTER ? Form::signups((int) $id) : [];
        $configSources = Form::configurationSources($form);
        $formError = null;
        require dirname(__DIR__, 2) . '/views/admin/forms/form.php';
    }

    public static function update(string $id): void
    {
        admin_check();
        $form = Form::find((int) $id);
        if (!$form) {
            header('Location: /admin/forms');
            exit;
        }

        try {
            Form::update((int) $id, $_POST);
            header('Location: /admin/forms/' . (int) $id . '/edit');
            exit;
        } catch (Throwable $e) {
            $form = array_merge($form, $_POST);
            $fields = Form::fields((int) $id);
            $signups = ($form['form_type'] ?? '') === Form::TYPE_NEWSLETTER ? Form::signups((int) $id) : [];
            $configSources = Form::configurationSources($form);
            $formError = $e->getMessage();
            require dirname(__DIR__, 2) . '/views/admin/forms/form.php';
        }
    }

    public static function fieldCreate(string $formId): void
    {
        admin_check();
        $form = Form::find((int) $formId);
        if (!$form) {
            header('Location: /admin/forms');
            exit;
        }
        $field = ['field_type' => 'text', 'sort_order' => count(Form::fields((int) $formId))];
        $fieldError = null;
        require dirname(__DIR__, 2) . '/views/admin/forms/field-form.php';
    }

    public static function fieldStore(string $formId): void
    {
        admin_check();
        $form = Form::find((int) $formId);
        if (!$form) {
            header('Location: /admin/forms');
            exit;
        }
        try {
            Form::createField((int) $formId, $_POST);
            header('Location: /admin/forms/' . (int) $formId . '/edit');
            exit;
        } catch (Throwable $e) {
            $field = $_POST;
            $fieldError = $e->getMessage();
            require dirname(__DIR__, 2) . '/views/admin/forms/field-form.php';
        }
    }

    public static function fieldEdit(string $id): void
    {
        admin_check();
        $field = Form::field((int) $id);
        if (!$field) {
            header('Location: /admin/forms');
            exit;
        }
        $form = Form::find((int) $field['form_id']);
        $fieldError = null;
        require dirname(__DIR__, 2) . '/views/admin/forms/field-form.php';
    }

    public static function fieldUpdate(string $id): void
    {
        admin_check();
        $field = Form::field((int) $id);
        if (!$field) {
            header('Location: /admin/forms');
            exit;
        }
        $form = Form::find((int) $field['form_id']);
        try {
            Form::updateField((int) $id, $_POST);
            header('Location: /admin/forms/' . (int) $field['form_id'] . '/edit');
            exit;
        } catch (Throwable $e) {
            $field = array_merge($field, $_POST);
            $fieldError = $e->getMessage();
            require dirname(__DIR__, 2) . '/views/admin/forms/field-form.php';
        }
    }

    public static function fieldDelete(string $id): void
    {
        admin_check();
        $field = Form::field((int) $id);
        if ($field) {
            Form::deleteField((int) $id);
            header('Location: /admin/forms/' . (int) $field['form_id'] . '/edit');
            exit;
        }
        header('Location: /admin/forms');
        exit;
    }
}
