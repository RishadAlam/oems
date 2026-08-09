<?php

declare(strict_types=1);

namespace OEMS\Tests\Support;

use OEMS\App\Contracts\HttpClientInterface;
use Throwable;

final class FakeHttpClient implements HttpClientInterface
{
    public int $calls = 0;

    /** @var list<array{url: string, headers: array, timeout: int}> */
    public array $requests = [];

    /** @var list<array{status: int, body: string}|Throwable> */
    private array $responses;

    public function __construct(int $status, string $body, ?Throwable $exception = null)
    {
        $this->responses = [$exception ?? ['status' => $status, 'body' => $body]];
    }

    /** @param list<array{status: int, body: string}|Throwable> $responses */
    public static function sequence(array $responses): self
    {
        $client = new self(200, '[]');
        $client->responses = $responses;

        return $client;
    }

    public function get(string $url, array $headers, int $timeoutSeconds): array
    {
        $this->calls++;
        $this->requests[] = ['url' => $url, 'headers' => $headers, 'timeout' => $timeoutSeconds];
        $response = array_shift($this->responses) ?? ['status' => 200, 'body' => '[]'];

        if ($response instanceof Throwable) {
            throw $response;
        }

        return $response;
    }
}
