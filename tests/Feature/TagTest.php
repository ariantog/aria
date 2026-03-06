<?php

use App\Models\Tag;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->user = User::factory()->create();

    $role = Role::create(['name' => 'super-admin']);
    $this->user->assignRole($role);
});

it('can view tags page', function () {
    $response = $this->actingAs($this->user)->get('/tags');

    $response->assertStatus(200);
});

it('can create tag', function () {
    $response = $this->actingAs($this->user)->post('/tags', [
        'name' => 'Test Tag',
        'code' => 'TST',
        'type' => Tag::TYPE_TYPE,
        'item_type' => 0,
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('tags', [
        'name' => 'Test Tag',
        'code' => 'TST',
        'type' => Tag::TYPE_TYPE,
    ]);
});

it('can update tag', function () {
    $tag = Tag::create([
        'name' => 'Old Tag',
        'code' => 'OLD',
        'type' => Tag::TYPE_TYPE,
        'item_type' => 0,
    ]);

    $response = $this->actingAs($this->user)->put("/tags/{$tag->id}", [
        'name' => 'New Tag',
        'code' => 'NEW',
        'type' => Tag::TYPE_SIZE,
        'item_type' => 1,
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('tags', [
        'id' => $tag->id,
        'name' => 'New Tag',
        'code' => 'NEW',
        'type' => Tag::TYPE_SIZE,
        'item_type' => 1,
    ]);
});

it('can delete tag', function () {
    $tag = Tag::create([
        'name' => 'To Delete',
        'code' => 'DEL',
        'type' => Tag::TYPE_TYPE,
        'item_type' => 0,
    ]);

    $response = $this->actingAs($this->user)->delete("/tags/{$tag->id}");

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseMissing('tags', [
        'id' => $tag->id,
    ]);
});
