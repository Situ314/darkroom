<?php

namespace App\Http\Controllers;

use App\Models\CreditTransaction;
use App\Services\Billing\CreditLedger;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CreditController extends Controller
{
    public function index(Request $request, CreditLedger $ledger): Response
    {
        $user = $request->user();

        return Inertia::render('credits/Index', [
            'balanceNanos' => $user->balance_nanos,
            'availableNanos' => $ledger->available($user),
            'packs' => array_map(fn (string $usd) => [
                'usd' => $usd,
                'nanos' => Money::toNanos($usd),
            ], $this->packs()),
            'transactions' => $user->creditTransactions()->latest('id')->paginate(15)
                ->through(fn (CreditTransaction $t) => [
                    'id' => $t->id,
                    'type' => $t->type,
                    'description' => $t->description,
                    'amount_nanos' => $t->amount_nanos,
                    'balance_after_nanos' => $t->balance_after_nanos,
                    'created_at' => $t->created_at->toIso8601String(),
                ]),
        ]);
    }

    /** @return list<string> */
    private function packs(): array
    {
        return array_values(array_map('strval', (array) config('billing.packs')));
    }

    /** Fake purchase: no payment provider, credit is simply added. */
    public function store(Request $request, CreditLedger $ledger): RedirectResponse
    {
        $data = $request->validate([
            'usd' => ['required', 'string', Rule::in($this->packs())],
        ]);

        $ledger->credit($request->user(), Money::toNanos($data['usd']), CreditTransaction::TYPE_PURCHASE, "Bought \${$data['usd']} of credits");

        Inertia::flash('toast', ['type' => 'success', 'message' => "Added \${$data['usd']} to your balance."]);

        return back();
    }
}
