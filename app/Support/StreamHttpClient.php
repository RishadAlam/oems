<?php

declare(strict_types=1);

namespace OEMS\App\Support;

use OEMS\App\Contracts\HttpClientInterface;
use RuntimeException;

final class StreamHttpClient implements HttpClientInterface
{
    public function __construct(private readonly string $userAgent)
    {
    }

    public function get(string $url, array $headers, int $timeoutSeconds): array
    {
        $timeoutSeconds = max(1, $timeoutSeconds);
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

        stream_set_timeout($stream, $timeoutSeconds);
        $body = stream_get_contents($stream);
        $metadata = stream_get_meta_data($stream);
        fclose($stream);

        if ($body === false || !empty($metadata['timed_out'])) {
            throw new RuntimeException('HTTP request timed out.');
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

        foreach ($headers as $header) {
            if (is_string($header) && preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $header, $match) === 1) {
                return (int) $match[1];
            }
        }

        return 0;
    }
}
