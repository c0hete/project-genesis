<?php

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it can create a service', function () {
    $this->withoutExceptionHandling();
    $service = Service::factory()->create([
        'name' => 'Test Service',
        'price_amount' => 5000,
    ]);

    expect($service)
        ->name->toBe('Test Service')
        ->price_amount->toBe(5000);
    
    $this->assertDatabaseHas('services', [
        'id' => $service->id,
        'name' => 'Test Service',
    ]);
});

test('it formats price correctly', function () {
    $service = Service::factory()->make([
        'price_amount' => 1250, // 12.50
    ]);

    expect($service->price)->toBe('12.50');

    $service->price_amount = 1000; // 10.00
    expect($service->price)->toBe('10.00');
});
