<?php

declare(strict_types=1);

namespace OEMS\Core;

use DateTimeImmutable;

final class Validator
{
    public static function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $ruleList = is_array($fieldRules) ? $fieldRules : explode('|', $fieldRules);
            $value = $data[$field] ?? null;
            $nullable = in_array('nullable', $ruleList, true);

            if ($nullable && ($value === null || $value === '')) {
                continue;
            }

            foreach ($ruleList as $ruleDefinition) {
                [$rule, $parameters] = self::parseRule((string) $ruleDefinition);
                $message = self::validateRule($field, $value, $rule, $parameters, $data);

                if ($message !== null) {
                    $errors[$field][] = $message;
                    break;
                }
            }
        }

        return $errors;
    }

    private static function parseRule(string $definition): array
    {
        [$rule, $parameterString] = array_pad(explode(':', $definition, 2), 2, '');
        $parameters = $parameterString === '' ? [] : explode(',', $parameterString);

        return [$rule, $parameters];
    }

    private static function validateRule(
        string $field,
        mixed $value,
        string $rule,
        array $parameters,
        array $data,
    ): ?string {
        $label = ucfirst(str_replace('_', ' ', $field));

        return match ($rule) {
            'required' => self::isEmpty($value) ? "{$label} is required." : null,
            'nullable' => null,
            'string' => $value !== null && !is_string($value) ? "{$label} must be text." : null,
            'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false
                ? null
                : "{$label} must be a valid email address.",
            'min' => self::length($value) >= (int) ($parameters[0] ?? 0)
                ? null
                : "{$label} must be at least {$parameters[0]} characters.",
            'max' => self::length($value) <= (int) ($parameters[0] ?? PHP_INT_MAX)
                ? null
                : "{$label} may not be longer than {$parameters[0]} characters.",
            'confirmed' => $value === ($data[$field . '_confirmation'] ?? null)
                ? null
                : "{$label} confirmation does not match.",
            'in' => in_array((string) $value, $parameters, true)
                ? null
                : "{$label} contains an invalid selection.",
            'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false
                ? null
                : "{$label} must be an integer.",
            'numeric' => is_numeric($value) ? null : "{$label} must be numeric.",
            'boolean' => in_array($value, [true, false, 0, 1, '0', '1'], true)
                ? null
                : "{$label} must be true or false.",
            'date' => self::isDate($value) ? null : "{$label} must be a valid date.",
            default => "{$label} uses an unsupported validation rule.",
        };
    }

    private static function isEmpty(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    private static function length(mixed $value): int
    {
        if (!is_string($value)) {
            return 0;
        }

        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }

    private static function isDate(mixed $value): bool
    {
        if (!is_string($value) || trim($value) === '') {
            return false;
        }

        try {
            new DateTimeImmutable($value);

            return true;
        } catch (\Exception) {
            return false;
        }
    }
}

