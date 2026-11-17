<?php

namespace App\Http\Controllers;

use App\Models\Generation;
use App\Models\WebhookEvent;
use App\Services\Pixelforge\GenerationSync;
use App\Services\Pixelforge\WebhookSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Receives Pixelforge webhooks.
 *
 * Delivery is at-least-once and may be out of order, so:
 *  1. The signature is checked against the *raw* body before anything else.
 *  2. The event id is inserted into a unique column; a duplicate is acknowledged
 *     and ignored.
 *  3. The event row and the state change are written in one transaction. If
 *     applying fails we roll back and return 500, so Pixelforge retries and the
 *     event is not lost.
 *  4. The state change itself only ever moves a generation forward (see GenerationSync).
 */
class PixelforgeWebhookController extends Controller
{
    public function __invoke(Request $request, WebhookSignature $signature, GenerationSync $sync): JsonResponse
    {
        $payload = $request->getContent();

        if (! $signature->isValid($payload, $request->header('Pixelforge-Signature'))) {
            Log::warning('Rejected Pixelforge webhook with an invalid signature', [
                'event_id' => $request->header('Pixelforge-Event-Id'),
            ]);

            return response()->json(['error' => 'invalid_signature'], 400);
        }

        $event = json_decode($payload, true);

        if (! is_array($event) || empty($event['id']) || empty($event['type'])) {
            return response()->json(['error' => 'invalid_payload'], 400);
        }

        $object = $event['data']['object'] ?? [];

        $result = DB::transaction(function () use ($event, $object, $sync) {
            $inserted = WebhookEvent::query()->insertOrIgnore([
                'event_id' => $event['id'],
                'type' => $event['type'],
                'pixelforge_generation_id' => $object['id'] ?? null,
                'payload' => json_encode($event),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted === 0) {
                return 'duplicate';
            }

            if (! str_starts_with($event['type'], 'generation.')) {
                return 'ignored'; // e.g. "ping" from the dashboard
            }

            $generation = $this->findGeneration($object);

            if (! $generation) {
                // Not ours (e.g. another environment sharing the key). Keep the event, do nothing.
                return 'unknown_generation';
            }

            $sync->apply($generation, $object);

            return 'processed';
        });

        return response()->json(['status' => $result]);
    }

    /** @param array<string, mixed> $object */
    private function findGeneration(array $object): ?Generation
    {
        // By Pixelforge id first; fall back to our uuid in metadata, because a
        // webhook can arrive before we've stored the id from the create response.
        $pixelforgeId = $object['id'] ?? null;
        $uuid = $object['metadata']['generation_uuid'] ?? null;

        return ($pixelforgeId ? Generation::where('pixelforge_id', $pixelforgeId)->first() : null)
            ?? ($uuid ? Generation::where('uuid', $uuid)->first() : null);
    }
}
