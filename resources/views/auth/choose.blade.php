<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Choose your desk · TCDA Traceability</title>
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icons/field-192.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="desk-picker-body">
<div class="desk-picker">
    <div class="desk-picker-hero">
        <img class="auth-logo" src="{{ asset('images/fisc-logo.jpg') }}" alt="FISC">
        <p class="brand-kicker">Ghana · palm and cashew</p>
        <h1>Choose your desk</h1>
        <p>Each role is a different page. Officers, mills, and exporters do not share a workspace.</p>
    </div>
    <div class="desk-doors">
        @foreach($desks as $desk)
            <a class="desk-door desk-door-{{ $desk['key'] }}" href="{{ route('login.desk', $desk['key']) }}">
                <span class="brand-kicker">{{ $desk['kicker'] }}</span>
                <strong>{{ $desk['title'] }}</strong>
                <p>{{ $desk['blurb'] }}</p>
                <span class="desk-door-url">/{{ $desk['key'] === 'exporter' ? 'export' : ($desk['key'] === 'aggregator' ? 'buying' : $desk['key']) }}</span>
            </a>
        @endforeach
        <a class="desk-door desk-door-field" href="{{ url('/field?install=1') }}">
            <span class="brand-kicker">Phone or PC</span>
            <strong>Field app (PWA)</strong>
            <p>Add to a phone home screen or download for this PC. The page tells you if the icon was added.</p>
            <span class="desk-door-url">/field</span>
        </a>
    </div>
</div>
</body>
</html>
