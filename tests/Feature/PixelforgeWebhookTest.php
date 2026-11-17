<?php

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Models\CreditTransaction;
use App\Models\WebhookEvent;
use App\Services\Pixelforge\WebhookSignature;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesPixelforgePayloads;
use Tests\TestCase;

class PixelforgeWebhookTest extends TestCase
{
    use MakesPixelforgePayloads, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPixelforge();
    }

    public function test_it_rejects_a_bad_signature(): void
    {
        $generation = $this->makeGeneration($this->makeUser());

        $this->sendWebhook($this->remoteGeneration($generation, 'succeeded'), signature: 't='.time().',v1=deadbeef')
            ->assertStatus(400);

        $this->assertSame(GenerationStatus::Queued, $generation->fresh()->status);
        $this->assertSame(0, WebhookEvent::count());
    }

    public function test_it_rejects_an_old_timestamp(): void
    {
        $generation = $this->makeGeneration($this->makeUser());
        $object = $this->remoteGeneration($generation, 'succeeded');
        $body = json_encode(['id' => 'evt_old', 'type' => 'generation.succeeded', 'data' => ['object' => $object]]);
        $stale = (new WebhookSignature($this->webhookSecret))->sign($body, time() - 3600);

        $this->call('POST', '/webhooks/pixelforge', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PIXELFORGE_SIGNATURE' => $stale,
        ], $body)->assertStatus(400);
    }

    public function test_succeeded_event_records_the_actual_cost_and_charges_the_user(): void
    {
        $user = $this->makeUser('10.00');
        $generation = $this->makeGeneration($user);

        $this->sendWebhook($this->remoteGeneration($generation, 'succeeded'))
            ->assertOk()
            ->assertJson(['status' => 'processed']);

        $generation->refresh();
        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
        $this->assertSame(62_780_000, $generation->provider_cost_nanos);   // $0.06278
        $this->assertSame(78_475_000, $generation->charged_nanos);         // × 1.25
        $this->assertSame(1500, $generation->reasoning_tokens);
        $this->assertNotNull($generation->image_url);

        $this->assertSame(Money::toNanos('10.00') - 78_475_000, $user->fresh()->balance_nanos);
        $this->assertSame(1, CreditTransaction::where('generation_id', $generation->id)->count());
    }

    public function test_the_same_event_delivered_twice_is_only_recorded_once(): void
    {
        $user = $this->makeUser('10.00');
        $generation = $this->makeGeneration($user);
        $object = $this->remoteGeneration($generation, 'succeeded');

        $this->sendWebhook($object, eventId: 'evt_same')->assertJson(['status' => 'processed']);
        $this->sendWebhook($object, eventId: 'evt_same')->assertJson(['status' => 'duplicate']);

        $this->assertSame(1, WebhookEvent::count());
        $this->assertSame(1, CreditTransaction::where('type', 'charge')->count());
        $this->assertSame(Money::toNanos('10.00') - 78_475_000, $user->fresh()->balance_nanos);
    }

    public function test_a_different_event_for_an_already_succeeded_generation_does_not_charge_again(): void
    {
        // e.g. a resend from the dashboard (new event id) or a poll racing the webhook.
        $user = $this->makeUser('10.00');
        $generation = $this->makeGeneration($user);
        $object = $this->remoteGeneration($generation, 'succeeded');

        $this->sendWebhook($object, eventId: 'evt_one');
        $this->sendWebhook($object, eventId: 'evt_two');

        $this->assertSame(1, CreditTransaction::where('type', 'charge')->count());
        $this->assertSame(Money::toNanos('10.00') - 78_475_000, $user->fresh()->balance_nanos);
    }

    public function test_a_late_processing_event_never_moves_a_finished_generation_back(): void
    {
        $generation = $this->makeGeneration($this->makeUser());

        $this->sendWebhook($this->remoteGeneration($generation, 'succeeded'));
        $this->sendWebhook($this->remoteGeneration($generation, 'processing'))->assertOk();

        $this->assertSame(GenerationStatus::Succeeded, $generation->fresh()->status);
    }

    public function test_out_of_order_events_still_reach_the_right_final_state(): void
    {
        $generation = $this->makeGeneration($this->makeUser());

        $this->sendWebhook($this->remoteGeneration($generation, 'failed'));
        $this->sendWebhook($this->remoteGeneration($generation, 'processing'));

        $generation->refresh();
        $this->assertSame(GenerationStatus::Failed, $generation->status);
        $this->assertSame(0, $generation->charged_nanos);
    }

    public function test_failed_generations_cost_nothing(): void
    {
        $user = $this->makeUser('10.00');
        $generation = $this->makeGeneration($user);

        $this->sendWebhook($this->remoteGeneration($generation, 'failed'))->assertOk();

        $generation->refresh();
        $this->assertSame(GenerationStatus::Failed, $generation->status);
        $this->assertSame('generation_failed', $generation->error_code);
        $this->assertSame(0, $generation->provider_cost_nanos);
        $this->assertSame(0, $generation->charged_nanos);
        $this->assertSame(Money::toNanos('10.00'), $user->fresh()->balance_nanos);
        $this->assertSame(0, CreditTransaction::count());
    }

    public function test_a_webhook_that_beats_the_create_response_is_matched_by_metadata(): void
    {
        $user = $this->makeUser();
        // We haven't stored Pixelforge's id yet: the POST is still in flight.
        $generation = $this->makeGeneration($user, ['pixelforge_id' => null, 'status' => GenerationStatus::Pending]);

        $object = $this->remoteGeneration($generation, 'processing', ['id' => 'gen_from_webhook']);
        $this->sendWebhook($object)->assertJson(['status' => 'processed']);

        $generation->refresh();
        $this->assertSame('gen_from_webhook', $generation->pixelforge_id);
        $this->assertSame(GenerationStatus::Processing, $generation->status);
    }

    public function test_unknown_generations_and_pings_are_acknowledged(): void
    {
        $this->sendWebhook(['id' => 'gen_someone_else', 'status' => 'succeeded'])
            ->assertOk()->assertJson(['status' => 'unknown_generation']);

        $this->sendWebhook(['id' => null, 'status' => 'ping'], type: 'ping')
            ->assertOk()->assertJson(['status' => 'ignored']);
    }

    public function test_if_applying_fails_the_event_is_not_marked_as_seen_so_the_retry_works(): void
    {
        $generation = $this->makeGeneration($this->makeUser());
        // A succeeded payload without usage can't be billed: we refuse it (500) instead of guessing.
        $broken = $this->remoteGeneration($generation, 'succeeded');
        $broken['usage'] = null;

        $this->sendWebhook($broken, eventId: 'evt_retry')->assertStatus(500);
        $this->assertSame(0, WebhookEvent::count());

        // Pixelforge retries the same event (now complete) and it goes through.
        $this->sendWebhook($this->remoteGeneration($generation, 'succeeded'), eventId: 'evt_retry')
            ->assertJson(['status' => 'processed']);
        $this->assertSame(GenerationStatus::Succeeded, $generation->fresh()->status);
    }
}
