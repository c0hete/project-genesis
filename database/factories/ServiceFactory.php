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
            'description' => $this->faker->optional(0.7)->sentence(),
            'duration_minutes' => $this->faker->numberBetween(15, 120),
            'price_cents' => $this->faker->numberBetween(100000, 1000000), // $10-$100 in cents
            'currency' => $this->faker->randomElement(['USD', 'CLP', 'MXN', 'ARS']),
            'is_active' => true,
        ];
    }
}
