<?php

namespace App\Jobs;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\Pixelforge\GenerationSync;
use App\Services\Pixelforge\PixelforgeClient;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends a generation to Pixelforge.
 *
 * Runs on the queue so a busy provider never blocks the user's request. Every
 * attempt uses the generation's uuid as the Idempotency-Key, so retrying after
 * a timeout or a 503 can never create (or bill) a second generation.
 */
class SubmitGeneration implements ShouldQueue
{
    use Queueable;

    /** Give up on unexpected exceptions after a few tries; 429/503 just get released. */
    public int $maxExceptions = 3;

    public function __construct(public Generation $generation) {}

    /** Keep retrying rate/concurrency limits for a while, then give up. */
    public function retryUntil(): DateTimeInterface
    {
        return $this->generation->created_at->addMinutes(30);
    }

    public function handle(PixelforgeClient $client, GenerationSync $sync): void
    {
        $generation = $this->generation->fresh();

        // Already accepted (e.g. a webhook beat the create response) or already final.
        if (! $generation || $generation->status !== GenerationStatus::Pending || $generation->pixelforge_id) {
            return;
        }

        try {
            $response = $client->createGeneration($generation->toPixelforgePayload(), $generation->uuid);
        } catch (ConnectionException $e) {
            // We don't know if Pixelforge got it. Retrying with the same key is safe either way.
            Log::warning('Pixelforge unreachable, retrying', ['generation' => $generation->uuid, 'error' => $e->getMessage()]);
            $this->release($this->backoffSeconds());

            return;
        }

        if ($response->status() === 202 || $response->successful()) {
            $sync->apply($generation, $response->json());

            return;
        }

        $code = (string) $response->json('error.code', 'http_'.$response->status());

        match (true) {
            // Busy: too many requests per minute, 3 already running, or overloaded.
            in_array($code, ['rate_limit_exceeded', 'concurrency_limit_exceeded', 'overloaded'], true),
            $response->serverError() => $this->release($this->retryAfter($response)),

            default => $sync->markSubmissionFailed(
                $generation,
                $code,
                (string) $response->json('error.message', 'Pixelforge rejected the request.'),
            ),
        };
    }

    /** Called by Laravel once we run out of time or exceptions. */
    public function failed(?Throwable $exception): void
    {
        app(GenerationSync::class)->markSubmissionFailed(
            $this->generation,
            'submission_failed',
            'Could not reach Pixelforge in time. You were not charged.',
        );
    }

    private function retryAfter(Response $response): int
    {
        $header = (int) $response->header('Retry-After');

        return max($header, $this->backoffSeconds());
    }

    /** 2, 4, 8 … capped at 60 seconds, plus a little jitter so retries don't stampede. */
    private function backoffSeconds(): int
    {
        return min(60, 2 ** min($this->attempts(), 6)) + random_int(0, 2);
    }
}
