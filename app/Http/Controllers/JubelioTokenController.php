<?php

namespace App\Http\Controllers;

use App\Models\Jubelio;
use App\Services\JubelioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class JubelioTokenController extends Controller
{
    public function index(JubelioService $jubelioService): View
    {
        Gate::authorize(Jubelio::getPermissions()['view']);

        return view('jubelio.token.index', [
            'status' => $jubelioService->getConnectionStatus(),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function refresh(JubelioService $jubelioService): RedirectResponse
    {
        Gate::authorize(Jubelio::getPermissions()['view']);

        $token = $jubelioService->refreshToken();

        if (! $token) {
            $status = $jubelioService->getConnectionStatus();

            return redirect()->route('jubelio.token.index')->with(
                'error',
                $status['last_auth_error'] ?? 'Gagal refresh token Jubelio. Periksa kredensial di .env atau status Jubelio.'
            );
        }

        return redirect()->route('jubelio.token.index')->with('success', 'Token Jubelio berhasil di-refresh.');
    }

    public function check(JubelioService $jubelioService): RedirectResponse
    {
        Gate::authorize(Jubelio::getPermissions()['view']);

        $result = $jubelioService->checkConnection();

        return redirect()->route('jubelio.token.index')->with($result['ok'] ? 'success' : 'error', $result['message']);
    }
}
