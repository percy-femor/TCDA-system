@extends('layouts.app')
@section('title', 'Open batch')
@section('content')
<div class="page-head">
    <div>
        <h1>Open a batch</h1>
        <p class="lede">Start at the buying point. Paper, mobile, or desk capture all write the same ledger.</p>
    </div>
</div>
<div class="card">
    <form method="post" action="{{ route('batches.store') }}" class="stack">
        @csrf
        <label>Chain
            <select name="chain" required>
                <option value="palm">Palm</option>
                <option value="cashew">Cashew</option>
            </select>
        </label>
        <label>Aggregator
            <select name="aggregator_id" required>
                @foreach($aggregators as $org)
                    <option value="{{ $org->id }}" @selected($user->organization_id === $org->id)>{{ $org->name }}</option>
                @endforeach
            </select>
        </label>
        <label>Capture channel
            <select name="capture_channel">
                <option value="desk">Desk (this screen)</option>
                <option value="paper">Paper card / stub</option>
                <option value="mobile">Mobile (digitised)</option>
            </select>
        </label>
        <label>Notes
            <textarea name="notes" rows="2">{{ old('notes') }}</textarea>
        </label>
        <button class="btn" type="submit">Open batch</button>
    </form>
</div>
@endsection
