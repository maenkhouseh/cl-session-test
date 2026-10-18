<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $link->title ?: parse_url($url, PHP_URL_HOST) }}</title>
    @if($link->description)<meta name="description" content="{{ $link->description }}">@endif
    @if($link->image)<meta property="og:image" content="{{ $link->image }}">@endif
    <style>html,body,iframe{margin:0;padding:0;width:100%;height:100%;border:0}</style>
</head>
<body><iframe src="{{ $url }}" referrerpolicy="no-referrer-when-downgrade" allow="fullscreen"></iframe></body>
</html>
