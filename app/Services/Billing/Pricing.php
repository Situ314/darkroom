<?php

namespace App\Services\Billing;

use App\Services\Pixelforge\PixelforgeClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pixelforge's published price list (GET /v1/pricing).
 *
 * Only used for *estimates*. The final cost always uses the rates stored on the
 * generation itself, because Pixelforge fixes them at creation time.
 */
class Pricing
{
    /** Values from the docs, used if the pricing endpoint can't be reached. */
    public const FALLBACK = [
        'rates' => [
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ],
        'tokens' => [
            'characters_per_text_token' => 4,
            'text_token_variance' => 0.15,
            'per_reference_image' => 765,
            'output' => [
                'low' => ['1024x1024' => 280, '1536x1024' => 420, '1024x1536' => 420],
                'medium' => ['1024x1024' => 1100, '1536x1024' => 1650, '1024x1536' => 1650],
                'high' => ['1024x1024' => 4300, '1536x1024' => 6450, '1024x1536' => 6450],
            ],
            'reasoning' => [
                'low' => ['min' => 0, 'max' => 500],
                'medium' => ['min' => 200, 'max' => 2000],
                'high' => ['min' => 1000, 'max' => 8000],
            ],
        ],
    ];

    public const SIZES = ['1024x1024', '1536x1024', '1024x1536'];

    public const QUALITIES = ['low', 'medium', 'high'];

    public const MAX_REFERENCE_IMAGES = 4;

    /** @param array<string, mixed> $table */
    public function __construct(private readonly array $table) {}

    public static function current(): self
    {
        $table = Cache::remember('pixelforge.pricing', now()->addHour(), function () {
            try {
                $response = app(PixelforgeClient::class)->pricing();

                if ($response->successful() && is_array($response->json('rates'))) {
                    return $response->json();
                }
            } catch (Throwable $e) {
                Log::warning('Could not fetch Pixelforge pricing', ['error' => $e->getMessage()]);
            }

            return self::FALLBACK;
        });

        return new self($table);
    }

    /** @return array<string, string> USD per 1M tokens, by token type. */
    public function rates(): array
    {
        return $this->table['rates'];
    }

    public function outputTokens(string $quality, string $size): int
    {
        return (int) $this->table['tokens']['output'][$quality][$size];
    }

    /** @return array{min: int, max: int} */
    public function reasoningRange(string $quality): array
    {
        $range = $this->table['tokens']['reasoning'][$quality];

        return ['min' => (int) $range['min'], 'max' => (int) $range['max']];
    }

    public function tokensPerReferenceImage(): int
    {
        return (int) $this->table['tokens']['per_reference_image'];
    }

    public function charactersPerTextToken(): int
    {
        return (int) $this->table['tokens']['characters_per_text_token'];
    }

    public function textTokenVariance(): float
    {
        return (float) $this->table['tokens']['text_token_variance'];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->table;
    }
}
