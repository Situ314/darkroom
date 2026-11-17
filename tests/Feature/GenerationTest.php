<?php

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Services\Billing\Pricing;
use App\Services\Pixelforge\GenerationSync;
use App\Services\Pixelforge\PixelforgeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\MakesPixelforgePayloads;
use Tests\TestCase;

class GenerationTest extends TestCase
{
    use MakesPixelforgePayloads, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPixelforge();
        // Pricing comes from the docs' values, no network.
        Cache::put('pixelforge.pricing', Pricing::FALLBACK, 3600);
    }

    public function test_guests_cannot_generate(): void
    {
        $this->get('/generate')->assertRedirect('/login');
        $this->post('/generations', ['prompt' => 'x'])->assertRedirect('/login');
    }

    public function test_the_generate_page_renders_with_pricing_and_balance(): void
    {
        $user = $this->makeUser('3.00');

        $this->actingAs($user)->get('/generate')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('generations/Create')
                ->where('availableNanos', 3_000_000_000)
                ->where('pricing.rates.output_tokens', '40.00'));
    }

    public function test_submitting_saves_the_estimate_and_queues_the_job(): void
    {
        Queue::fake();
        $user = $this->makeUser();

        $this->actingAs($user)->post('/generations', [
            'prompt' => str_repeat('a', 400),
            'size' => '1536x1024',
            'quality' => 'high',
            'reference_images' => ['https://example.com/a.png', '', 'https://example.com/b.png'],
        ])->assertRedirect();

        $generation = Generation::sole();
        $this->assertSame(GenerationStatus::Pending, $generation->status);
        $this->assertSame(['https://example.com/a.png', 'https://example.com/b.png'], $generation->reference_images);
        $this->assertSame(467_343_750, $generation->estimated_max_nanos); // docs' worst case + 25%
        Queue::assertPushed(SubmitGeneration::class);
    }

    public function test_it_validates_like_pixelforge_does(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post('/generations', [
            'prompt' => str_repeat('a', 4001),
            'size' => '800x600',
            'quality' => 'ultra',
            'reference_images' => array_fill(0, 5, 'https://example.com/x.png'),
        ])->assertSessionHasErrors(['prompt', 'size', 'quality', 'reference_images']);

        $this->actingAs($user)->post('/generations', [
            'prompt' => 'ok', 'size' => '1024x1024', 'quality' => 'low',
            'reference_images' => ['ftp://example.com/x.png'],
        ])->assertSessionHasErrors('reference_images.0');
    }

    public function test_users_cannot_generate_what_they_cannot_afford(): void
    {
        Queue::fake();
        $user = $this->makeUser('0.10'); // high + landscape can cost up to ~$0.47

        $this->actingAs($user)->post('/generations', [
            'prompt' => 'A castle', 'size' => '1536x1024', 'quality' => 'high',
        ])->assertSessionHasErrors('prompt');

        $this->assertSame(0, Generation::count());
        Queue::assertNothingPushed();
    }

    public function test_credit_held_by_in_flight_generations_counts_against_new_ones(): void
    {
        Queue::fake();
        $user = $this->makeUser('0.50');
        // Already one in flight that could cost up to $0.45.
        $this->makeGeneration($user, ['estimated_max_nanos' => 450_000_000]);

        $this->actingAs($user)->post('/generations', [
            'prompt' => 'A castle', 'size' => '1024x1024', 'quality' => 'medium',
        ])->assertSessionHasErrors('prompt');
    }

    public function test_the_job_sends_an_idempotency_key_and_stores_the_pixelforge_id(): void
    {
        $user = $this->makeUser();
        $generation = $this->makeGeneration($user, ['pixelforge_id' => null, 'status' => GenerationStatus::Pending]);

        Http::fake(['*/generations' => Http::response(
            $this->remoteGeneration($generation, 'queued', ['id' => 'gen_abc']), 202,
        )]);

        (new SubmitGeneration($generation))->handle(app(PixelforgeClient::class), app(GenerationSync::class));

        Http::assertSent(fn (Request $request) => $request->hasHeader('Idempotency-Key', $generation->uuid)
            && $request->hasHeader('Authorization', 'Bearer pf_test_key')
            && $request['metadata']['generation_uuid'] === $generation->uuid
            && ! isset($request['reference_images']));

        $generation->refresh();
        $this->assertSame('gen_abc', $generation->pixelforge_id);
        $this->assertSame(GenerationStatus::Queued, $generation->status);
    }

    public function test_the_job_backs_off_when_pixelforge_is_busy(): void
    {
        $generation = $this->makeGeneration($this->makeUser(), ['pixelforge_id' => null, 'status' => GenerationStatus::Pending]);

        foreach ([
            [429, 'concurrency_limit_exceeded', '5'],
            [429, 'rate_limit_exceeded', '12'],
            [503, 'overloaded', '2'],
        ] as [$status, $code, $retryAfter]) {
            Http::fake(['*' => Http::response(['error' => ['type' => 'x', 'code' => $code, 'message' => 'busy']], $status, ['Retry-After' => $retryAfter])]);

            $job = (new SubmitGeneration($generation))->withFakeQueueInteractions();
            $job->handle(app(PixelforgeClient::class), app(GenerationSync::class));

            $job->assertReleased();
            $this->assertSame(GenerationStatus::Pending, $generation->fresh()->status);
        }
    }

    public function test_a_refused_prompt_fails_without_charging(): void
    {
        $user = $this->makeUser('10.00');
        $generation = $this->makeGeneration($user, ['pixelforge_id' => null, 'status' => GenerationStatus::Pending]);

        Http::fake(['*' => Http::response(['error' => [
            'type' => 'invalid_request_error', 'code' => 'content_policy_violation', 'message' => 'Not allowed.',
        ]], 400)]);

        $job = (new SubmitGeneration($generation))->withFakeQueueInteractions();
        $job->handle(app(PixelforgeClient::class), app(GenerationSync::class));

        $job->assertNotReleased();
        $generation->refresh();
        $this->assertSame(GenerationStatus::Failed, $generation->status);
        $this->assertSame('content_policy_violation', $generation->error_code);
        $this->assertSame(0, $generation->charged_nanos);
        $this->assertSame(10_000_000_000, $user->fresh()->balance_nanos);
    }

    public function test_the_job_does_nothing_if_a_webhook_already_moved_it_on(): void
    {
        Http::fake();
        $generation = $this->makeGeneration($this->makeUser(), ['status' => GenerationStatus::Processing]);

        (new SubmitGeneration($generation))->handle(app(PixelforgeClient::class), app(GenerationSync::class));

        Http::assertNothingSent();
    }

    public function test_polling_picks_up_generations_whose_webhooks_never_arrived(): void
    {
        $user = $this->makeUser('10.00');
        $generation = $this->makeGeneration($user, ['last_synced_at' => now()->subMinutes(5)]);

        Http::fake(["*/generations/{$generation->pixelforge_id}" => Http::response($this->remoteGeneration($generation, 'succeeded'))]);

        $this->artisan('pixelforge:sync')->assertSuccessful();

        $this->assertSame(GenerationStatus::Succeeded, $generation->fresh()->status);
        $this->assertSame(78_475_000, $generation->fresh()->charged_nanos);
    }

    public function test_history_only_shows_your_own_generations(): void
    {
        $me = $this->makeUser();
        $this->makeGeneration($me, ['prompt' => 'mine', 'status' => GenerationStatus::Failed]);
        $this->makeGeneration($this->makeUser(), ['prompt' => 'theirs', 'status' => GenerationStatus::Failed]);

        $this->actingAs($me)->get('/history')
            ->assertInertia(fn ($page) => $page
                ->component('generations/Index')
                ->has('generations.data', 1)
                ->where('generations.data.0.prompt', 'mine'));
    }
}
