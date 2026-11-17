<?php

namespace App\Services\Pixelforge;

use App\Models\Generation;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Safety net for webhooks: if we haven't heard about an in-flight generation
 * for a while, ask Pixelforge directly. Webhooks are the main path; this just
 * makes sure nothing stays "processing" forever if every delivery failed.
 */
class GenerationPoller
{
    public function __construct(
        private readonly PixelforgeClient $client,
        private readonly GenerationSync $sync,
    ) {}

    public function pollStale(int $olderThanSeconds = 60, ?int $userId = null, int $limit = 20): int
    {
        $stale = Generation::query()
            ->active()
            ->whereNotNull('pixelforge_id')
            ->where(fn ($q) => $q
                ->where('last_synced_at', '<', now()->subSeconds($olderThanSeconds))
                ->orWhereNull('last_synced_at'))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->oldest('last_synced_at')
            ->limit($limit)
            ->get();

        foreach ($stale as $generation) {
            try {
                $response = $this->client->getGeneration($generation->pixelforge_id);

                if ($response->successful()) {
                    $this->sync->apply($generation, $response->json());
                } else {
                    $generation->forceFill(['last_synced_at' => now()])->save();
                }
            } catch (Throwable $e) {
                Log::warning('Polling Pixelforge failed', ['generation' => $generation->uuid, 'error' => $e->getMessage()]);
            }
        }

        return $stale->count();
    }
}
