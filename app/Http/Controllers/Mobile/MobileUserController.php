<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileUserController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing(['roles', 'permissions', 'location']);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'active' => (bool) $user->active,
                'location_id' => $user->location_id,
                'two_factor' => $user->hasEnabledTwoFactorAuthentication(),
                'roles' => $user->roles->pluck('name')->values()->all(),
                'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            ],
            'mobile_actions' => $this->mobileActions($user),
        ]);
    }

    /**
     * @return list<string>
     */
    private function mobileActions(\App\Models\User $user): array
    {
        $perms = Transaction::getPermissions();
        $actions = [];

        if ($user->can($perms['type-sell']) || $user->can($perms['create'])) {
            $actions[] = 'sell';
        }
        if ($user->can($perms['type-cash-in'])) {
            $actions[] = 'cash_in';
        }
        if ($user->can($perms['type-cash-out'])) {
            $actions[] = 'cash_out';
        }
        if ($user->can($perms['type-transfer'])) {
            $actions[] = 'transfer';
        }
        if ($user->can($perms['type-move'])) {
            $actions[] = 'move';
        }

        return array_values(array_unique($actions));
    }
}
