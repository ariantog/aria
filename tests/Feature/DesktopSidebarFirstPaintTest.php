<?php

use App\Models\User;

/**
 * Desktop used to animate the sidebar width on every page load because #sidebar
 * had a CSS transition before Alpine applied w-64/w-14 from localStorage.
 */
beforeEach(function () {
    $this->user = User::factory()->create();

    expect($this->user->is_superadmin)->toBeTrue();
});

it('renders CSS that seeds desktop sidebar width before Alpine hydrates', function () {
    $html = $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('data-sidebar-desktop')
        ->toContain('html[data-sidebar-desktop="open"] #sidebar')
        ->toContain('html[data-sidebar-desktop="collapsed"] #sidebar')
        ->toContain('html[data-sidebar-desktop="open"] #main-content')
        ->toContain('html[data-sidebar-desktop="collapsed"] #main-content');
});

it('gates desktop sidebar width transitions behind anim-ready', function () {
    $html = $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('#sidebar.anim-ready { transition: width 0.2s ease, transform 0.2s ease; }')
        ->not->toContain('#sidebar { transition: width 0.2s ease, transform 0.2s ease; }');
});

it('adds anim-ready to the sidebar after Alpine first paint', function () {
    $html = $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('x-init="$nextTick(() => $el.classList.add(\'anim-ready\'))"')
        ->toContain('id="sidebar"');
});

it('keeps the desktop open preference in sync on the html dataset', function () {
    $html = $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain("document.documentElement.dataset.sidebarDesktop = this.sidebarOpen ? 'open' : 'collapsed'");
});
