@extends('layouts.app')
@section('title', 'DDS '.$batch->public_id)
@section('content')
<div class="page-head">
    <div>
        <h1>Due-diligence pack {{ $batch->public_id }}</h1>
        <p class="lede">
            {{ strtoupper($batch->chain) }} · Aggregator {{ $batch->aggregator->name }}
            @if($batch->mill) · Mill {{ $batch->mill->name }} @endif
            @if($batch->shipment_ref) · Shipment {{ $batch->shipment_ref }} @endif
        </p>
    </div>
    <span class="badge {{ $assessment['status'] }}">{{ $assessment['status'] }}</span>
</div>

@if($assessment['gaps'])
<div class="card">
    <h2>Unresolved gaps — printed as-is</h2>
    <ul class="gap-list">
        @foreach($assessment['gaps'] as $gap)
            <li>{{ $gap }}</li>
        @endforeach
    </ul>
</div>
@else
<div class="card">
    <h2>No gaps on current rules</h2>
    <p class="muted">Palm: geolocation + land status + consent. Cashew: consent; mapping is recommended and listed if missing.</p>
</div>
@endif

<div class="card">
    <h2>Farm-set</h2>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Farmer</th>
                <th>Community</th>
                <th>Kg</th>
                <th>Plot GPS</th>
                <th>Land</th>
                <th>Consent</th>
            </tr>
            </thead>
            <tbody>
            @foreach($batch->members as $member)
                @php $plot = $member->plot ?? $member->farmer->plots->first(); @endphp
                <tr>
                    <td>{{ $member->farmer->public_id }} {{ $member->farmer->name }}</td>
                    <td>{{ $member->farmer->community }}</td>
                    <td>{{ number_format($member->volume_kg, 1) }}</td>
                    <td>{{ $plot && $plot->hasLocation() ? $plot->latitude.', '.$plot->longitude : 'Missing' }}</td>
                    <td>{{ $plot->land_status ?? 'unknown' }}</td>
                    <td>{{ $member->farmer->consent_at ? 'Yes' : 'No' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

@if($batch->status === 'mill_received')
<div class="card">
    <h2>Link shipment</h2>
    <form method="post" action="{{ route('dds.export', $batch) }}" class="stack">
        @csrf
        <label>Bill of lading / shipment ref
            <input name="shipment_ref" required>
        </label>
        <button class="btn" type="submit">Mark exported</button>
    </form>
</div>
@endif
@endsection
