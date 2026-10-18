<?php

namespace Database\Factories;

use App\Models\Folder;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Folder> */
class FolderFactory extends Factory
{
    protected $model = Folder::class;

    public function definition(): array
    {
        return ['workspace_id' => Workspace::factory(), 'name' => fake()->unique()->words(2, true), 'access_level' => 'write'];
    }
}
