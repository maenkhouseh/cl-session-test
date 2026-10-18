<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', config('app.name'))</title>
    <style>
        :root { color-scheme: light dark; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; font: 16px/1.5 system-ui, sans-serif; background: Canvas; color: CanvasText; }
        main { width: min(92vw, 26rem); padding: 2rem; border: 1px solid color-mix(in srgb, CanvasText 15%, transparent); border-radius: .9rem; text-align: center; }
        h1 { font-size: 1.25rem; margin: 0 0 .5rem; }
        p { margin: 0 0 1rem; opacity: .75; }
        input, button { font: inherit; padding: .65rem .8rem; border-radius: .5rem; border: 1px solid color-mix(in srgb, CanvasText 25%, transparent); width: 100%; box-sizing: border-box; }
        button { margin-top: .6rem; cursor: pointer; background: CanvasText; color: Canvas; border: 0; }
        .error { color: #dc2626; opacity: 1; }
    </style>
</head>
<body><main>@yield('content')</main></body>
</html>
