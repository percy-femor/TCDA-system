@php
    $readonly = $readonly ?? false;
    $geojson = $geojson ?? old('geojson');
    $mapRegion = $mapRegion ?? ($lockedRegion ?? auth()->user()?->operatingRegion());
    $view = \App\Support\GhanaMap::viewFor($mapRegion);
@endphp
<div class="geofence {{ $readonly ? 'is-readonly' : '' }}"
     data-geofence
     @if($readonly) data-readonly="1" @endif
     data-region="{{ $mapRegion }}"
     data-start-lat="{{ $startLat ?? $view['lat'] }}"
     data-start-lng="{{ $startLng ?? $view['lng'] }}"
     data-start-zoom="{{ $view['zoom'] }}">
    @if(! $readonly)
        <h2>Farm land geofence</h2>
        <p class="muted">
            The map is locked to Ghana.
            @if($mapRegion)
                It opens on <strong>{{ $mapRegion }}</strong>.
            @endif
            A single pin is not enough — walk or click the real farm boundary.
        </p>
        <ol class="geofence-steps">
            <li>Search the community, or stand on the farm and use GPS, so the satellite view shows this plot.</li>
            <li>Zoom in until you can see the trees or field edge.</li>
            <li>Click each corner around the farm (or tap <strong>Add this GPS corner</strong> at each corner while you walk).</li>
            <li>Need at least three corners. The fence turns green when it is closed.</li>
        </ol>
        <div class="geofence-search">
            <input type="search" data-place-q placeholder="Find a place in Ghana — e.g. Dixcove, Techiman">
            <button class="btn secondary" type="button" data-place-search>Find in Ghana</button>
        </div>
        <ul class="geofence-places" data-place-results hidden></ul>
    @endif
    <div class="geofence-map" data-map></div>
    @if(! $readonly)
        <div class="row-actions" style="margin:12px 0">
            <button class="btn" type="button" data-locate>Centre on my GPS</button>
            <button class="btn secondary" type="button" data-gps>Add this GPS corner</button>
            <button class="btn secondary" type="button" data-undo>Undo last corner</button>
            <button class="btn secondary" type="button" data-clear>Clear fence</button>
        </div>
        <p class="muted" data-summary>Map is Ghana only. No geofence yet.</p>
        <textarea name="geojson" data-geojson hidden>{{ $geojson }}</textarea>
        <div class="grid-2">
            <label>Land status
                <select name="land_status" required>
                    <option value="customary" @selected(old('land_status', 'customary') === 'customary')>Customary</option>
                    <option value="documented" @selected(old('land_status') === 'documented')>Documented</option>
                    <option value="unknown" @selected(old('land_status') === 'unknown')>Unknown</option>
                </select>
            </label>
            <label>Area (ha) — filled from the polygon if left blank
                <input name="area_hectares" value="{{ old('area_hectares') }}" step="0.0001">
            </label>
        </div>
    @else
        <textarea hidden data-geojson>{{ $geojson }}</textarea>
        <p class="muted" data-summary></p>
    @endif
</div>
