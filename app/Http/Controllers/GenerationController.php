<?php

namespace App\Http\Controllers;

use App\Enums\GenerationStatus;
use App\Http\Requests\StoreGenerationRequest;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Services\Billing\CostCalculator;
use App\Services\Billing\CreditLedger;
use App\Services\Billing\Pricing;
use App\Services\Pixelforge\GenerationPoller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GenerationController extends Controller
{
    /** The "Generate" page: prompt form, live cost estimate, latest results. */
    public function create(Request $request, CreditLedger $ledger): Response
    {
        $user = $request->user();
        $this->pollIfStale($user);

        return Inertia::render('generations/Create', [
            'pricing' => Pricing::current()->toArray(),
            'markup' => (string) config('billing.markup'),
            'availableNanos' => $ledger->available($user),
            'recent' => $user->generations()->latest()->limit(8)->get()
                ->map(fn (Generation $g) => $g->toFrontend()),
        ]);
    }

    /** History of every generation, newest first. */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->pollIfStale($user);

        $generations = $user->generations()->latest()->paginate(12)
            ->through(fn (Generation $g) => $g->toFrontend());

        return Inertia::render('generations/Index', [
            'generations' => $generations,
            'totals' => [
                'count' => $user->generations()->count(),
                'spent_nanos' => (int) $user->generations()->where('status', GenerationStatus::Succeeded)->sum('charged_nanos'),
            ],
        ]);
    }

    public function store(StoreGenerationRequest $request, CostCalculator $calculator, CreditLedger $ledger): RedirectResponse
    {
        $data = $request->validated();
        $references = array_values(array_filter($data['reference_images'] ?? []));

        // Recompute the estimate server-side; the one in the browser is only for display.
        $estimate = $calculator->estimate(Pricing::current(), $data['prompt'], $data['size'], $data['quality'], count($references));

        $generation = DB::transaction(function () use ($request, $data, $references, $estimate, $ledger) {
            // Lock the user so two quick submits can't both spend the same credit.
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();

            if ($ledger->available($user) < $estimate['max_nanos']) {
                throw ValidationException::withMessages([
                    'prompt' => 'Not enough credit for this generation. It can cost up to $'
                        .number_format($estimate['max_nanos'] / 1e9, 4).'. Buy credits to continue.',
                ]);
            }

            return $user->generations()->create([
                'uuid' => (string) Str::uuid(),
                'status' => GenerationStatus::Pending,
                'prompt' => $data['prompt'],
                'size' => $data['size'],
                'quality' => $data['quality'],
                'reference_images' => $references,
                'estimated_min_nanos' => $estimate['min_nanos'],
                'estimated_max_nanos' => $estimate['max_nanos'],
            ]);
        });

        SubmitGeneration::dispatch($generation);

        return back();
    }

    /**
     * Webhooks do the real work; this just covers a host where the scheduler
     * isn't running. At most once every 20s per user, only for stale rows.
     */
    private function pollIfStale(User $user): void
    {
        if (! $user->generations()->active()->whereNotNull('pixelforge_id')->exists()) {
            return;
        }

        if (Cache::add("poll-stale:{$user->id}", true, 20)) {
            app(GenerationPoller::class)->pollStale(olderThanSeconds: 45, userId: $user->id, limit: 3);
        }
    }
}
