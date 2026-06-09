<?php

use App\Models\Service;
use App\Models\User;
use App\Models\Vendor;

function readyUserForServices(): User
{
    return User::factory()->create([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'two_factor_confirmed_at' => now(),
    ]);
}

test('services index is accessible', function () {
    $user = readyUserForServices();
    $vendor = Vendor::factory()->create();
    Service::factory()->for($vendor)->create();

    $response = $this->actingAs($user)->get(route('services.index'));

    $response->assertOk()->assertSee('SVC-');
});

test('service can be created with generated service code', function () {
    $user = readyUserForServices();
    $vendor = Vendor::factory()->create();

    $response = $this->actingAs($user)->post(route('services.store'), [
        'vendor_uuid' => $vendor->uuid,
        'name' => 'Cloud Storage',
        'plan' => 'Pro',
        'category' => 'storage',
        'status' => 'active',
    ]);

    $service = Service::query()->where('name', 'Cloud Storage')->firstOrFail();
    $response->assertRedirect(route('services.show', $service));
    expect($service->service_code)->toStartWith('SVC-');
    expect($service->uuid)->not->toBeEmpty();
    expect($service->vendor_id)->toBe($vendor->id);
});

test('service show page displays service code and vendor link', function () {
    $user = readyUserForServices();
    $vendor = Vendor::factory()->create(['name' => 'Linked Vendor']);
    $service = Service::factory()->for($vendor)->create();

    $response = $this->actingAs($user)->get(route('services.show', $service));

    $response->assertOk()
        ->assertSee($service->service_code)
        ->assertSee('Linked Vendor');
});

test('service can be updated', function () {
    $user = readyUserForServices();
    $vendor = Vendor::factory()->create();
    $service = Service::factory()->for($vendor)->create(['name' => 'Old Service']);

    $this->actingAs($user)->put(route('services.update', $service), [
        'vendor_uuid' => $vendor->uuid,
        'name' => 'New Service',
        'category' => $service->category,
        'status' => $service->status,
    ]);

    expect($service->fresh()->name)->toBe('New Service');
});

test('service can be soft deleted', function () {
    $user = readyUserForServices();
    $vendor = Vendor::factory()->create();
    $service = Service::factory()->for($vendor)->create();

    $response = $this->actingAs($user)->delete(route('services.destroy', $service));

    $response->assertRedirect(route('services.index'));
    expect(Service::withTrashed()->find($service->id)->deleted_at)->not->toBeNull();
});

test('service routes resolve via uuid not id', function () {
    $user = readyUserForServices();
    $vendor = Vendor::factory()->create();
    $service = Service::factory()->for($vendor)->create();

    $this->actingAs($user)->get('/services/'.$service->uuid)->assertOk();
    $this->actingAs($user)->get('/services/'.$service->id)->assertNotFound();
});

test('services index filters by vendor', function () {
    $user = readyUserForServices();
    $vendorA = Vendor::factory()->create(['name' => 'Vendor A']);
    $vendorB = Vendor::factory()->create(['name' => 'Vendor B']);
    Service::factory()->for($vendorA)->create(['name' => 'Service A']);
    Service::factory()->for($vendorB)->create(['name' => 'Service B']);

    $response = $this->actingAs($user)->get(route('services.index', ['vendor_uuid' => $vendorA->uuid]));

    $response->assertOk()->assertSee('Service A')->assertDontSee('Service B');
});

test('vendor show lists its services', function () {
    $user = readyUserForServices();
    $vendor = Vendor::factory()->create();
    Service::factory()->for($vendor)->create(['name' => 'My Service']);

    $response = $this->actingAs($user)->get(route('vendors.show', $vendor));

    $response->assertOk()->assertSee('My Service');
});
