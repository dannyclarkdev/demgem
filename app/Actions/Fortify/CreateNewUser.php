<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Support\Auth\RegistrationGate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(
        private RegistrationGate $gate,
        private Request $request,
    ) {}

    /**
     * Validate and create a newly registered user.
     *
     * A POST with no pending invite on an invite-only install is a script, not a
     * form: a bare 403 and no row, the same answer the register page gave.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        abort_unless($this->gate->allows($this->request), 403, 'demgem is invite only.');

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);
    }
}
