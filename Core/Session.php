<?php

declare(strict_types=1);

namespace OEMS\Core;

final class Session
{
    public function __construct(bool $start = true, array $options = [])
    {
        if (!$start || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name((string) ($options['name'] ?? 'OEMS_SESSION'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure || (bool) ($options['secure'] ?? false),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->readNested($_SESSION, $key, $default);
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function put(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $target = &$_SESSION;

        foreach ($segments as $segment) {
            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                $target[$segment] = [];
            }

            $target = &$target[$segment];
        }

        $target = $value;
    }

    public function forget(string $key): void
    {
        $segments = explode('.', $key);
        $last = array_pop($segments);
        $target = &$_SESSION;

        foreach ($segments as $segment) {
            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                return;
            }

            $target = &$target[$segment];
        }

        unset($target[$last]);
    }

    public function flash(string $key, mixed $value): void
    {
        $this->put('_flash.' . $key, $value);
    }

    public function pullFlash(string $key, mixed $default = null): mixed
    {
        $value = $this->get('_flash.' . $key, $default);
        $this->forget('_flash.' . $key);

        return $value;
    }

    public function regenerate(): bool
    {
        return session_status() !== PHP_SESSION_ACTIVE || session_regenerate_id(true);
    }

    public function invalidate(): void
    {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            $parameters = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $parameters['path'],
                'domain' => $parameters['domain'],
                'secure' => $parameters['secure'],
                'httponly' => $parameters['httponly'],
                'samesite' => $parameters['samesite'] ?? 'Lax',
            ]);
            session_destroy();
        }
    }

    private function readNested(array $source, string $key, mixed $default): mixed
    {
        foreach (explode('.', $key) as $segment) {
            if (!is_array($source) || !array_key_exists($segment, $source)) {
                return $default;
            }

            $source = $source[$segment];
        }

        return $source;
    }
}
