<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

/**
 * Write-behind buffer for click events. The redirect pushes one JSON line (O(1), no SQL);
 * FlushClicks drains it in batches into the partitioned table.
 */
class ClickBuffer
{
    public const KEY = 'clicks:buffer';

    public function push(array $event): void
    {
        Redis::rpush(self::KEY, json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Atomically take up to $limit events off the head of the buffer.
     *
     * @return array<int, array<string, mixed>>
     */
    public function drain(int $limit = 1000): array
    {
        $script = <<<'LUA'
local items = redis.call('LRANGE', KEYS[1], 0, ARGV[1] - 1)
redis.call('LTRIM', KEYS[1], ARGV[1], -1)
return items
LUA;

        $raw = Redis::eval($script, 1, self::KEY, $limit);

        return array_map(fn (string $line) => json_decode($line, true), $raw ?: []);
    }

    public function size(): int
    {
        return (int) Redis::llen(self::KEY);
    }

    public function clear(): void
    {
        Redis::del(self::KEY);
    }
}
