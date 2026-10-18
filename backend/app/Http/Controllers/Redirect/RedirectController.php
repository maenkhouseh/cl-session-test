<?php

namespace App\Http\Controllers\Redirect;

use App\Data\RedirectDecision;
use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Services\RedirectResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Cookie;

/** Hot path. Thin on purpose: the resolver decides, this renders. */
class RedirectController extends Controller
{
    public function __construct(private RedirectResolver $resolver) {}

    public function show(Request $request, string $key): Response|RedirectResponse
    {
        return $this->render($request, $this->resolver->resolve($request, $key));
    }

    /** `https://short.domain/` — the domain's placeholder, if any. */
    public function root(Request $request): Response|RedirectResponse
    {
        $placeholder = Domain::query()->where('slug', strtolower($request->getHost()))->value('placeholder_url');

        return $placeholder ? $this->redirect($placeholder) : response()->view('redirect.placeholder', [], 200);
    }

    public function render(Request $request, RedirectDecision $d): Response|RedirectResponse
    {
        $response = match ($d->type) {
            RedirectDecision::REDIRECT => $this->redirect($d->url),
            RedirectDecision::CLOAK => response()->view('redirect.cloak', ['link' => $d->link, 'url' => $d->url]),
            RedirectDecision::PREVIEW => response()->view('redirect.preview', ['link' => $d->link, 'url' => $d->url]),
            RedirectDecision::PASSWORD => response()->view('redirect.password', ['link' => $d->link, 'failed' => $d->passwordFailed], $d->passwordFailed ? 401 : 200),
            RedirectDecision::EXPIRED => $d->url ? $this->redirect($d->url) : response()->view('redirect.expired', [], 410),
            default => $d->url ? $this->redirect($d->url) : response()->view('redirect.not-found', [], 404),
        };

        if ($d->variantCookie !== null) {
            $response->headers->setCookie(Cookie::create('ab_'.substr(sha1($d->link->id), 0, 12), $d->variantCookie, now()->addDays(30), '/', null, $request->secure(), true, false, 'lax'));
        }

        // Short links are not content: keep them out of search results unless the owner opted in.
        if (! ($d->link?->do_index)) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }

    private function redirect(string $url): RedirectResponse
    {
        // 302, never cacheable: every visit must reach us to be counted and re-targeted.
        return redirect()->away($url, 302, ['Cache-Control' => 'no-store, max-age=0', 'Referrer-Policy' => 'no-referrer-when-downgrade']);
    }
}
