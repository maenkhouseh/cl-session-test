<?php

namespace Database\Factories;

use App\Models\Domain;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Domain> */
class DomainFactory extends Factory
{
    protected $model = Domain::class;

    public function definition(): array
    {
        return ['workspace_id' => Workspace::factory(), 'slug' => fake()->unique()->domainName(), 'verified' => true, 'verified_at' => now()];
    }

    public function unverified(): static
    {
        return $this->state(['verified' => false, 'verified_at' => null]);
    }
}
