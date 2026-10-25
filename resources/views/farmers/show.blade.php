@extends('layouts.app')
@section('title', $farmer->name)
@section('content')
<div class="page-head">
    <div>
        <h1>{{ $farmer->name }}</h1>
        <p class="lede">{{ $farmer->public_id }} · {{ $farmer->community }}, {{ $farmer->district }}, {{ $farmer->region }}</p>
    </div>
    <span class="badge {{ $farmer->chain }}">{{ $farmer->chain }}</span>
</div>

<div class="card">
    <h2>Identity</h2>
    <p class="muted">
        Phone {{ $farmer->phone ?: '—' }} ·
        Card {{ $farmer->paper_card_no ?: '—' }} ·
        Consent {{ $farmer->consent_at?->format('d M Y') ?: 'missing' }} ·
        Enrolled by {{ $farmer->enrolledBy?->name ?: '—' }}
    </p>
</div>

<div class="card">
    <h2>Farm-land geofences</h2>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Plot</th>
                <th>Centroid</th>
                <th>Fence</th>
                <th>Area (ha)</th>
                <th>Land status</th>
                <th>Mapped</th>
            </tr>
            </thead>
            <tbody>
            @forelse($farmer->plots as $plot)
                <tr>
                    <td>{{ $plot->public_id }}</td>
                    <td>{{ $plot->hasLocation() ? $plot->latitude.', '.$plot->longitude : 'Missing' }}</td>
                    <td>{{ filled($plot->geojson) ? 'Polygon' : 'Point only' }}</td>
                    <td>{{ $plot->area_hectares ?? '—' }}</td>
                    <td>{{ $plot->land_status }}</td>
                    <td>{{ $plot->mapped_at?->format('d M Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No plot yet — palm lots will fail EUDR until a geofence is drawn.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @foreach($farmer->plots as $plot)
        @if(filled($plot->geojson))
            <div style="margin-top:16px">
                <h3>{{ $plot->public_id }}</h3>
                @include('partials.farm-geofence', ['readonly' => true, 'geojson' => $plot->geojson, 'startLat' => $plot->latitude, 'startLng' => $plot->longitude])
            </div>
        @endif
    @endforeach
</div>

@if(auth()->user()->canHandleFarmer($farmer))
<div class="card">
    <h2>Add another plot geofence</h2>
    <form method="post" action="{{ route('plots.store', $farmer) }}" class="stack" style="max-width:none">
        @csrf
        @include('partials.farm-geofence')
        <button class="btn" type="submit">Save plot</button>
    </form>
</div>
@endif
@endsection
@push('scripts')
<script src="{{ asset('js/farm-geofence.js') }}"></script>
@endpush
