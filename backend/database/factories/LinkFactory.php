<?php

namespace Database\Factories;

use App\Models\Link;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Link> */
class LinkFactory extends Factory
{
    protected $model = Link::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'domain' => 'shortie.link',
            'key' => Str::random(7),
            'url' => fake()->url(),
        ];
    }
}
