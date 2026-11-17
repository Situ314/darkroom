<?php

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Models\CreditTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\MakesPixelforgePayloads;
use Tests\TestCase;

class CreditsAndReportTest extends TestCase
{
    use MakesPixelforgePayloads, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPixelforge();
    }

    public function test_new_users_get_a_welcome_credit(): void
    {
        config(['billing.welcome_credit' => '1.00']);

        $this->post('/register', [
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'ada@example.com')->sole();
        $this->assertSame(1_000_000_000, $user->balance_nanos);
        $this->assertSame(CreditTransaction::TYPE_WELCOME, $user->creditTransactions()->sole()->type);
    }

    public function test_buying_credits_adds_to_the_balance(): void
    {
        $user = $this->makeUser('0.00');

        $this->actingAs($user)->post('/credits', ['usd' => '10.00'])->assertRedirect();

        $this->assertSame(10_000_000_000, $user->fresh()->balance_nanos);
        $this->assertSame(10_000_000_000, $user->creditTransactions()->sole()->balance_after_nanos);
    }

    public function test_only_offered_packs_can_be_bought(): void
    {
        $user = $this->makeUser('0.00');

        $this->actingAs($user)->post('/credits', ['usd' => '1000000'])->assertSessionHasErrors('usd');
        $this->assertSame(0, $user->fresh()->balance_nanos);
    }

    public function test_the_admin_report_is_for_admins_only(): void
    {
        $this->actingAs($this->makeUser())->get('/admin/report')->assertForbidden();
    }

    public function test_admins_can_be_granted_by_email_in_config(): void
    {
        $user = $this->makeUser();
        config(['billing.admin_emails' => [strtolower($user->email)]]);

        Http::fake(['*' => Http::response(['data' => [], 'total' => ['cost_usd' => '0']])]);

        $this->actingAs($user)->get('/admin/report')->assertOk();
    }

    public function test_the_admin_report_totals_spend_cost_and_profit_and_compares_with_pixelforge(): void
    {
        $admin = $this->makeUser();
        $admin->forceFill(['is_admin' => true])->save();

        $customer = $this->makeUser('10.00');
        foreach ([1, 2] as $i) {
            $generation = $this->makeGeneration($customer);
            $this->sendWebhook($this->remoteGeneration($generation, 'succeeded'));
        }
        // A failed one must not show up as cost.
        $this->sendWebhook($this->remoteGeneration($this->makeGeneration($customer), 'failed'));

        $today = now('UTC')->toDateString();
        Http::fake(['*/usage*' => Http::response([
            'object' => 'usage',
            'data' => [[
                'date' => $today, 'generations' => 2, 'input_text_tokens' => 12, 'input_image_tokens' => 0,
                'output_tokens' => 2200, 'reasoning_tokens' => 3000, 'cost_usd' => '0.125560000',
            ]],
            'total' => ['generations' => 2, 'cost_usd' => '0.125560000'],
        ])]);

        $this->actingAs($admin)->get('/admin/report')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/Report')
                ->where('totals.generations', 2)
                ->where('totals.provider_nanos', 125_560_000)
                ->where('totals.charged_nanos', 156_950_000)
                ->where('totals.profit_nanos', 31_390_000)
                ->where('totals.pixelforge_nanos', 125_560_000)
                ->where('daily.0.date', $today)
                ->where('daily.0.pixelforge_nanos', 125_560_000)
                ->where('perUser.0.email', $customer->email)
                ->where('perUser.0.generations', 2));

        $this->assertSame(GenerationStatus::Succeeded, $customer->generations()->first()->status);
    }
}
