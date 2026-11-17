<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GenerationStatus;
use App\Http\Controllers\Controller;
use App\Models\Generation;
use App\Services\Pixelforge\PixelforgeClient;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Spend, provider cost and profit per day and per user.
 *
 * Days are the UTC day a generation *completed*, which is how Pixelforge bills
 * in GET /v1/usage, so the "provider cost" column can be compared line by line.
 */
class ReportController extends Controller
{
    public function __invoke(Request $request, PixelforgeClient $client): Response
    {
        Gate::authorize('view-admin');

        $days = min(92, max(1, (int) $request->integer('days', 30)));
        $end = CarbonImmutable::now('UTC')->startOfDay();
        $start = $end->subDays($days - 1);

        $succeeded = Generation::query()
            ->where('status', GenerationStatus::Succeeded)
            ->whereBetween('completed_at', [$start, $end->endOfDay()]);

        $ours = (clone $succeeded)->toBase()
            ->selectRaw('DATE(completed_at) as day, COUNT(*) as generations, SUM(charged_nanos) as charged, SUM(provider_cost_nanos) as provider')
            ->groupBy('day')
            ->get()
            ->keyBy(fn ($row) => (string) $row->day);

        $pixelforge = $this->pixelforgeUsage($client, $start->toDateString(), $end->toDateString());

        $daily = [];
        for ($day = $end; $day->gte($start); $day = $day->subDay()) {
            $date = $day->toDateString();
            $row = $ours->get($date);
            $charged = (int) ($row->charged ?? 0);
            $provider = (int) ($row->provider ?? 0);

            $daily[] = [
                'date' => $date,
                'generations' => (int) ($row->generations ?? 0),
                'charged_nanos' => $charged,
                'provider_nanos' => $provider,
                'profit_nanos' => $charged - $provider,
                'pixelforge_nanos' => $pixelforge['days'][$date]['cost'] ?? null,
                'pixelforge_generations' => $pixelforge['days'][$date]['generations'] ?? null,
            ];
        }

        $perUser = (clone $succeeded)->toBase()
            ->join('users', 'users.id', '=', 'generations.user_id')
            ->select('users.id', 'users.name', 'users.email', 'users.balance_nanos')
            ->selectRaw('COUNT(*) as generations, SUM(charged_nanos) as charged, SUM(provider_cost_nanos) as provider')
            ->groupBy('users.id', 'users.name', 'users.email', 'users.balance_nanos')
            ->orderByDesc(DB::raw('SUM(charged_nanos)'))
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'email' => $row->email,
                'balance_nanos' => (int) $row->balance_nanos,
                'generations' => (int) $row->generations,
                'charged_nanos' => (int) $row->charged,
                'provider_nanos' => (int) $row->provider,
                'profit_nanos' => (int) $row->charged - (int) $row->provider,
            ]);

        $totals = [
            'generations' => array_sum(array_column($daily, 'generations')),
            'charged_nanos' => array_sum(array_column($daily, 'charged_nanos')),
            'provider_nanos' => array_sum(array_column($daily, 'provider_nanos')),
            'pixelforge_nanos' => $pixelforge['total'],
        ];
        $totals['profit_nanos'] = $totals['charged_nanos'] - $totals['provider_nanos'];

        return Inertia::render('admin/Report', [
            'days' => $days,
            'range' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            'daily' => $daily,
            'perUser' => $perUser,
            'totals' => $totals,
            'pixelforgeError' => $pixelforge['error'],
        ]);
    }

    /**
     * GET /v1/usage, converted to nano-dollars. Cached briefly to stay well inside rate limits.
     *
     * @return array{days: array<string, array{cost: int, generations: int}>, total: int|null, error: string|null}
     */
    private function pixelforgeUsage(PixelforgeClient $client, string $start, string $end): array
    {
        return Cache::remember("pixelforge.usage.{$start}.{$end}", 60, function () use ($client, $start, $end) {
            try {
                $response = $client->usage($start, $end);
            } catch (Throwable $e) {
                return ['days' => [], 'total' => null, 'error' => $e->getMessage()];
            }

            if (! $response->successful()) {
                return ['days' => [], 'total' => null, 'error' => (string) $response->json('error.message', 'HTTP '.$response->status())];
            }

            $days = [];
            foreach ((array) $response->json('data', []) as $day) {
                $days[$day['date']] = [
                    'cost' => Money::toNanos($day['cost_usd']),
                    'generations' => (int) $day['generations'],
                ];
            }

            return [
                'days' => $days,
                'total' => Money::toNanos($response->json('total.cost_usd', '0')),
                'error' => null,
            ];
        });
    }
}
