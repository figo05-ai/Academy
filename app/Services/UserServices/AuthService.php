<?php

namespace App\Services\UserServices;
use App\Models\User;

class AuthService
{
public function handleGoogleCallback($googleUser)
{
    $user = User::where('email', $googleUser->email)->first();

    if (!$user) {
        $user = User::create([
            'name' => $googleUser->name,
            'email' => $googleUser->email,
            'google_id' => $googleUser->id,
            'password' => null
        ]);
    } else {
        $user->update(['google_id' => $googleUser->id]);
    }

    return $user;
}
}
