<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\Rol;
use App\Models\Escuela;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user: the school and its admin, together.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'escuela' => ['required', 'string', 'max:120'],
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ], attributes: ['escuela' => 'nombre de la escuela'])->validate();

        return DB::transaction(function () use ($input): User {
            $escuela = Escuela::create(['nombre' => $input['escuela']]);

            $user = new User([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);
            $user->escuela()->associate($escuela);
            $user->rol = Rol::Admin;
            $user->save();

            return $user;
        });
    }
}
