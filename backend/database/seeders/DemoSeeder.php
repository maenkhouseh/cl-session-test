<?php

namespace Database\Seeders;

use App\Enums\Plan;
use App\Enums\WorkspaceRole;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\Folder;
use App\Models\Link;
use App\Models\Tag;
use App\Models\User;
use App\Models\Workspace;
use App\Services\EventPartitions;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Realistic demo data for local development: `php artisan db:seed --class=DemoSeeder`. Login: demo@shortie.test / password */
class DemoSeeder extends Seeder
{
    private const COUNTRIES = ['US' => 'NA', 'GB' => 'EU', 'DE' => 'EU', 'CA' => 'NA', 'IN' => 'AS', 'FR' => 'EU', 'BR' => 'SA', 'AU' => 'OC', 'JP' => 'AS'];

    private const CITIES = ['US' => ['New York', 'San Francisco', 'Austin'], 'GB' => ['London', 'Manchester'], 'DE' => ['Berlin', 'Munich'], 'CA' => ['Toronto', 'Vancouver'], 'IN' => ['Bengaluru', 'Mumbai'], 'FR' => ['Paris'], 'BR' => ['São Paulo'], 'AU' => ['Sydney'], 'JP' => ['Tokyo']];

    private const REFERERS = ['(direct)' => 40, 'google.com' => 25, 'twitter.com' => 12, 'linkedin.com' => 10, 'news.ycombinator.com' => 6, 'facebook.com' => 5, 'reddit.com' => 2];

    private const DEVICES = [['Desktop', 'Chrome', 'Mac'], ['Desktop', 'Chrome', 'Windows'], ['Desktop', 'Safari', 'Mac'], ['Mobile', 'Mobile Safari', 'iOS'], ['Mobile', 'Chrome Mobile', 'Android'], ['Desktop', 'Firefox', 'Linux'], ['Tablet', 'Mobile Safari', 'iPadOS']];

    public function run(): void
    {
        mt_srand(42);
        foreach (['click_events', 'lead_events', 'sale_events'] as $table) {
            app(EventPartitions::class)->ensure($table, 3, now()->subMonths(4));
        }

        $user = User::firstOrCreate(['email' => 'demo@shortie.test'], ['name' => 'Demo User', 'password' => 'password']);
        $workspace = Workspace::firstOrCreate(['slug' => 'acme'], ['name' => 'Acme Inc.', 'plan' => Plan::Business, 'owner_id' => $user->id, 'usage_reset_at' => now()->addMonth()]);
        $workspace->users()->syncWithoutDetaching([$user->id => ['role' => WorkspaceRole::Owner->value]]);

        Domain::firstOrCreate(['slug' => 'go.acme.test'], ['workspace_id' => $workspace->id, 'verified' => true, 'verified_at' => now(), 'is_primary' => true]);
        $tags = collect(['Launch' => 'red', 'Blog' => 'blue', 'Newsletter' => 'green', 'Social' => 'purple', 'Partners' => 'yellow'])
            ->map(fn ($color, $name) => Tag::firstOrCreate(['workspace_id' => $workspace->id, 'name' => $name], ['color' => $color]));
        $folders = collect(['Marketing', 'Product', 'Docs'])->map(fn ($n) => Folder::firstOrCreate(['workspace_id' => $workspace->id, 'name' => $n]));

        $links = $this->links($workspace, $user, $tags, $folders);
        $this->events($workspace, $links);
    }

    private function links(Workspace $workspace, User $user, $tags, $folders)
    {
        $targets = ['https://acme.test/pricing', 'https://acme.test/blog/launch', 'https://acme.test/docs/start', 'https://acme.test/signup', 'https://acme.test/demo', 'https://acme.test/changelog', 'https://acme.test/careers', 'https://acme.test/partners'];

        return collect(range(1, 24))->map(function ($i) use ($workspace, $user, $tags, $folders, $targets) {
            $link = Link::create([
                'workspace_id' => $workspace->id, 'user_id' => $user->id, 'domain' => $i % 3 === 0 ? 'go.acme.test' : 'shortie.link',
                'key' => Str::lower(Str::random(6)), 'url' => $targets[$i % count($targets)].'?utm_source=demo&utm_medium=link&utm_campaign=c'.$i,
                'utm_source' => 'demo', 'utm_medium' => 'link', 'utm_campaign' => "c{$i}",
                'title' => ['Pricing', 'Launch post', 'Docs', 'Signup', 'Book a demo', 'Changelog', 'Careers', 'Partner program'][$i % 8],
                'folder_id' => $i % 4 === 0 ? null : $folders[$i % 3]->id, 'track_conversion' => true,
                'created_at' => now()->subDays(random_int(1, 80)), 'archived' => $i === 24,
            ]);
            $link->tags()->attach($tags->random(random_int(0, 2))->pluck('id')->all());

            return $link;
        });
    }

    private function events(Workspace $workspace, $links): void
    {
        $weights = $links->mapWithKeys(fn ($l, $i) => [$l->id => max(1, 30 - $i)]); // a long-tail of popularity
        $customers = collect(range(1, 60))->map(fn ($i) => Customer::firstOrCreate(['workspace_id' => $workspace->id, 'external_id' => "demo_user_{$i}"], ['name' => fake()->name(), 'email' => "customer{$i}@example.com"]));

        $clicks = [];
        for ($n = 0; $n < 6000; $n++) {
            $link = $links->firstWhere('id', $this->pickWeighted($weights->all()));
            [$device, $browser, $os] = self::DEVICES[array_rand(self::DEVICES)];
            $country = array_rand(self::COUNTRIES);
            $referer = $this->pickWeighted(self::REFERERS);
            $clicks[] = [
                'occurred_at' => now()->subSeconds((int) (pow(mt_rand() / mt_getrandmax(), 1.6) * 86400 * 85))->toIso8601String(),
                'workspace_id' => $workspace->id, 'link_id' => $link->id, 'click_id' => Str::lower(Str::random(16)), 'is_unique' => mt_rand(1, 100) <= 70,
                'url' => $link->url, 'country' => $country, 'continent' => self::COUNTRIES[$country], 'city' => self::CITIES[$country][array_rand(self::CITIES[$country])],
                'device' => $device, 'browser' => $browser, 'os' => $os, 'referer' => $referer, 'referer_url' => $referer === '(direct)' ? null : "https://{$referer}/",
                'qr' => mt_rand(1, 100) <= 8, 'utm_source' => $link->utm_source, 'utm_medium' => $link->utm_medium, 'utm_campaign' => $link->utm_campaign,
            ];
        }
        foreach (array_chunk($clicks, 1000) as $chunk) {
            DB::table('click_events')->insert($chunk);
        }

        $leads = $sales = [];
        foreach ($customers as $i => $customer) {
            $click = $clicks[array_rand($clicks)];
            $customer->update(['link_id' => $click['link_id'], 'click_id' => $click['click_id'], 'country' => $click['country']]);
            $dims = array_diff_key($click, array_flip(['occurred_at', 'workspace_id', 'link_id', 'click_id', 'is_unique']));
            $base = ['workspace_id' => $workspace->id, 'link_id' => $click['link_id'], 'customer_id' => $customer->id, 'click_id' => $click['click_id'], ...$dims];
            $leadAt = Carbon::parse($click['occurred_at'])->addMinutes(random_int(2, 600));
            if ($leadAt->isFuture()) {
                $leadAt = now()->subMinute();
            }
            $leads[] = [...$base, 'occurred_at' => $leadAt->toIso8601String(), 'event_name' => 'Sign up'];

            if ($i % 3 !== 0) {
                foreach (range(1, random_int(1, 3)) as $m) {
                    $at = $leadAt->copy()->addDays($m * 2);
                    if ($at->isFuture()) {
                        continue;
                    }
                    $sales[] = [...$base, 'occurred_at' => $at->toIso8601String(), 'event_name' => 'Purchase', 'invoice_id' => "demo_inv_{$i}_{$m}", 'amount' => [1900, 4900, 9900][array_rand([1900, 4900, 9900])], 'currency' => 'usd', 'payment_processor' => 'stripe'];
                }
            }
        }
        DB::table('lead_events')->insert($leads);
        DB::table('sale_events')->insert($sales);

        // Keep the denormalised link counters consistent with the seeded events.
        DB::statement('UPDATE links l SET clicks = c.n, last_clicked_at = c.last FROM (SELECT link_id, count(*) n, max(occurred_at) last FROM click_events WHERE workspace_id = ? GROUP BY link_id) c WHERE l.id = c.link_id', [$workspace->id]);
        DB::statement('UPDATE links l SET leads = c.n FROM (SELECT link_id, count(*) n FROM lead_events WHERE workspace_id = ? GROUP BY link_id) c WHERE l.id = c.link_id', [$workspace->id]);
        DB::statement('UPDATE links l SET sales = c.n, sale_amount = c.amt FROM (SELECT link_id, count(*) n, sum(amount) amt FROM sale_events WHERE workspace_id = ? GROUP BY link_id) c WHERE l.id = c.link_id', [$workspace->id]);
        $workspace->update(['events_usage' => count($clicks) + count($leads) + count($sales)]);
    }

    /** @param array<string|int, int> $weights */
    private function pickWeighted(array $weights): string|int
    {
        $roll = mt_rand(1, array_sum($weights));
        foreach ($weights as $key => $weight) {
            if (($roll -= $weight) <= 0) {
                return $key;
            }
        }

        return array_key_last($weights);
    }
}
