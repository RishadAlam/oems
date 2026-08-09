<?php

declare(strict_types=1);

namespace OEMS\App\Support;

use Closure;
use OEMS\App\Contracts\HttpClientInterface;
use RuntimeException;

final class StreamHttpClient implements HttpClientInterface
{
    private readonly Closure $clock;

    public function __construct(
        private readonly string $userAgent,
        private readonly int $maxResponseBytes = 1048576,
        ?Closure $clock = null,
    ) {
        if ($this->maxResponseBytes < 1) {
            throw new RuntimeException('HTTP response limit must be positive.');
        }

        $this->clock = $clock ?? static fn (): float => microtime(true);
    }

    public function get(string $url, array $headers, int $timeoutSeconds): array
    {
        $timeoutSeconds = max(1, $timeoutSeconds);
        $deadline = ($this->clock)() + $timeoutSeconds;
        $headers = array_merge([
            'Accept' => 'application/json',
            'User-Agent' => $this->userAgent,
        ], $headers);
        $lines = [];

        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . str_replace(["\r", "\n"], '', (string) $value);
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $lines),
                'timeout' => $timeoutSeconds,
                'ignore_errors' => true,
                'follow_location' => 0,
                'max_redirects' => 0,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ],
        ]);
        $stream = @fopen($url, 'rb', false, $context);

        if ($stream === false) {
            throw new RuntimeException('Unable to complete HTTP request.');
        }

        $body = '';

        try {
            while (!feof($stream)) {
                $remaining = $deadline - ($this->clock)();

                if ($remaining <= 0) {
                    throw new RuntimeException('HTTP request timed out.');
                }

                $seconds = (int) floor($remaining);
                $microseconds = max(1, (int) (($remaining - $seconds) * 1_000_000));
                stream_set_timeout($stream, $seconds, $microseconds);
                $chunk = fread($stream, min(8192, $this->maxResponseBytes - strlen($body) + 1));
                $metadata = stream_get_meta_data($stream);

                if ($chunk === false || !empty($metadata['timed_out'])) {
                    throw new RuntimeException('HTTP request timed out.');
                }

                $body .= $chunk;

                if (strlen($body) > $this->maxResponseBytes) {
                    throw new RuntimeException('HTTP response exceeded the allowed size.');
                }
            }

            if (($this->clock)() > $deadline) {
                throw new RuntimeException('HTTP request timed out.');
            }

            $metadata = stream_get_meta_data($stream);
        } finally {
            fclose($stream);
        }

        return [
            'status' => $this->status($metadata['wrapper_data'] ?? []),
            'body' => $body,
        ];
    }

    private function status(mixed $headers): int
    {
        if (!is_array($headers)) {
            return 0;
        }

        $status = 0;

        foreach ($headers as $header) {
            if (is_string($header) && preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $header, $match) === 1) {
                $status = (int) $match[1];
            }
        }

        return $status;
    }
}
