<?php

namespace App\Services\Billing;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Turns token counts into money. Everything is exact decimal math and the
 * result is an integer number of nano-dollars (see App\Support\Money).
 */
class CostCalculator
{
    public const TOKEN_TYPES = [
        'input_text_tokens',
        'input_image_tokens',
        'output_tokens',
        'reasoning_tokens',
    ];

    public function __construct(private readonly string $markup = '1.25') {}

    /**
     * What Pixelforge charges us: sum of tokens × rate / 1M for each token type.
     *
     * @param  array<string, int>  $tokens
     * @param  array<string, string>  $rates  USD per 1M tokens, as Pixelforge returns them
     */
    public function providerCost(array $tokens, array $rates): int
    {
        $usd = BigDecimal::zero();

        foreach (self::TOKEN_TYPES as $type) {
            $usd = $usd->plus(
                BigDecimal::of($tokens[$type] ?? 0)->multipliedBy($rates[$type] ?? '0')
            );
        }

        // USD per 1M tokens → nano-dollars: × 1e9 / 1e6 = × 1000.
        // Exact for any rate with up to 3 decimals; rounded half-up beyond that.
        return $usd->multipliedBy(1000)->toScale(0, RoundingMode::HalfUp)->toInt();
    }

    /**
     * What we charge the user. Rounded *up* to the next nano-dollar so we never
     * charge less than cost + markup (the difference is at most $0.000000001).
     */
    public function withMarkup(int $providerNanos): int
    {
        return BigDecimal::of($providerNanos)
            ->multipliedBy($this->markup)
            ->toScale(0, RoundingMode::Up)
            ->toInt();
    }

    /**
     * Up-front estimate. Output and reference-image tokens are exact; text tokens
     * are ±15% of ceil(chars / 4); reasoning tokens fall in a range per quality.
     * So we return a min and a max, and use the max to reserve the user's credit.
     *
     * @return array{tokens: array{min: array<string, int>, max: array<string, int>}, provider_min_nanos: int, provider_max_nanos: int, min_nanos: int, max_nanos: int}
     */
    public function estimate(Pricing $pricing, string $prompt, string $size, string $quality, int $referenceImages): array
    {
        $chars = mb_strlen($prompt, 'UTF-8');
        $textEstimate = (int) ceil($chars / $pricing->charactersPerTextToken());
        // Integer percent so PHP and the JS estimate in the browser round identically.
        $variancePct = (int) round($pricing->textTokenVariance() * 100);
        $reasoning = $pricing->reasoningRange($quality);

        $fixed = [
            'input_image_tokens' => $referenceImages * $pricing->tokensPerReferenceImage(),
            'output_tokens' => $pricing->outputTokens($quality, $size),
        ];

        $min = $fixed + [
            'input_text_tokens' => intdiv($textEstimate * (100 - $variancePct), 100),
            'reasoning_tokens' => $reasoning['min'],
        ];
        $max = $fixed + [
            'input_text_tokens' => intdiv($textEstimate * (100 + $variancePct) + 99, 100),
            'reasoning_tokens' => $reasoning['max'],
        ];

        $minProvider = $this->providerCost($min, $pricing->rates());
        $maxProvider = $this->providerCost($max, $pricing->rates());

        return [
            'tokens' => ['min' => $min, 'max' => $max],
            'provider_min_nanos' => $minProvider,
            'provider_max_nanos' => $maxProvider,
            'min_nanos' => $this->withMarkup($minProvider),
            'max_nanos' => $this->withMarkup($maxProvider),
        ];
    }
}
