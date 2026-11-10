<?php

declare(strict_types=1);

namespace DevnullInsights;

use JsonException;

final readonly class HttpResponse
{
    /** @param array<string, string> $headers lower-cased header names */
    public function __construct(public int $status, public array $headers, public string $body)
    {
    }

    /** @return array<mixed> */
    public function json(): array
    {
        try {
            $data = json_decode($this->body, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new DevnullException("Response is not JSON: {$exception->getMessage()}", previous: $exception);
        }

        if (! is_array($data)) {
            throw new DevnullException('Response JSON is not an object');
        }

        return $data;
    }
}
