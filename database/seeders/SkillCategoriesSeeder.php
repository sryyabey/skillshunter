<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SkillCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Software & Development', 'Content & Editorial', 'Data & Research', 'Product & Design'] as $name) {
            Category::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
        }
    }
}
