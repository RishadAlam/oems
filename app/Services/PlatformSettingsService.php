<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use OEMS\App\Contracts\PlatformSettingsRepositoryInterface;
use OEMS\Core\Logger;
use Throwable;

final class PlatformSettingsService
{
    private const CATALOG = [
        'site_name' => ['default' => 'OEMS', 'max' => 80, 'required' => true],
        'site_tagline' => ['default' => 'Find your next room full of ideas.', 'max' => 160, 'required' => true],
        'contact_email' => ['default' => 'hello@oems.local', 'max' => 190, 'required' => true, 'email' => true],
        'support_phone' => ['default' => '+880 2 0000 0000', 'max' => 40, 'required' => true, 'phone' => true],
        'footer_blurb' => ['default' => 'Better tools for finding a crowd, filling a room, and running an event people remember.', 'max' => 240, 'required' => true],
        'footer_location' => ['default' => 'Dhaka, Bangladesh', 'max' => 120, 'required' => true],
        'home_hero_kicker' => ['default' => 'Events made for showing up', 'max' => 80, 'required' => true],
        'home_hero_title' => ['default' => 'Find your next standout event.', 'max' => 100, 'required' => true],
        'home_hero_copy' => ['default' => 'Discover workshops, talks, and gatherings across Dhaka, or host an event experience that feels effortless.', 'max' => 240, 'required' => true],
        'default_seo_description' => ['default' => 'Discover published workshops, talks, and gatherings with OEMS.', 'max' => 320, 'required' => true],
    ];

    public function __construct(
        private readonly PlatformSettingsRepositoryInterface $settings,
        private readonly ?Logger $logger = null,
    ) {
    }

    public static function defaults(): array
    {
        return array_map(static fn (array $definition): string => $definition['default'], self::CATALOG);
    }

    public function publicValues(): array
    {
        try {
            $stored = $this->settings->valuesForKeys(array_keys(self::CATALOG));
        } catch (Throwable) {
            $this->logFailure('read');
            return self::defaults();
        }

        $values = [];
        foreach (self::CATALOG as $key => $definition) {
            $candidate = $stored[$key] ?? null;
            $normalized = $this->normalize($candidate);
            $values[$key] = $this->isValid($normalized, $definition)
                ? $normalized
                : $definition['default'];
        }

        return $values;
    }

    public function update(array $data): array
    {
        $values = [];
        $errors = [];

        foreach (self::CATALOG as $key => $definition) {
            $value = $this->normalize($data[$key] ?? null);
            if (!$this->isValid($value, $definition)) {
                $errors[$key][] = !is_scalar($data[$key] ?? null)
                    ? 'Enter a valid text value.'
                    : ($key === 'contact_email' ? 'Enter a valid public contact email.' : 'Enter a value within the allowed length.');
                continue;
            }
            $values[$key] = $value;
        }

        if ($errors !== []) {
            return ['success' => false, 'errors' => $errors];
        }

        try {
            $this->settings->updateMany($values);
        } catch (Throwable) {
            $this->logFailure('update');
            return ['success' => false, 'errors' => ['settings' => ['Platform settings could not be saved.']]];
        }

        return ['success' => true, 'errors' => [], 'values' => $values];
    }

    private function normalize(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = str_replace(["\r\n", "\r"], "\n", trim((string) $value));

        return preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1 ? null : $value;
    }

    private function isValid(?string $value, array $definition): bool
    {
        if ($value === null || (($definition['required'] ?? false) && $value === '')) {
            return false;
        }

        if (mb_strlen($value) > (int) $definition['max']) {
            return false;
        }

        if (($definition['email'] ?? false) && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        return !($definition['phone'] ?? false) || preg_match('/^\+?[0-9][0-9 ()-]{5,39}$/', $value) === 1;
    }

    private function logFailure(string $operation): void
    {
        if ($this->logger === null) {
            return;
        }

        try {
            $this->logger->error('Platform settings persistence failed.', ['operation' => $operation]);
        } catch (Throwable) {
        }
    }
}
