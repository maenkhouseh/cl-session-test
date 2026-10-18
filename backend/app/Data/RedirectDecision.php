<?php

namespace App\Data;

use App\Models\Link;

/** What the redirect endpoint should do for a request — decided by RedirectResolver, rendered by the controller. */
final readonly class RedirectDecision
{
    public const REDIRECT = 'redirect';

    public const PASSWORD = 'password';

    public const PREVIEW = 'preview';

    public const CLOAK = 'cloak';

    public const NOT_FOUND = 'not_found';

    public const EXPIRED = 'expired';

    public function __construct(
        public string $type,
        public ?string $url = null,
        public ?Link $link = null,
        public ?string $variantCookie = null,
        public bool $passwordFailed = false,
    ) {}

    public static function to(string $type, ?string $url = null, ?Link $link = null): self
    {
        return new self($type, $url, $link);
    }
}
