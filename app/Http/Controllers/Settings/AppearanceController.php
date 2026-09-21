<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\UserPreferenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class AppearanceController extends Controller
{
    public function __construct(
        protected UserPreferenceService $preferences,
    ) {}

    public function edit(Request $request): View
    {
        return view('settings.appearance', [
            'appearance' => $this->preferences->appearanceFor($request->user()),
            'fontSize' => $this->preferences->fontSizeFor($request->user()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'appearance' => ['required', 'in:light,dark,system'],
            'font_size' => ['required', 'in:small,default,large'],
        ]);

        try {
            $this->preferences->setAppearance($request->user(), $validated['appearance']);
            $this->preferences->setFontSize($request->user(), $validated['font_size']);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('appearance.edit')
            ->with('success', 'Appearance saved.')
            ->withCookie(cookie('appearance', $validated['appearance'], 60 * 24 * 365))
            ->withCookie(cookie('font_size', $validated['font_size'], 60 * 24 * 365));
    }
}
