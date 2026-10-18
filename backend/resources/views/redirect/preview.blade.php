<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $link->title ?: $url }}</title>
    <meta property="og:title" content="{{ $link->title }}">
    <meta property="og:description" content="{{ $link->description }}">
    <meta property="og:url" content="{{ $link->short_link }}">
    <meta property="og:type" content="website">
    @if($link->image)<meta property="og:image" content="{{ $link->image }}"><meta name="twitter:image" content="{{ $link->image }}">@endif
    @if($link->video)<meta property="og:video" content="{{ $link->video }}">@endif
    <meta name="twitter:card" content="{{ $link->image ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $link->title }}">
    <meta name="twitter:description" content="{{ $link->description }}">
    <link rel="canonical" href="{{ $url }}">
</head>
<body></body>
</html>
