<?php

declare(strict_types=1);

namespace OEMS\App\Support;

final class RememberCookie
{
    public function __construct(
        private readonly string $name,
        private readonly bool $configuredSecure,
    ) {
    }

    public function header(string $value, int $expires): string
    {
        $parts = [
            rawurlencode($this->name) . '=' . rawurlencode($value),
            'Expires=' . gmdate('D, d M Y H:i:s T', $expires),
            'Max-Age=' . max(0, $expires - time()),
            'Path=/',
            'HttpOnly',
            'SameSite=Lax',
        ];

        if ($this->configuredSecure || $this->isDirectHttps()) {
            $parts[] = 'Secure';
        }

        return implode('; ', $parts);
    }

    public function forConsumptionResult(array $result): ?string
    {
        if (is_string($result['remember_cookie'] ?? null)
            && is_int($result['expires_at'] ?? null)) {
            return $this->header($result['remember_cookie'], $result['expires_at']);
        }

        if (($result['forget_cookie'] ?? false) === true) {
            return $this->header('', time() - 3600);
        }

        return null;
    }

    private function isDirectHttps(): bool
    {
        return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
    }
}
