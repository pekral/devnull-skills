<?php

declare(strict_types=1);

namespace DevnullInsights;

/**
 * GET over PHP's own stream wrapper: no ext-curl, no shell tools, the same on Windows, macOS and Linux.
 * HTTPS needs ext-openssl only, which Composer itself requires.
 */
final readonly class HttpClient
{
    private const int Attempts = 3;

    public function __construct(private string $userAgent, private float $retryDelaySeconds = 5.0)
    {
    }

    /** @param list<string> $headers */
    public function get(string $url, array $headers): HttpResponse
    {
        if (str_starts_with($url, 'https://') && ! extension_loaded('openssl')) {
            throw new DevnullException('PHP extension "openssl" is required for HTTPS (enable extension=openssl in php.ini)');
        }

        for ($attempt = 1; ; $attempt++) {
            $response = $this->send($url, $headers);
            $retryable = $response->status === 0 || $response->status === 429 || $response->status >= 500;

            if (! $retryable || $attempt === self::Attempts) {
                return $response;
            }

            usleep((int) ($this->retryDelaySeconds * $attempt * 1_000_000));
        }
    }

    /** @param list<string> $headers */
    private function send(string $url, array $headers): HttpResponse
    {
        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers),
            'user_agent' => $this->userAgent,
            'timeout' => 30,
            'ignore_errors' => true,
        ]]);

        $body = @file_get_contents($url, false, $context);
        $lines = function_exists('http_get_last_response_headers') ? (http_get_last_response_headers() ?? []) : ($http_response_header ?? []);

        $status = 0;
        $parsed = [];
        foreach ($lines as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $match) === 1) {
                // A redirect starts a new header block; keep only the last response.
                $status = (int) $match[1];
                $parsed = [];
            } elseif (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $parsed[strtolower(trim($name))] = trim($value);
            }
        }

        return new HttpResponse($status, $parsed, is_string($body) ? $body : '');
    }
}
