<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

class MobileAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'two_factor_code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var User|null $user */
        $user = User::query()->where('username', mb_strtolower($validated['username']))->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'username' => [__('auth.failed')],
            ]);
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            if ($request->filled('recovery_code')) {
                $recovery = $request->input('recovery_code');
                $match = collect($user->recoveryCodes())->first(fn ($code) => hash_equals((string) $code, (string) $recovery));
                if (! $match) {
                    throw ValidationException::withMessages(['recovery_code' => [__('The provided two factor recovery code was invalid.')]]);
                }
                $user->replaceRecoveryCode($match);
            } elseif ($request->filled('two_factor_code')) {
                $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);
                $ok = app(TwoFactorAuthenticationProvider::class)->verify($secret, (string) $request->input('two_factor_code'));
                if (! $ok) {
                    throw ValidationException::withMessages(['two_factor_code' => [__('The provided two factor authentication code was invalid.')]]);
                }
            } else {
                return response()->json([
                    'two_factor' => true,
                    'message' => 'Two-factor authentication is required.',
                ], 428);
            }
        }

        if (! $user->active) {
            return response()->json(['message' => 'Account is not active.'], 403);
        }

        $deviceName = $validated['device_name'] ?? 'flutter-mobile';
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        $user->loadMissing(['roles', 'permissions', 'location']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'active' => (bool) $user->active,
            'location_id' => $user->location_id,
            'two_factor' => $user->hasEnabledTwoFactorAuthentication(),
            'roles' => $user->roles->pluck('name')->values()->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
        ];
    }
}
