<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Pet;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $dogs = Category::create(['name' => 'Dogs', 'slug' => 'dogs']);
        Category::create(['name' => 'Cats', 'slug' => 'cats']);

        Pet::create(['name' => 'Rex', 'weight' => 12.0, 'owner_id' => 1, 'category_id' => $dogs->id, 'tags' => ['friendly']]);
        Pet::create(['name' => 'Mia', 'weight' => 4.5, 'owner_id' => 2]);
    }
}
