<?php

namespace Tests\Unit;

use App\Services\Billing\CostCalculator;
use App\Services\Billing\Pricing;
use App\Support\Money;
use PHPUnit\Framework\TestCase;

class CostCalculatorTest extends TestCase
{
    private CostCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new CostCalculator('1.25');
    }

    public function test_it_matches_the_worked_example_in_the_pixelforge_docs(): void
    {
        // high, 1536x1024, 400-char prompt, 2 reference images, worst case = $0.373875000
        $cost = $this->calculator->providerCost([
            'input_text_tokens' => 115,
            'input_image_tokens' => 1530,
            'output_tokens' => 6450,
            'reasoning_tokens' => 8000,
        ], Pricing::FALLBACK['rates']);

        $this->assertSame(373_875_000, $cost);
        $this->assertSame('0.373875000', Money::toUsd($cost));
    }

    public function test_fractions_of_a_cent_are_kept_exactly(): void
    {
        // 1 text token at $5.00 / 1M = $0.000005 = 5000 nano-dollars.
        $cost = $this->calculator->providerCost(['input_text_tokens' => 1], Pricing::FALLBACK['rates']);
        $this->assertSame(5_000, $cost);

        // 3 reasoning tokens at $12.50 / 1M = $0.0000375
        $cost = $this->calculator->providerCost(['reasoning_tokens' => 3], Pricing::FALLBACK['rates']);
        $this->assertSame(37_500, $cost);
    }

    public function test_it_uses_the_rates_it_is_given_not_the_current_price_list(): void
    {
        $cost = $this->calculator->providerCost(['output_tokens' => 1000], ['output_tokens' => '80.00']);

        $this->assertSame(80_000_000, $cost); // $0.08
    }

    public function test_markup_is_25_percent_and_rounds_up_to_the_next_nano_dollar(): void
    {
        $this->assertSame(467_343_750, $this->calculator->withMarkup(373_875_000));
        $this->assertSame(0, $this->calculator->withMarkup(0));
        // 10 * 1.25 = 12.5 → 13: we never charge below cost + 25%.
        $this->assertSame(13, $this->calculator->withMarkup(10));
    }

    public function test_estimate_returns_a_range_covering_text_and_reasoning_uncertainty(): void
    {
        $pricing = new Pricing(Pricing::FALLBACK);
        $prompt = str_repeat('a', 400); // ceil(400 / 4) = 100 text tokens

        $estimate = $this->calculator->estimate($pricing, $prompt, '1536x1024', 'high', 2);

        $this->assertSame(85, $estimate['tokens']['min']['input_text_tokens']);
        $this->assertSame(115, $estimate['tokens']['max']['input_text_tokens']);
        $this->assertSame(1530, $estimate['tokens']['max']['input_image_tokens']);
        $this->assertSame(6450, $estimate['tokens']['max']['output_tokens']);
        $this->assertSame(1000, $estimate['tokens']['min']['reasoning_tokens']);
        $this->assertSame(8000, $estimate['tokens']['max']['reasoning_tokens']);

        // Worst case equals the docs' example, plus our markup.
        $this->assertSame(373_875_000, $estimate['provider_max_nanos']);
        $this->assertSame(467_343_750, $estimate['max_nanos']);
        $this->assertLessThan($estimate['max_nanos'], $estimate['min_nanos']);
    }

    public function test_prompt_length_is_counted_in_unicode_characters(): void
    {
        $pricing = new Pricing(Pricing::FALLBACK);

        // 8 characters (but 24 bytes) → 2 tokens, not 6.
        $estimate = $this->calculator->estimate($pricing, '日本語の文章です', '1024x1024', 'low', 0);

        $this->assertSame(3, $estimate['tokens']['max']['input_text_tokens']); // ceil(2 * 1.15)
    }
}
