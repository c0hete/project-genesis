<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Service ' . $this->faker->numberBetween(1, 100000),
            'slug' => 'service-' . $this->faker->unique()->numberBetween(1, 100000),
            'duration_minutes' => $this->faker->numberBetween(15, 120),
            'price_amount' => $this->faker->numberBetween(1000, 10000),
            'price_currency' => 'USD',
            'is_active' => true,
        ];
    }
}
