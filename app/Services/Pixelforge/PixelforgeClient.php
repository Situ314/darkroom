<?php

namespace App\Services\Pixelforge;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper over the Pixelforge REST API. It returns raw responses on
 * purpose: callers decide what a 429 or a 503 means for them.
 */
class PixelforgeClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey,
        private readonly int $timeout = 15,
    ) {}

    /** @param array<string, mixed> $payload */
    public function createGeneration(array $payload, string $idempotencyKey): Response
    {
        return $this->request()
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post('/generations', $payload);
    }

    public function getGeneration(string $id): Response
    {
        return $this->request()->get('/generations/'.urlencode($id));
    }

    public function pricing(): Response
    {
        return Http::baseUrl($this->baseUrl)->acceptJson()->timeout($this->timeout)->get('/pricing');
    }

    /** Dates are YYYY-MM-DD, UTC, inclusive. Max 92 days per call. */
    public function usage(string $startDate, string $endDate): Response
    {
        return $this->request()->get('/usage', [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken((string) $this->apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout);
    }
}
