<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Desk') · TCDA Traceability</title>
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icons/field-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/field-180.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script>
      if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistrations().then(function (regs) {
          regs.forEach(function (reg) {
            var script = (reg.active && reg.active.scriptURL) || '';
            if (reg.scope === location.origin + '/' || script.indexOf('field-sw.js') !== -1) {
              reg.update();
            }
          });
        });
      }
    </script>
</head>
<body class="desk-{{ auth()->user()->role->value }}">
@php $role = auth()->user()->role->value; @endphp
<div class="app">
    <aside class="sidebar">
        <div>
            <img class="side-logo" src="{{ asset('images/fisc-logo.jpg') }}" alt="FISC">
            <p class="brand-kicker">{{ auth()->user()->role->label() }}</p>
            <p class="brand">
                @switch($role)
                    @case('tcda') National command @break
                    @case('field_officer') Field officer desk @break
                    @case('aggregator') Buying point @break
                    @case('mill') Weighbridge @break
                    @case('exporter') Buyer packs @break
                @endswitch
            </p>
        </div>
        <nav class="nav">
            @if($role === 'tcda')
                <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">National command</a>
                <a href="{{ route('supervision') }}" class="{{ request()->routeIs('supervision') ? 'active' : '' }}">Exceptions</a>
                <a href="{{ route('officers.index') }}" class="{{ request()->routeIs('officers.*') ? 'active' : '' }}">Officers</a>
                <a href="{{ route('farmers.index') }}" class="{{ request()->routeIs('farmers.*') ? 'active' : '' }}">National farmer roll</a>
                <a href="{{ route('batches.index') }}" class="{{ request()->routeIs('batches.*') ? 'active' : '' }}">All farm-sets</a>
            @elseif($role === 'field_officer')
                <a href="{{ route('officer.home') }}" class="{{ request()->routeIs('officer.home') ? 'active' : '' }}">Today’s field work</a>
                <a href="{{ route('farmers.create') }}" class="{{ request()->routeIs('farmers.create') ? 'active' : '' }}">Enrol a farmer</a>
                <a href="{{ url('/field?install=1') }}">Install field app</a>
            @elseif($role === 'aggregator')
                <a href="{{ route('buying.home') }}" class="{{ request()->routeIs('buying.home') || request()->routeIs('batches.show') ? 'active' : '' }}">Buying-point floor</a>
                <a href="{{ route('batches.create') }}" class="{{ request()->routeIs('batches.create') ? 'active' : '' }}">Open a farm-set</a>
                <a href="{{ route('buying.farmers') }}" class="{{ request()->routeIs('buying.farmers') || request()->routeIs('farmers.show') ? 'active' : '' }}">Farmers I buy from</a>
            @elseif($role === 'mill')
                <a href="{{ route('mill.index') }}" class="{{ request()->routeIs('mill.*') ? 'active' : '' }}">Trucks at the gate</a>
            @elseif($role === 'exporter')
                <a href="{{ route('dds.index') }}" class="{{ request()->routeIs('dds.*') ? 'active' : '' }}">Packs for buyers</a>
            @endif
        </nav>
        <div class="side-user">
            <strong>{{ auth()->user()->name }}</strong>
            {{ auth()->user()->role->label() }}
            @if(auth()->user()->operatingRegion())
                · {{ auth()->user()->operatingRegion() }}
            @elseif(auth()->user()->isTcda())
                · all 16 regions
            @endif
            <form method="post" action="{{ route('logout') }}" style="margin-top:8px">
                @csrf
                <button class="link-btn" type="submit">Sign out</button>
            </form>
        </div>
    </aside>
    <main class="main">
        <div class="desk-mast">
            <span class="desk-mast-kicker">{{ auth()->user()->role->label() }}</span>
            <strong>
                @switch($role)
                    @case('tcda') /tcda · National command @break
                    @case('field_officer') /officer · Field desk @break
                    @case('aggregator') /buying · Buying-point floor @break
                    @case('mill') /mill · Weighbridge @break
                    @case('exporter') /export · Buyer packs @break
                @endswitch
            </strong>
        </div>
        <div class="desk-banner">
            @switch($role)
                @case('tcda')
                    You supervise the national registry. Field enrolment, mill weighing, and export packing are other logins.
                    @break
                @case('field_officer')
                    You enrol and map farmers in {{ auth()->user()->operatingRegion() ?: 'your assigned region' }} only. You cannot open batches, weigh fruit, or pack a shipment.
                    @break
                @case('aggregator')
                    You open farm-sets and record kg at the buying point. You cannot weigh at the mill or issue a buyer pack.
                    @break
                @case('mill')
                    You record weighbridge kg. You cannot enrol farmers or pack an export file.
                    @break
                @case('exporter')
                    You pack buyer due-diligence files. You cannot enrol farmers or run the weighbridge.
                    @break
            @endswitch
        </div>
        @if(session('status'))
            <div class="flash">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="errors">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif
        @yield('content')
    </main>
</div>
@stack('scripts')
</body>
</html>
