<?php

namespace Database\Factories;

use App\Models\Webhook;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Webhook> */
class WebhookFactory extends Factory
{
    protected $model = Webhook::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->words(2, true),
            'url' => 'https://hooks.example.com/'.Str::random(8),
            'secret' => 'whsec_'.Str::random(32),
            'triggers' => ['link.created', 'lead.created', 'sale.created'],
        ];
    }
}
