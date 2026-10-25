@extends('layouts.app')
@section('title', 'Packs for buyers')
@section('content')
<div class="page-head">
    <div>
        <h1>Packs for buyers</h1>
        <p class="lede">Shipment files only. Gaps stay printed. This desk does not enrol farmers or stamp a weighbridge ticket.</p>
    </div>
</div>

<div class="desk-split">
    <div>
        <h2>Ready to link a bill of lading</h2>
        @forelse($ready as $batch)
            <div class="pack-card">
                <h2>{{ $batch->public_id }}</h2>
                <p class="muted">{{ strtoupper($batch->chain) }} · {{ $batch->mill?->name ?: 'Mill received' }}</p>
                <a class="btn" href="{{ route('dds.show', $batch) }}">Open buyer pack</a>
            </div>
        @empty
            <p class="muted">No clean packs waiting.</p>
        @endforelse

        <h2>Already shipped</h2>
        @forelse($shipped as $batch)
            <div class="pack-card">
                <h2>{{ $batch->public_id }}</h2>
                <p class="muted">Shipment {{ $batch->shipment_ref ?: '—' }}</p>
                <a href="{{ route('dds.show', $batch) }}">View pack</a>
            </div>
        @empty
            <p class="muted">No shipments linked yet.</p>
        @endforelse
    </div>
    <div>
        <h2>Gaps — do not invent GPS</h2>
        @forelse($gaps as $batch)
            <div class="pack-card is-gap">
                <h2>{{ $batch->public_id }}</h2>
                <p class="muted">Still a due-diligence pack. Missing plots stay missing.</p>
                <a class="btn" href="{{ route('dds.show', $batch) }}">Read the gaps</a>
            </div>
        @empty
            <p class="muted">No open gap packs.</p>
        @endforelse
    </div>
</div>
@endsection
