import { NextResponse, type NextRequest } from "next/server";

/** Laravel names the session cookie `<app-name>-session`. */
const SESSION_COOKIE = process.env.SESSION_COOKIE_NAME ?? "shortie-session";

/**
 * Optimistic gate only: a visitor with no session cookie can never be authenticated, so send them to
 * /login before any dashboard JS loads. The real check is the API (401 → the dashboard layout redirects).
 */
export function proxy(request: NextRequest) {
  if (!request.cookies.has(SESSION_COOKIE)) {
    const login = new URL("/login", request.url);
    login.searchParams.set("next", request.nextUrl.pathname + request.nextUrl.search);
    return NextResponse.redirect(login);
  }

  return NextResponse.next();
}

export const config = {
  matcher: ["/app/:path*"],
};
