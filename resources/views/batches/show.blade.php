@extends('layouts.app')
@section('title', $batch->public_id)
@section('content')
<div class="page-head">
    <div>
        <h1>{{ $batch->public_id }}</h1>
        <p class="lede">{{ $batch->aggregator->name }} · {{ $batch->capture_channel }} capture · {{ number_format($batch->totalVolumeKg(), 1) }} kg from {{ $batch->farmCount() }} farms</p>
    </div>
    <div class="row-actions">
        <span class="badge {{ $batch->chain }}">{{ $batch->chain }}</span>
        <span class="badge {{ $batch->status }}">{{ str_replace('_',' ',$batch->status) }}</span>
        <span class="badge {{ $assessment['status'] }}">{{ $assessment['status'] }}</span>
    </div>
</div>

@if($assessment['gaps'])
    <div class="card">
        <h2>Compliance gaps</h2>
        <ul class="gap-list">
            @foreach($assessment['gaps'] as $gap)
                <li>{{ $gap }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card">
    <h2>Farm deliveries</h2>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Farmer</th>
                <th>Kg</th>
                <th>Stub</th>
                <th>Channel</th>
                <th>Date</th>
            </tr>
            </thead>
            <tbody>
            @forelse($batch->members as $member)
                <tr>
                    <td>
                        @if(auth()->user()->canEnrolFarmers())
                            <a href="{{ route('farmers.show', $member->farmer) }}">{{ $member->farmer->public_id }}</a>
                        @else
                            {{ $member->farmer->public_id }}
                        @endif
                        {{ $member->farmer->name }}
                    </td>
                    <td>{{ number_format($member->volume_kg, 1) }}</td>
                    <td>{{ $member->paper_stub_no ?: '—' }}</td>
                    <td>{{ $member->capture_channel }}</td>
                    <td>{{ $member->delivered_at->format('d M Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No deliveries yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($batch->status === 'open' && auth()->user()->canManageBatches())
<div class="card">
    <h2>Add delivery</h2>
    <form method="post" action="{{ route('batches.deliveries', $batch) }}" class="stack">
        @csrf
        <label>Farmer
            <select name="farmer_id" required>
                @foreach($farmers as $farmer)
                    <option value="{{ $farmer->id }}">{{ $farmer->public_id }} — {{ $farmer->name }} ({{ $farmer->community }})</option>
                @endforeach
            </select>
        </label>
        <div class="grid-2">
            <label>Volume (kg)
                <input type="number" step="0.1" name="volume_kg" required>
            </label>
            <label>Paper stub no.
                <input name="paper_stub_no">
            </label>
        </div>
        <div class="grid-2">
            <label>Channel
                <select name="capture_channel">
                    <option value="desk">Desk</option>
                    <option value="paper">Paper</option>
                    <option value="mobile">Mobile</option>
                </select>
            </label>
            <label>Delivered
                <input type="date" name="delivered_at" value="{{ now()->toDateString() }}">
            </label>
        </div>
        <div class="row-actions">
            <button class="btn" type="submit">Add delivery</button>
        </div>
    </form>
    <form method="post" action="{{ route('batches.close', $batch) }}" style="margin-top:16px">
        @csrf
        <button class="btn secondary" type="submit">Close batch</button>
    </form>
</div>
@endif

<div class="card">
    <h2>Custody log</h2>
    <ul class="timeline">
        @foreach($batch->custodyEvents as $event)
            <li>
                <strong>{{ str_replace('_', ' ', $event->event_type) }}</strong>
                · {{ $event->happened_at->format('d M Y H:i') }}
                · {{ $event->actor?->name ?: 'system' }}
            </li>
        @endforeach
    </ul>
</div>
@endsection
