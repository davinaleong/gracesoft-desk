<?php

use App\Models\Category;
use App\Models\Service;
use App\Models\User;
use App\Models\Vendor;

function readyUserForCategories(): User
{
    return User::factory()->create([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'two_factor_confirmed_at' => now(),
    ]);
}

test('categories index lists vendor and service categories separately', function () {
    $user = readyUserForCategories();
    Category::factory()->vendor()->create(['name' => 'Consulting']);
    Category::factory()->service()->create(['name' => 'Monitoring']);

    $response = $this->actingAs($user)->get(route('settings.categories.index'));

    $response->assertOk()->assertSee('Consulting')->assertSee('Monitoring');
});

test('vendor category can be created', function () {
    $user = readyUserForCategories();

    $response = $this->actingAs($user)->post(route('settings.categories.store'), [
        'type' => 'vendor',
        'name' => 'Consulting',
        'status' => 'active',
    ]);

    $response->assertRedirect(route('settings.categories.index'));
    $category = Category::query()->where('name', 'Consulting')->firstOrFail();
    expect($category->type)->toBe('vendor');
});

test('service category can be created', function () {
    $user = readyUserForCategories();

    $this->actingAs($user)->post(route('settings.categories.store'), [
        'type' => 'service',
        'name' => 'Monitoring',
        'status' => 'active',
    ])->assertRedirect(route('settings.categories.index'));

    $category = Category::query()->where('name', 'Monitoring')->firstOrFail();
    expect($category->type)->toBe('service');
});

test('same name is allowed across different types but not within the same type', function () {
    $user = readyUserForCategories();
    Category::factory()->vendor()->create(['name' => 'Shared Name']);

    $this->actingAs($user)->post(route('settings.categories.store'), [
        'type' => 'service',
        'name' => 'Shared Name',
        'status' => 'active',
    ])->assertSessionDoesntHaveErrors('name');

    $this->actingAs($user)->post(route('settings.categories.store'), [
        'type' => 'vendor',
        'name' => 'Shared Name',
        'status' => 'active',
    ])->assertSessionHasErrors('name');
});

test('category can be updated', function () {
    $user = readyUserForCategories();
    $category = Category::factory()->vendor()->create(['name' => 'Old Name']);

    $this->actingAs($user)->put(route('settings.categories.update', $category), [
        'name' => 'New Name',
        'status' => 'inactive',
    ])->assertRedirect(route('settings.categories.index'));

    $category->refresh();
    expect($category->name)->toBe('New Name')->and($category->status)->toBe('inactive');
});

test('unused category can be deleted', function () {
    $user = readyUserForCategories();
    $category = Category::factory()->vendor()->create();

    $this->actingAs($user)->delete(route('settings.categories.destroy', $category))
        ->assertRedirect(route('settings.categories.index'));

    expect(Category::query()->find($category->id))->toBeNull();
});

test('category in use by a vendor cannot be deleted', function () {
    $user = readyUserForCategories();
    $category = Category::factory()->vendor()->create();
    Vendor::factory()->create(['category_id' => $category->id]);

    $this->actingAs($user)->delete(route('settings.categories.destroy', $category));

    expect(Category::query()->find($category->id))->not->toBeNull();
});

test('category in use by a service cannot be deleted', function () {
    $user = readyUserForCategories();
    $category = Category::factory()->service()->create();
    Service::factory()->create(['category_id' => $category->id]);

    $this->actingAs($user)->delete(route('settings.categories.destroy', $category));

    expect(Category::query()->find($category->id))->not->toBeNull();
});

test('category routes resolve via uuid not id', function () {
    $user = readyUserForCategories();
    $category = Category::factory()->vendor()->create();

    $this->actingAs($user)->get(route('settings.categories.edit', $category))->assertOk();
    $this->actingAs($user)->get('/settings/categories/'.$category->id.'/edit')->assertNotFound();
});

test('categories require authentication', function () {
    $this->get(route('settings.categories.index'))->assertRedirect(route('login'));
});
