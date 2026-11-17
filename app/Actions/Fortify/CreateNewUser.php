<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\CreditTransaction;
use App\Models\User;
use App\Services\Billing\CreditLedger;
use App\Support\Money;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        $welcome = Money::toNanos((string) config('billing.welcome_credit'));

        if ($welcome > 0) {
            app(CreditLedger::class)->credit($user, $welcome, CreditTransaction::TYPE_WELCOME, 'Welcome credit');
        }

        return $user;
    }
}
