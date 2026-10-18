<?php

namespace App\Http\Controllers\Redirect;

use App\Data\RedirectDecision;
use App\Http\Controllers\Controller;
use App\Models\Link;
use App\Services\LinkCache;
use App\Services\RedirectResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Cookie;

class PasswordController extends Controller
{
    public function __construct(private RedirectResolver $resolver, private RedirectController $redirects, private LinkCache $cache) {}

    public function verify(Request $request, string $key): Response|RedirectResponse
    {
        $domain = strtolower($request->getHost());
        $link = $this->cache->remember($domain, $key, fn () => Link::query()->forDomainKey($domain, $key)->first());

        if (! $link || ! $link->password) {
            return redirect()->to($request->getPathInfo());
        }

        if (! $this->resolver->passwordMatches($link, (string) $request->input('password'))) {
            return $this->redirects->render($request, new RedirectDecision(RedirectDecision::PASSWORD, null, $link, null, true));
        }

        // Unlocked: remember it for a day, then re-enter the normal flow (which now records the click).
        $response = redirect()->to($request->getPathInfo().($request->getQueryString() ? '?'.$request->getQueryString() : ''));
        $response->headers->setCookie(Cookie::create(
            $this->resolver->cookieName($link), $this->resolver->unlockToken($link), now()->addDay(), '/', null, $request->secure(), true, false, 'lax',
        ));

        return $response;
    }
}
