<?php

namespace Database\Factories;

use App\Enums\PetStatus;
use App\Models\Pet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pet>
 */
class PetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'status' => fake()->randomElement(PetStatus::cases()),
            'weight' => fake()->randomFloat(2, 1, 60),
            'owner_id' => fake()->numberBetween(1, 100),
            'tags' => [],
        ];
    }
}
