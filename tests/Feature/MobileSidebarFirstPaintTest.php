<?php

use App\Models\User;

/**
 * The mobile drawer used to start from the desktop localStorage preference
 * (usually open) and then init() set sidebarOpen = false. That painted the
 * sidebar and immediately hid it on every phone page load.
 */
beforeEach(function () {
    $this->user = User::factory()->create();

    expect($this->user->is_superadmin)->toBeTrue();
});

it('renders CSS that keeps the sidebar off-screen on mobile until it is opened', function () {
    $html = $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('@media (max-width: 1023px)')
        ->toContain('#sidebar:not(.is-open)')
        ->toContain('transform: translateX(-100%)');
});

it('initializes the Alpine sidebar closed on mobile instead of hiding it after paint', function () {
    $html = $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('sidebarOpen: isMobileViewport() ? false : savedDesktopOpen()')
        ->toContain("return 'w-64 is-open'")
        ->toContain(':class="sidebarClass()"')
        ->not->toContain("sidebarOpen: localStorage.getItem('sidebarOpen') !== 'false'");
});

it('does not persist the mobile drawer state over the desktop sidebar preference', function () {
    $html = $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('persistSidebarOpen()')
        ->toContain('if (!this.isMobile)')
        ->toContain("localStorage.setItem('sidebarOpen', this.sidebarOpen)")
        ->not->toContain("this.\$watch('sidebarOpen', val => localStorage.setItem('sidebarOpen', val))");
});
