<?php

namespace App\Services\Pixelforge;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\Billing\CostCalculator;
use App\Services\Billing\CreditLedger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Applies Pixelforge's view of a generation (from a webhook, a poll, or the
 * create response) to our record.
 *
 * Rules:
 *  - Status only moves forward (queued → processing → succeeded|failed), so a
 *    late or duplicated event can never undo a newer state.
 *  - Cost is computed, and the user charged, exactly once: on the transition
 *    into "succeeded", while holding a row lock on the generation.
 */
class GenerationSync
{
    public function __construct(
        private readonly CostCalculator $calculator,
        private readonly CreditLedger $ledger,
    ) {}

    /** @param array<string, mixed> $remote A Pixelforge generation object */
    public function apply(Generation $generation, array $remote): Generation
    {
        return DB::transaction(function () use ($generation, $remote) {
            $generation = Generation::whereKey($generation->id)->lockForUpdate()->firstOrFail();
            $incoming = GenerationStatus::tryFrom($remote['status'] ?? '');

            $generation->pixelforge_id ??= $remote['id'] ?? null;
            $generation->submitted_at ??= $this->date($remote['created_at'] ?? null) ?? now();
            $generation->last_synced_at = now();

            if ($incoming && $this->shouldTransition($generation, $incoming)) {
                $generation->status = $incoming;
                $generation->started_at ??= $this->date($remote['started_at'] ?? null);

                match ($incoming) {
                    GenerationStatus::Succeeded => $this->recordSuccess($generation, $remote),
                    GenerationStatus::Failed => $this->recordFailure($generation, $remote),
                    default => null,
                };
            } elseif ($generation->status === GenerationStatus::Succeeded && ! empty($remote['image_url'])) {
                // Already final. Pixelforge hands out fresh signed URLs on every read; keep the latest.
                $generation->image_url = $remote['image_url'];
                $generation->image_url_expires_at = $this->date($remote['image_url_expires_at'] ?? null);
            }

            $generation->save();

            if ($generation->status === GenerationStatus::Succeeded) {
                $this->ledger->chargeFor($generation);
            }

            return $generation;
        });
    }

    /** We gave up before Pixelforge accepted it (or Pixelforge refused it). Never billed. */
    public function markSubmissionFailed(Generation $generation, string $code, string $message): void
    {
        DB::transaction(function () use ($generation, $code, $message) {
            $generation = Generation::whereKey($generation->id)->lockForUpdate()->firstOrFail();

            if ($generation->status->isFinal() || $generation->pixelforge_id) {
                return;
            }

            $generation->forceFill([
                'status' => GenerationStatus::Failed,
                'error_code' => $code,
                'error_message' => $message,
                'provider_cost_nanos' => 0,
                'charged_nanos' => 0,
                'completed_at' => now(),
            ])->save();
        });
    }

    private function shouldTransition(Generation $generation, GenerationStatus $incoming): bool
    {
        if ($generation->status->canMoveTo($incoming)) {
            return true;
        }

        // Edge case: we marked it failed because submitting kept erroring (e.g. timeouts),
        // but Pixelforge did create it after all. Its final state wins, so the user is
        // charged exactly what Pixelforge billed us.
        return $generation->status === GenerationStatus::Failed
            && $generation->getOriginal('pixelforge_id') === null
            && $incoming->isFinal();
    }

    /** @param array<string, mixed> $remote */
    private function recordSuccess(Generation $generation, array $remote): void
    {
        $usage = $remote['usage'] ?? null;
        $rates = $remote['rates'] ?? null;

        if (! is_array($usage) || ! is_array($rates)) {
            // Don't finalise without numbers; the caller fails and Pixelforge retries / we poll.
            throw new RuntimeException("Succeeded generation {$generation->pixelforge_id} has no usage or rates.");
        }

        $providerCost = $this->calculator->providerCost($usage, $rates);

        $generation->forceFill([
            'rates' => $rates,
            'input_text_tokens' => $usage['input_text_tokens'] ?? 0,
            'input_image_tokens' => $usage['input_image_tokens'] ?? 0,
            'output_tokens' => $usage['output_tokens'] ?? 0,
            'reasoning_tokens' => $usage['reasoning_tokens'] ?? 0,
            'provider_cost_nanos' => $providerCost,
            'charged_nanos' => $this->calculator->withMarkup($providerCost),
            'image_url' => $remote['image_url'] ?? null,
            'image_url_expires_at' => $this->date($remote['image_url_expires_at'] ?? null),
            'error_code' => null,
            'error_message' => null,
            'completed_at' => $this->date($remote['completed_at'] ?? null) ?? now(),
        ]);
    }

    /** @param array<string, mixed> $remote */
    private function recordFailure(Generation $generation, array $remote): void
    {
        $generation->forceFill([
            'error_code' => $remote['error']['code'] ?? 'generation_failed',
            'error_message' => $remote['error']['message'] ?? 'The generation failed.',
            'provider_cost_nanos' => 0,
            'charged_nanos' => 0,
            'completed_at' => $this->date($remote['completed_at'] ?? null) ?? now(),
        ]);
    }

    private function date(?string $value): ?CarbonImmutable
    {
        return $value ? CarbonImmutable::parse($value)->utc() : null;
    }
}
