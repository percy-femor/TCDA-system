<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · {{ $desk['title'] }}</title>
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icons/field-192.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="login-desk login-desk-{{ $desk['key'] }}">
<div class="auth-shell">
    <section class="auth-art">
        <p class="brand-kicker">{{ $desk['kicker'] }} desk</p>
        <img class="auth-logo" src="{{ asset('images/fisc-logo.jpg') }}" alt="FISC">
        <h1>{{ $desk['title'] }}</h1>
        <p>{{ $desk['blurb'] }}</p>
        <p class="muted" style="color:#b7c9bb">This login cannot open another role’s page.</p>
        <p><a href="{{ route('login') }}" style="color:#e2c48a">← All desks</a></p>
    </section>
    <section class="auth-panel">
        <div>
            <h2 style="margin-top:0">Sign in to {{ $desk['title'] }}</h2>
            @if($errors->any())
                <div class="errors">{{ $errors->first() }}</div>
            @endif
            <form method="post" action="{{ route('login.desk.store', $desk['key']) }}" class="stack">
                @csrf
                <label>Email
                    <input type="email" name="email" value="{{ old('email', $desk['email']) }}" required>
                </label>
                <label>Password
                    <input type="password" name="password" required>
                </label>
                <button class="btn" type="submit">Enter this desk</button>
            </form>
            <p class="demo-accounts">Demo password is <code>password</code>@if($desk['key'] === 'officer'). Bono officers use <code>officer.bono@tcda.gov.gh</code> on this same field desk.@endif</p>
        </div>
    </section>
</div>
</body>
</html>
