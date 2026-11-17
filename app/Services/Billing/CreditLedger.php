<?php

namespace App\Services\Billing;

use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Every balance change goes through here: the user's row is locked, the
 * balance updated, and a ledger row written in the same transaction.
 */
class CreditLedger
{
    public function credit(User $user, int $nanos, string $type, string $description): CreditTransaction
    {
        return DB::transaction(function () use ($user, $nanos, $type, $description) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $locked->balance_nanos += $nanos;
            $locked->save();

            return $locked->creditTransactions()->create([
                'type' => $type,
                'amount_nanos' => $nanos,
                'balance_after_nanos' => $locked->balance_nanos,
                'description' => $description,
            ]);
        });
    }

    /**
     * Charge the user for a succeeded generation. Safe to call more than once:
     * the existing charge is returned and the unique index on generation_id
     * backs that up at the database level.
     */
    public function chargeFor(Generation $generation): CreditTransaction
    {
        return DB::transaction(function () use ($generation) {
            $existing = CreditTransaction::where('generation_id', $generation->id)->first();

            if ($existing) {
                return $existing;
            }

            $locked = User::whereKey($generation->user_id)->lockForUpdate()->firstOrFail();
            $locked->balance_nanos -= $generation->charged_nanos;
            $locked->save();

            return $locked->creditTransactions()->create([
                'type' => CreditTransaction::TYPE_CHARGE,
                'amount_nanos' => -$generation->charged_nanos,
                'balance_after_nanos' => $locked->balance_nanos,
                'generation_id' => $generation->id,
                'description' => 'Image generation ('.$generation->quality.', '.$generation->size.')',
            ]);
        });
    }

    /**
     * Balance minus the worst-case cost of generations still in flight.
     * Call inside a transaction holding the user's lock to make it race-free.
     */
    public function available(User $user): int
    {
        $reserved = (int) $user->generations()->active()->sum('estimated_max_nanos');

        return $user->balance_nanos - $reserved;
    }
}
