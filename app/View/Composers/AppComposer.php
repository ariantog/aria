<?php

namespace App\View\Composers;

use App\Models\Addrbook;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AppComposer
{
    public function compose(View $view): void
    {
        $user = Auth::user();
        if (! $user) return;

        // Share flash for the layout's flash block, unless a controller already
        // passed its own $flash (do not override explicit values).
        if (! array_key_exists('flash', $view->getData())) {
            $view->with('flash', [
                'success' => session('success'),
                'error'   => session('error'),
            ]);
        }

        $view->with('_sidebar', [
            'user'          => $user,
            'permissions'   => $user->getAllPermissions()->pluck('name')->toArray(),
            'roles'         => $user->getRoleNames()->toArray(),
            'addrbook_types' => collect(Addrbook::getTypes())->map(function ($type) {
                $type['permission'] = Addrbook::getPermissions($type['slug'])['view'];
                return $type;
            })->toArray(),
            'flash' => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }
}
