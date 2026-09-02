<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    expect($this->user->is_superadmin)->toBeTrue();
});

it('renders cash in and cash out with Enter navigation hooks', function (string $path) {
    $html = $this->actingAs($this->user)
        ->get($path)
        ->assertOk()
        ->assertSee('data-testid="cash-entry-row"', false)
        ->assertSee('cash-entry-invoice-', false)
        ->assertSee('cash-entry-note-', false)
        ->assertSee('cash-entry-total-', false)
        ->assertSee('rowTotalFilled', false)
        ->assertSee('isConfirmedEnterKey', false)
        ->assertSee('claimEnterFieldNavigation', false)
        ->assertSee('enterFieldNavClaimMs', false)
        ->assertSee('if (!this.rowTotalFilled(this.form.items[idx])) return true;', false)
        ->getContent();

    expect($html)
        ->toContain('_queueFieldFocus')
        ->toContain('Do not reset _fieldKeyHandled on every keydown')
        ->not->toContain('this._fieldKeyHandled = false;'."\n            if (this._processFieldKey(idx, field, e)) {");
})->with([
    '/transactions/cash-in',
    '/transactions/cash-out',
]);
