<?php

use App\Models\User;
use App\Models\Vendor;

function readyUserForVendors(): User
{
    return User::factory()->create([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'two_factor_confirmed_at' => now(),
    ]);
}

test('vendors index is accessible', function () {
    $user = readyUserForVendors();
    Vendor::factory()->create(['name' => 'Test Corp', 'category' => 'cloud']);

    $response = $this->actingAs($user)->get(route('vendors.index'));

    $response->assertOk()->assertSee('VND-');
});

test('vendor can be created with generated vendor code', function () {
    $user = readyUserForVendors();

    $response = $this->actingAs($user)->post(route('vendors.store'), [
        'name' => 'Acme Ltd',
        'category' => 'saas',
        'website' => 'https://acme.example.com',
        'status' => 'active',
    ]);

    $vendor = Vendor::query()->where('name', 'Acme Ltd')->firstOrFail();
    $response->assertRedirect(route('vendors.show', $vendor));
    expect($vendor->vendor_code)->toStartWith('VND-');
    expect($vendor->uuid)->not->toBeEmpty();
});

test('vendor show page displays vendor code', function () {
    $user = readyUserForVendors();
    $vendor = Vendor::factory()->create();

    $response = $this->actingAs($user)->get(route('vendors.show', $vendor));

    $response->assertOk()->assertSee($vendor->vendor_code);
});

test('vendor can be updated', function () {
    $user = readyUserForVendors();
    $vendor = Vendor::factory()->create(['name' => 'Old Name']);

    $this->actingAs($user)->put(route('vendors.update', $vendor), [
        'name' => 'New Name',
        'category' => $vendor->category,
        'status' => $vendor->status,
    ]);

    expect($vendor->fresh()->name)->toBe('New Name');
});

test('vendor cannot be deleted with active services', function () {
    $user = readyUserForVendors();
    $vendor = Vendor::factory()->create();
    $vendor->services()->create([
        'name' => 'Active Service',
        'category' => 'storage',
        'status' => 'active',
    ]);

    $response = $this->actingAs($user)->delete(route('vendors.destroy', $vendor));

    $response->assertRedirect(route('vendors.show', $vendor));
    expect(Vendor::query()->find($vendor->id))->not->toBeNull();
});

test('vendor can be soft deleted when no active services', function () {
    $user = readyUserForVendors();
    $vendor = Vendor::factory()->create();
    $vendor->services()->create([
        'name' => 'Cancelled Service',
        'category' => 'storage',
        'status' => 'cancelled',
    ]);

    $response = $this->actingAs($user)->delete(route('vendors.destroy', $vendor));

    $response->assertRedirect(route('vendors.index'));
    expect(Vendor::withTrashed()->find($vendor->id)->deleted_at)->not->toBeNull();
});

test('vendor routes resolve via uuid not id', function () {
    $user = readyUserForVendors();
    $vendor = Vendor::factory()->create();

    $this->actingAs($user)->get('/vendors/'.$vendor->uuid)->assertOk();
    $this->actingAs($user)->get('/vendors/'.$vendor->id)->assertNotFound();
});

test('vendor index filters by status', function () {
    $user = readyUserForVendors();
    Vendor::factory()->create(['name' => 'Active Vendor', 'status' => 'active']);
    Vendor::factory()->inactive()->create(['name' => 'Inactive Vendor']);

    $response = $this->actingAs($user)->get(route('vendors.index', ['status' => 'active']));

    $response->assertOk()->assertSee('Active Vendor')->assertDontSee('Inactive Vendor');
});
