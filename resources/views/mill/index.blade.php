@extends('layouts.app')
@section('title', 'Weighbridge')
@section('content')
<div class="page-head">
    <div>
        <h1>Weighbridge</h1>
        <p class="lede">Trucks at the gate. Record mill kg. The farm-set on the batch does not get rewritten when fruit is mixed.</p>
    </div>
</div>

<div class="desk-split">
    <div>
        <h2>Waiting at the gate</h2>
        @forelse($waiting as $batch)
            <div class="weigh-card">
                <h2>{{ $batch->public_id }}</h2>
                <p class="muted">{{ $batch->aggregator->name }} · farm-gate <span class="kg">{{ number_format($batch->totalVolumeKg(), 1) }}</span> kg</p>
                <form method="post" action="{{ route('mill.store', $batch) }}" class="stack" style="max-width:none;margin-top:12px">
                    @csrf
                    @if(auth()->user()->organization_id)
                        <input type="hidden" name="mill_id" value="{{ auth()->user()->organization_id }}">
                    @else
                        <label>Mill
                            <select name="mill_id" required>
                                @foreach($mills as $mill)
                                    <option value="{{ $mill->id }}">{{ $mill->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                    <label>Weighbridge kg
                        <input type="number" step="0.1" name="mill_weight_kg" required placeholder="Ticket weight">
                    </label>
                    <button class="btn" type="submit">Stamp intake</button>
                </form>
            </div>
        @empty
            <p class="muted">No closed farm-sets waiting.</p>
        @endforelse
    </div>
    <div>
        <h2>Already weighed</h2>
        @forelse($received as $batch)
            <div class="weigh-card">
                <h2>{{ $batch->public_id }}</h2>
                <p>Mill <span class="kg">{{ number_format($batch->mill_weight_kg, 1) }}</span> kg</p>
                <p class="muted">Farm-gate was {{ number_format($batch->totalVolumeKg(), 1) }} kg. Difference is expected.</p>
                <a href="{{ route('batches.show', $batch) }}">Open ticket</a>
            </div>
        @empty
            <p class="muted">Nothing weighed yet.</p>
        @endforelse
    </div>
</div>
@endsection
