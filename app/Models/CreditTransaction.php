<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property int $amount_nanos
 * @property int $balance_after_nanos
 * @property int|null $generation_id
 * @property string $description
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class CreditTransaction extends Model
{
    public const TYPE_PURCHASE = 'purchase';

    public const TYPE_WELCOME = 'welcome';

    public const TYPE_CHARGE = 'charge';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount_nanos' => 'integer',
            'balance_after_nanos' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Generation, $this> */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }
}
