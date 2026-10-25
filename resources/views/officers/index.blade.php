@extends('layouts.app')
@section('title', 'Officers')
@section('content')
<div class="page-head">
    <div>
        <h1>Field officers</h1>
        <p class="lede">Create officers, assign each to one of Ghana’s 16 regions, or remove them. An officer can only enrol and update farmers in their assigned region.</p>
    </div>
</div>

<div class="card">
    <h2>Add an officer</h2>
    <form method="post" action="{{ route('officers.store') }}" class="stack" style="max-width:none">
        @csrf
        <div class="grid-2">
            <label>Name
                <input name="name" value="{{ old('name') }}" required>
            </label>
            <label>Email
                <input type="email" name="email" value="{{ old('email') }}" required>
            </label>
        </div>
        <div class="grid-2">
            <label>Password
                <input type="password" name="password" required minlength="8">
            </label>
            <label>Confirm password
                <input type="password" name="password_confirmation" required minlength="8">
            </label>
        </div>
        <label>Assigned region
            <select name="region" required>
                <option value="">Select region</option>
                @foreach($regions as $region)
                    <option value="{{ $region }}" @selected(old('region') === $region)>{{ $region }}</option>
                @endforeach
            </select>
        </label>
        <button class="btn" type="submit">Create officer</button>
    </form>
</div>

<div class="card">
    <h2>Officers on the system</h2>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Region</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse($officers as $officer)
                <tr>
                    <td colspan="3">
                        <form method="post" action="{{ route('officers.update', $officer) }}" class="row-actions">
                            @csrf
                            @method('PUT')
                            <input name="name" value="{{ $officer->name }}" required style="min-width:160px">
                            <span class="muted" style="align-self:center">{{ $officer->email }}</span>
                            <select name="region" required>
                                @foreach($regions as $region)
                                    <option value="{{ $region }}" @selected($officer->region === $region)>{{ $region }}</option>
                                @endforeach
                            </select>
                            <button class="btn" type="submit">Save</button>
                        </form>
                    </td>
                    <td>
                        <form method="post" action="{{ route('officers.destroy', $officer) }}" onsubmit="return confirm('Remove {{ $officer->name }}? They will not be able to sign in.')">
                            @csrf
                            @method('DELETE')
                            <button class="btn danger" type="submit">Remove</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">No field officers yet. Create one above.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
