<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SetPasswordRequest;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class SetPasswordController extends Controller
{
    public function store(SetPasswordRequest $request): Response
    {
        $status = Password::broker()->reset(
            $request->only('email', 'token', 'password'),
            function (User $user, string $password) use ($request) {
                $user->forceFill([
                    'name' => $request->validated('name'),
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                ])->save();
            },
        );

        return $status === Password::PASSWORD_RESET
            ? response()->noContent()
            : throw ValidationException::withMessages(['token' => [__($status)]]);
    }
}
