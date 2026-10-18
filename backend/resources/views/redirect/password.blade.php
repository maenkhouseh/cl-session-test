@extends('redirect._layout')
@section('title', 'Password required')
@section('content')
    <h1>This link is password protected</h1>
    <p>Enter the password to continue.</p>
    <form method="post" action="">
        <input type="password" name="password" placeholder="Password" autofocus required>
        @if($failed)<p class="error" style="margin:.5rem 0 0">Incorrect password. Try again.</p>@endif
        <button type="submit">Continue</button>
    </form>
@endsection
