<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#2b2b2b">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="TCDA Field">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>TCDA Field</title>
    <link rel="manifest" href="{{ asset('field-manifest.json') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icons/field-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/field-180.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/field.css') }}?v=17">
</head>
<body class="field-body">
<header class="field-bar">
    <div class="field-bar-brand">
        <img class="field-logo" src="{{ asset('images/fisc-logo.jpg') }}" alt="FISC">
        <div class="field-bar-identity">
            <p class="brand-kicker" id="net-label">Offline-capable field app</p>
            <strong id="who">Sign in once, then work without signal</strong>
        </div>
    </div>
    <div class="field-bar-actions">
        <span class="field-install-chip" id="install-chip">Install: not added</span>
        <button type="button" class="btn field-sync" id="sync-btn" hidden>Sync</button>
        <button type="button" class="field-logout" id="logout-btn" hidden>Sign out</button>
    </div>
</header>
<aside class="field-install" id="install-banner">
    <img class="field-install-icon" src="{{ asset('icons/field-192.png') }}" alt="">
    <div>
        <strong>Install TCDA Field</strong>
        <p class="field-install-state" id="install-state">Install status: not added yet.</p>
        <p id="install-copy">Add it to a phone home screen, or download it for this PC.</p>
        <ol class="field-install-ios" id="install-ios-steps" hidden>
            <li>Tap the Share button in Safari.</li>
            <li>Tap <strong>Add to Home Screen</strong>.</li>
            <li>Open the FISC icon — not Safari. This bar will then say Installed.</li>
        </ol>
    </div>
    <div class="field-install-actions">
        <button type="button" class="btn" id="install-btn">Add to Home screen</button>
        <button type="button" class="btn secondary" id="install-desktop">Download for this PC</button>
        <button type="button" class="btn secondary" id="install-dismiss">Continue in browser</button>
    </div>
</aside>
<p class="field-status" id="status" hidden></p>
<main id="app" class="field-main"></main>
<nav class="field-tabs" id="tabs" hidden>
    <button type="button" data-view="farmers">Farmers</button>
    <button type="button" data-view="enrol">Enrol</button>
    <button type="button" data-view="plot">Map plot</button>
    <button type="button" data-view="batch">Batch</button>
    <button type="button" data-view="outbox">Outbox</button>
</nav>
    <script src="{{ asset('js/field-app.js') }}?v=17"></script>
</body>
</html>
