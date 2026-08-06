<?php

declare(strict_types=1);

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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

