<?php

declare(strict_types=1);

namespace OEMS\App\Services;

use Closure;
use OEMS\App\Contracts\PlatformSettingsRepositoryInterface;
use Throwable;

final class MaintenanceService
{
    private const CACHE_TTL_SECONDS = 5;

    private readonly Closure $clock;

    public function __construct(
        private readonly PlatformSettingsRepositoryInterface $settings,
        private readonly string $cachePath,
        ?Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): int => time();
    }

    public function isEnabled(): bool
    {
        $now = ($this->clock)();
        $cached = $this->readCache();
        if ($cached !== null && $cached['expires_at'] >= $now) {
            return $cached['enabled'];
        }

        try {
            $values = $this->settings->privateValuesForKeys(['maintenance_mode']);
            $enabled = in_array(strtolower(trim((string) ($values['maintenance_mode'] ?? '0'))), ['1', 'true', 'yes', 'on'], true);
        } catch (Throwable) {
            $enabled = $cached['enabled'] ?? false;
        }
        $this->writeCache($enabled, $now + self::CACHE_TTL_SECONDS);

        return $enabled;
    }

    public function setEnabled(bool $enabled, int $adminUserId): void
    {
        $this->settings->setMaintenance($enabled, $adminUserId);
        $this->writeCache($enabled, ($this->clock)() + self::CACHE_TTL_SECONDS);
    }

    /** @return array{enabled:bool,expires_at:int}|null */
    private function readCache(): ?array
    {
        $raw = @file_get_contents($this->cachePath);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) && is_bool($decoded['enabled'] ?? null) && is_int($decoded['expires_at'] ?? null)
            ? ['enabled' => $decoded['enabled'], 'expires_at' => $decoded['expires_at']]
            : null;
    }

    private function writeCache(bool $enabled, int $expiresAt): void
    {
        $directory = dirname($this->cachePath);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return;
        }
        try {
            $temporary = tempnam($directory, '.maintenance-');
            if (!is_string($temporary)) {
                return;
            }
            if (file_put_contents($temporary, json_encode(['enabled' => $enabled, 'expires_at' => $expiresAt], JSON_THROW_ON_ERROR), LOCK_EX) === false) {
                @unlink($temporary);
                return;
            }
            @chmod($temporary, 0600);
            if (!@rename($temporary, $this->cachePath)) {
                @unlink($temporary);
            }
        } catch (Throwable) {
        }
    }
}
