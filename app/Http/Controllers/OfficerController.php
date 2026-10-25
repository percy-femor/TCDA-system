<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\GhanaRegions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OfficerController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isTcda(), 403);

        return view('officers.index', [
            'officers' => User::query()
                ->where('role', UserRole::FieldOfficer)
                ->orderBy('name')
                ->get(),
            'regions' => GhanaRegions::all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isTcda(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'region' => ['required', GhanaRegions::rule()],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => UserRole::FieldOfficer,
            'organization_id' => $request->user()->organization_id,
            'region' => $data['region'],
        ]);

        return back()->with('status', $data['name'].' can now sign in as a field officer for '.$data['region'].'.');
    }

    public function update(Request $request, User $officer): RedirectResponse
    {
        abort_unless($request->user()->isTcda(), 403);
        abort_unless($officer->role === UserRole::FieldOfficer, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'region' => ['required', GhanaRegions::rule()],
        ]);

        $officer->update([
            'name' => $data['name'],
            'region' => $data['region'],
        ]);

        return back()->with('status', $officer->name.' is assigned to '.$data['region'].'.');
    }

    public function destroy(Request $request, User $officer): RedirectResponse
    {
        abort_unless($request->user()->isTcda(), 403);
        abort_unless($officer->role === UserRole::FieldOfficer, 404);
        abort_if($officer->is($request->user()), 403, 'You cannot remove your own account.');

        $name = $officer->name;
        $officer->delete();

        return back()->with('status', $name.' was removed from the registry.');
    }
}
