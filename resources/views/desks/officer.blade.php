@extends('layouts.app')
@section('title', 'Field officer desk')
@section('content')
<div class="page-head">
    <div>
        <h1>Field officer desk</h1>
        <p class="lede">
            Today’s work is enrolment and farm geofences in <strong>{{ $assignedRegion ?: 'an unassigned region' }}</strong>.
            This is not the mill and not the export desk.
        </p>
    </div>
</div>

<div class="job-card">
    <h2>Install the field app on this phone</h2>
    <p class="muted">
        The field page reports whether the icon was added or cancelled.
        You can add it to a phone home screen or download it for this PC.
    </p>
    <ol class="geofence-steps">
        <li><strong>Android:</strong> tap <strong>Add to Home screen</strong> and wait for “Installed”.</li>
        <li><strong>iPhone:</strong> Share → <strong>Add to Home Screen</strong>, then open that icon.</li>
        <li><strong>Windows / Mac:</strong> tap <strong>Download for this PC</strong> (Start menu or Applications).</li>
    </ol>
    <p style="margin-top:12px;display:flex;flex-wrap:wrap;gap:8px">
        <a class="btn" href="{{ url('/field?install=1') }}">Add TCDA Field to this phone</a>
        <a class="btn secondary" href="{{ url('/field?install=1&target=pc') }}">Download for this PC</a>
    </p>
</div>

<div class="job-card">
    <h2>Enrol a farmer today</h2>
    <p class="muted">Consent is a timestamp. The farm land must be drawn as a polygon geofence — a single GPS pin is not enough.</p>
    <form method="post" action="{{ route('farmers.store') }}" class="stack" style="max-width:none;margin-top:12px">
        @csrf
        <label>Full name
            <input name="name" value="{{ old('name') }}" required>
        </label>
        <div class="grid-2">
            <label>Community
                <input name="community" value="{{ old('community') }}" required>
            </label>
            <label>District
                <input name="district" value="{{ old('district') }}" required>
            </label>
        </div>
        <div class="grid-2">
            <label>Region
                <input type="hidden" name="region" value="{{ $lockedRegion }}">
                <input value="{{ $lockedRegion ?: 'Ask TCDA to assign a region' }}" disabled>
            </label>
            <label>Chain
                <select name="chain" required>
                    <option value="palm">Palm</option>
                    <option value="cashew">Cashew</option>
                </select>
            </label>
        </div>
        <label>Paper card no.
            <input name="paper_card_no" value="{{ old('paper_card_no') }}">
        </label>
        <label style="display:flex;align-items:center;gap:8px">
            <input type="checkbox" name="consent" value="1" style="width:auto" checked>
            Farmer gave consent
        </label>
        @include('partials.farm-geofence')
        <button class="btn" type="submit" @disabled(! $lockedRegion)>Save enrolment</button>
    </form>
</div>

<div class="desk-split">
    <div class="job-card">
        <h2>Follow-up: missing consent</h2>
        @forelse($needsConsent as $farmer)
            <p><a href="{{ route('farmers.show', $farmer) }}">{{ $farmer->public_id }}</a> {{ $farmer->name }} · {{ $farmer->community }}</p>
        @empty
            <p class="muted">No consent gaps in your region.</p>
        @endforelse
    </div>
    <div class="job-card">
        <h2>Follow-up: missing geofence</h2>
        @forelse($needsGps as $farmer)
            <p><a href="{{ route('farmers.show', $farmer) }}">{{ $farmer->public_id }}</a> {{ $farmer->name }} · {{ $farmer->chain }}</p>
        @empty
            <p class="muted">Every farmer in view has a mapped plot.</p>
        @endforelse
    </div>
</div>

<div class="job-card">
    <h2>My farmers</h2>
    <p class="muted">Only {{ $assignedRegion ?: 'your' }} farmers. Other regions are hidden.</p>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Chain</th>
                <th>Community</th>
                <th>Consent</th>
                <th>Geofence</th>
            </tr>
            </thead>
            <tbody>
            @foreach($farmers as $farmer)
                <tr>
                    <td><a href="{{ route('farmers.show', $farmer) }}">{{ $farmer->public_id }}</a></td>
                    <td>{{ $farmer->name }}</td>
                    <td>{{ $farmer->chain }}</td>
                    <td>{{ $farmer->community }}</td>
                    <td>{{ $farmer->consent_at ? 'Yes' : 'Missing' }}</td>
                    <td>{{ $farmer->hasGeolocation() ? 'Mapped' : 'Gap' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px">{{ $farmers->links() }}</div>
</div>
@endsection
@push('scripts')
<script src="{{ asset('js/farm-geofence.js') }}"></script>
@endpush
