@extends('layouts.app')
@section('title', 'Enrol farmer')
@section('content')
<div class="page-head">
    <div>
        <h1>Enrol farmer</h1>
        <p class="lede">
            Identity and the farm-land geofence are saved together.
            Region must be one of Ghana’s 16 official regions.
            @if($lockedRegion)
                This officer can only enrol in {{ $lockedRegion }}.
            @endif
        </p>
    </div>
</div>
<div class="card">
    <form method="post" action="{{ route('farmers.store') }}" class="stack" style="max-width:none">
        @csrf
        <label>Full name
            <input name="name" value="{{ old('name') }}" required>
        </label>
        <div class="grid-2">
            <label>Phone
                <input name="phone" value="{{ old('phone') }}">
            </label>
            <label>Paper card no.
                <input name="paper_card_no" value="{{ old('paper_card_no') }}">
            </label>
        </div>
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
                @if($lockedRegion)
                    <input type="hidden" name="region" value="{{ $lockedRegion }}">
                    <input value="{{ $lockedRegion }}" disabled>
                @else
                    <select name="region" required>
                        <option value="">Select region</option>
                        @foreach($regions as $region)
                            <option value="{{ $region }}" @selected(old('region') === $region)>{{ $region }}</option>
                        @endforeach
                    </select>
                @endif
            </label>
            <label>Chain
                <select name="chain" required>
                    <option value="palm" @selected(old('chain')==='palm')>Palm (EUDR geolocation)</option>
                    <option value="cashew" @selected(old('chain')==='cashew')>Cashew (E&S due diligence)</option>
                </select>
            </label>
        </div>
        <label>Usual aggregator
            <select name="aggregator_id">
                <option value="">—</option>
                @foreach($aggregators as $org)
                    <option value="{{ $org->id }}" @selected((string) old('aggregator_id') === (string) $org->id)>{{ $org->name }}</option>
                @endforeach
            </select>
        </label>
        <label style="display:flex;align-items:center;gap:8px">
            <input type="checkbox" name="consent" value="1" style="width:auto" @checked(old('consent'))>
            Farmer gave consent to store identity and plot data
        </label>
        @include('partials.farm-geofence')
        <button class="btn" type="submit">Save enrolment</button>
    </form>
</div>
@endsection
@push('scripts')
<script src="{{ asset('js/farm-geofence.js') }}"></script>
@endpush
