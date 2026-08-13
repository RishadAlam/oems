<?php

declare(strict_types=1);

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('status_modifier')) {
    function status_modifier(mixed $value, string $domain): string
    {
        static $allowed = [
            'account' => ['active', 'inactive', 'suspended'],
            'attendance' => ['not_checked_in', 'present', 'absent'],
            'certificate' => ['valid', 'revoked'],
            'contact' => ['new', 'read', 'replied', 'archived'],
            'event' => ['draft', 'pending', 'approved', 'rejected', 'published', 'completed', 'cancelled'],
            'newsletter_campaign' => ['draft', 'queued', 'sent', 'failed'],
            'organizer_approval' => ['pending', 'approved', 'rejected'],
            'payment' => ['none', 'pending', 'paid', 'failed', 'refunded', 'partially_refunded', 'not_required'],
            'publication' => ['draft', 'published'],
            'registration' => ['pending', 'confirmed', 'cancelled', 'waitlisted', 'refunded'],
            'review' => ['pending', 'published', 'hidden'],
            'ticket' => ['none', 'valid', 'used', 'cancelled'],
            'tone' => ['info', 'success', 'warning', 'danger', 'neutral', 'muted'],
        ];
        $status = is_scalar($value) ? (string) $value : '';

        return in_array($status, $allowed[$domain] ?? [], true) ? $status : 'neutral';
    }
}

if (!function_exists('oems_status_label')) {
    function oems_status_label(mixed $value, array $labels = [], bool $humanize = true): string
    {
        $status = is_scalar($value) ? (string) $value : '';
        if (trim($status) === '') {
            return 'Unknown';
        }
        if (array_key_exists($status, $labels) && is_scalar($labels[$status])) {
            return (string) $labels[$status];
        }

        return $humanize ? ucfirst(str_replace('_', ' ', $status)) : $status;
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        $base = dirname(__DIR__, 2);

        return $path === '' ? $base : $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);

        if ($value === false) {
            return $default;
        }

        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }
}

if (!function_exists('old_value')) {
    function old_value(array $old, string $key, string $default = ''): string
    {
        return e($old[$key] ?? $default);
    }
}

if (!function_exists('field_error')) {
    function field_error(array $errors, string $field): ?string
    {
        $messages = $errors[$field] ?? [];

        return is_array($messages) && isset($messages[0]) ? (string) $messages[0] : null;
    }
}

if (!function_exists('form_control_attributes')) {
    function form_control_attributes(
        array $errors,
        string $field,
        array $helpIds = [],
        ?string $errorId = null,
    ): string {
        $describedBy = array_values(array_filter(
            array_map(static fn (mixed $id): string => trim((string) $id), $helpIds),
            static fn (string $id): bool => $id !== '',
        ));
        $invalid = field_error($errors, $field) !== null;

        if ($invalid) {
            $describedBy[] = $errorId ?? str_replace('_', '-', $field) . '-error';
        }

        $attributes = $invalid ? ' aria-invalid="true"' : '';

        if ($describedBy !== []) {
            $attributes .= ' aria-describedby="' . e(implode(' ', array_unique($describedBy))) . '"';
        }

        return $attributes;
    }
}

if (!function_exists('form_error_entries')) {
    function form_error_entries(array $errors, array $targets = [], array $labels = []): array
    {
        $entries = [];

        foreach ($errors as $field => $messages) {
            if (!is_string($field) || !is_array($messages) || !is_scalar($messages[0] ?? null)) {
                continue;
            }

            $entries[] = [
                'field' => $field,
                'target' => (string) ($targets[$field] ?? $field),
                'label' => (string) ($labels[$field] ?? ucfirst(str_replace('_', ' ', $field))),
                'message' => (string) $messages[0],
            ];
        }

        return $entries;
    }
}
