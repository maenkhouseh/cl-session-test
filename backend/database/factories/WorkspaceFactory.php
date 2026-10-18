<?php

namespace Database\Factories;

use App\Enums\Plan;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Workspace> */
class WorkspaceFactory extends Factory
{
    protected $model = Workspace::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'plan' => Plan::Business,
            'owner_id' => User::factory(),
            'usage_reset_at' => now()->addMonth(),
        ];
    }

    public function plan(Plan $plan): static
    {
        return $this->state(['plan' => $plan]);
    }
}
