<?php

namespace Tests\Concerns;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Services\Pixelforge\WebhookSignature;
use App\Support\Money;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

trait MakesPixelforgePayloads
{
    protected string $webhookSecret = 'whsec_test_secret';

    protected function setUpPixelforge(): void
    {
        config([
            'services.pixelforge.key' => 'pf_test_key',
            'services.pixelforge.webhook_secret' => $this->webhookSecret,
            'billing.markup' => '1.25',
        ]);
    }

    protected function makeUser(string $balanceUsd = '10.00'): User
    {
        return User::factory()->create(['balance_nanos' => Money::toNanos($balanceUsd)]);
    }

    protected function makeGeneration(User $user, array $attributes = []): Generation
    {
        return $user->generations()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'pixelforge_id' => 'gen_'.Str::random(12),
            'status' => GenerationStatus::Queued,
            'prompt' => 'A lighthouse at dusk',
            'size' => '1024x1024',
            'quality' => 'medium',
            'reference_images' => [],
            'estimated_min_nanos' => 50_000_000,
            'estimated_max_nanos' => 90_000_000,
        ], $attributes));
    }

    /** A generation object shaped like Pixelforge's API returns it. */
    protected function remoteGeneration(Generation $generation, string $status, array $overrides = []): array
    {
        $object = [
            'id' => $generation->pixelforge_id,
            'object' => 'generation',
            'status' => $status,
            'prompt' => $generation->prompt,
            'size' => $generation->size,
            'quality' => $generation->quality,
            'reference_images' => [],
            'metadata' => ['generation_uuid' => $generation->uuid],
            'image_url' => null,
            'image_url_expires_at' => null,
            'usage' => null,
            'rates' => [
                'input_text_tokens' => '5.00',
                'input_image_tokens' => '10.00',
                'output_tokens' => '40.00',
                'reasoning_tokens' => '12.50',
            ],
            'error' => null,
            'created_at' => now()->subSeconds(20)->toIso8601String(),
            'started_at' => in_array($status, ['processing', 'succeeded', 'failed']) ? now()->subSeconds(10)->toIso8601String() : null,
            'completed_at' => in_array($status, ['succeeded', 'failed']) ? now()->toIso8601String() : null,
        ];

        if ($status === 'succeeded') {
            $object['image_url'] = 'https://pixelforge.promptedit.dev/images/'.$generation->pixelforge_id.'.png?sig=abc';
            $object['image_url_expires_at'] = now()->addDay()->toIso8601String();
            // 6×5000 + 0 + 1100×40000 + 1500×12500 = 62,780,000 nano-dollars ($0.06278)
            $object['usage'] = [
                'input_text_tokens' => 6,
                'input_image_tokens' => 0,
                'output_tokens' => 1100,
                'reasoning_tokens' => 1500,
                'total_tokens' => 2606,
            ];
        }

        if ($status === 'failed') {
            $object['error'] = ['code' => 'generation_failed', 'message' => 'The model could not produce an image.'];
        }

        return array_replace_recursive($object, $overrides);
    }

    protected function sendWebhook(array $object, ?string $eventId = null, ?string $type = null, ?string $signature = null): TestResponse
    {
        $body = json_encode([
            'id' => $eventId ?? 'evt_'.Str::random(16),
            'object' => 'event',
            'type' => $type ?? 'generation.'.$object['status'],
            'created_at' => now()->toIso8601String(),
            'data' => ['object' => $object],
        ]);

        $signature ??= (new WebhookSignature($this->webhookSecret))->sign($body);

        return $this->call('POST', '/webhooks/pixelforge', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PIXELFORGE_SIGNATURE' => $signature,
        ], $body);
    }
}
