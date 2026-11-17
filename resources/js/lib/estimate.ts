import type { PricingTable, TokenUsage } from '@/types';

/**
 * Same formula as App\Services\Billing\CostCalculator::estimate(), so the user
 * sees the price instantly as they type. The server recomputes it on submit.
 *
 * Integer math throughout (nano-dollars), so no float drift.
 */
const TOKEN_TYPES: (keyof TokenUsage)[] = [
    'input_text_tokens',
    'input_image_tokens',
    'output_tokens',
    'reasoning_tokens',
];

/** "12.50" → 12500 nano-dollars per token (USD per 1M tokens × 1000). */
function nanosPerToken(rate: string): number {
    const [whole, fraction = ''] = rate.split('.');

    return Number(whole) * 1000 + Number(fraction.padEnd(3, '0').slice(0, 3));
}

function providerCost(
    tokens: TokenUsage,
    rates: PricingTable['rates'],
): number {
    return TOKEN_TYPES.reduce(
        (sum, type) => sum + tokens[type] * nanosPerToken(rates[type]),
        0,
    );
}

/** Markup like "1.25" applied exactly, rounded up to the next nano-dollar. */
function withMarkup(nanos: number, markup: string): number {
    const [whole, fraction = ''] = markup.split('.');
    const scale = 10 ** fraction.length;
    const numerator = Number(whole) * scale + Number(fraction || 0);

    return Math.ceil((nanos * numerator) / scale);
}

export type Estimate = {
    min: TokenUsage;
    max: TokenUsage;
    minNanos: number;
    maxNanos: number;
};

export function estimate(
    pricing: PricingTable,
    markup: string,
    prompt: string,
    size: string,
    quality: string,
    referenceImages: number,
): Estimate {
    const { tokens, rates } = pricing;
    // Count Unicode code points on purpose: that is what PHP mb_strlen (and Pixelforge) count.
    const chars = Array.from(prompt).length;
    const text = Math.ceil(chars / tokens.characters_per_text_token);
    const variancePct = Math.round(tokens.text_token_variance * 100);
    const reasoning = tokens.reasoning[quality];

    const fixed = {
        input_image_tokens: referenceImages * tokens.per_reference_image,
        output_tokens: tokens.output[quality][size],
    };

    const min: TokenUsage = {
        ...fixed,
        input_text_tokens: Math.floor((text * (100 - variancePct)) / 100),
        reasoning_tokens: reasoning.min,
    };
    const max: TokenUsage = {
        ...fixed,
        input_text_tokens: Math.ceil((text * (100 + variancePct)) / 100),
        reasoning_tokens: reasoning.max,
    };

    return {
        min,
        max,
        minNanos: withMarkup(providerCost(min, rates), markup),
        maxNanos: withMarkup(providerCost(max, rates), markup),
    };
}
