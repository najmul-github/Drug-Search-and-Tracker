<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuthService
{
    public function register(array $data): ?User
    {
        try {
            return DB::transaction(function () use ($data) {
                return User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                ]);
            });
        } catch (Throwable $e) {
            Log::error('AuthService register failed', ['data' => $data, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function login(string $email, string $password): ?User
    {
        try {
            $user = User::where('email', $email)->first();
            if (!$user || !Hash::check($password, $user->password)) return null;
            return $user;
        } catch (Throwable $e) {
            Log::error('AuthService login failed', ['email'=>$email, 'error'=>$e->getMessage()]);
            return null;
        }
    }

    public function logout(User $user): bool
    {
        try {
            return (bool) $user->currentAccessToken()?->delete();
        } catch (Throwable $e) {
            Log::warning('AuthService logout failed', ['user_id'=>$user->id, 'error'=>$e->getMessage()]);
            return false;
        }
    }
}
