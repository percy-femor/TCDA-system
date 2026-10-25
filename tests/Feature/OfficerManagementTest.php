<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_tcda_can_create_an_officer_for_a_region(): void
    {
        $this->seed();
        $tcda = User::query()->where('email', 'tcda@tcda.gov.gh')->firstOrFail();

        $this->actingAs($tcda)
            ->post(route('officers.store'), [
                'name' => 'New Officer',
                'email' => 'officer.volta@tcda.gov.gh',
                'password' => 'secret123',
                'password_confirmation' => 'secret123',
                'region' => 'Volta',
            ])
            ->assertRedirect();

        $officer = User::query()->where('email', 'officer.volta@tcda.gov.gh')->firstOrFail();
        $this->assertSame(UserRole::FieldOfficer, $officer->role);
        $this->assertSame('Volta', $officer->region);

        $this->post('/logout');
        $this->post('/login', [
            'email' => 'officer.volta@tcda.gov.gh',
            'password' => 'secret123',
        ])->assertRedirect(route('officer.home'));
    }

    public function test_tcda_can_reassign_and_remove_an_officer(): void
    {
        $this->seed();
        $tcda = User::query()->where('email', 'tcda@tcda.gov.gh')->firstOrFail();
        $officer = User::query()->where('email', 'officer@tcda.gov.gh')->firstOrFail();

        $this->actingAs($tcda)
            ->put(route('officers.update', $officer), [
                'name' => 'Western lead',
                'region' => 'Western North',
            ])
            ->assertRedirect();

        $this->assertSame('Western North', $officer->fresh()->region);
        $this->assertSame('Western lead', $officer->fresh()->name);

        $this->actingAs($tcda)
            ->delete(route('officers.destroy', $officer))
            ->assertRedirect();

        $this->assertDatabaseMissing('users', ['email' => 'officer@tcda.gov.gh']);
    }

    public function test_a_field_officer_cannot_manage_officers(): void
    {
        $this->seed();
        $officer = User::query()->where('email', 'officer@tcda.gov.gh')->firstOrFail();

        $this->actingAs($officer)->get(route('officers.index'))->assertForbidden();
        $this->actingAs($officer)->post(route('officers.store'), [
            'name' => 'Intruder',
            'email' => 'x@tcda.gov.gh',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'region' => 'Ashanti',
        ])->assertForbidden();
    }
}
