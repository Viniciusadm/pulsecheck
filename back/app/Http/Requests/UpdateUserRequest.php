<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends StoreUserRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->targetUser())],
            'role' => ['sometimes', 'required', Rule::enum(UserRole::class)],
            'password' => ['nullable', 'string', 'confirmed', Password::defaults()],
        ];
    }

    public function attributesToUpdate(): array
    {
        return collect($this->validated())
            ->reject(fn (mixed $value, string $key) => $key === 'password' && blank($value))
            ->all();
    }

    private function targetUser(): User
    {
        return $this->route('user');
    }
}
