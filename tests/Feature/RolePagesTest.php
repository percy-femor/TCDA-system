<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_role_lands_on_its_own_desk(): void
    {
        $this->seed();

        $this->post('/login', ['email' => 'tcda@tcda.gov.gh', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('National command')
            ->assertSee('You supervise the national registry')
            ->assertDontSee('Weighbridge')
            ->assertDontSee('Enrol a farmer today');
        $this->post('/logout');

        $this->post('/login', ['email' => 'officer@tcda.gov.gh', 'password' => 'password'])
            ->assertRedirect(route('officer.home'));
        $this->get(route('officer.home'))
            ->assertOk()
            ->assertSee('Field officer desk')
            ->assertSee('Download for this PC')
            ->assertSee('Enrol a farmer today')
            ->assertSee('Kofi Adjei')
            ->assertDontSee('Weighbridge')
            ->assertDontSee('Packs for buyers')
            ->assertDontSee('Buying-point floor');
        $this->post('/logout');

        $this->post('/login', ['email' => 'agg@western.palm.gh', 'password' => 'password'])
            ->assertRedirect(route('buying.home'));
        $this->get(route('buying.home'))
            ->assertOk()
            ->assertSee('Buying-point floor')
            ->assertSee('Open a farm-set')
            ->assertDontSee('National command')
            ->assertDontSee('Stamp intake');
        $this->post('/logout');

        $this->post('/login', ['email' => 'mill@benso.palm.gh', 'password' => 'password'])
            ->assertRedirect(route('mill.index'));
        $this->get(route('mill.index'))
            ->assertOk()
            ->assertSee('Weighbridge')
            ->assertSee('Waiting at the gate')
            ->assertSee('Stamp intake')
            ->assertDontSee('Enrol a farmer today')
            ->assertDontSee('Packs for buyers');
        $this->post('/logout');

        $this->post('/login', ['email' => 'export@nutex.gh', 'password' => 'password'])
            ->assertRedirect(route('dds.index'));
        $this->get(route('dds.index'))
            ->assertOk()
            ->assertSee('Packs for buyers')
            ->assertSee('Ready to link a bill of lading')
            ->assertDontSee('Weighbridge')
            ->assertDontSee('Enrol a farmer today');
    }

    public function test_roles_cannot_open_another_desk(): void
    {
        $this->seed();

        $officer = User::query()->where('email', 'officer@tcda.gov.gh')->firstOrFail();
        $this->actingAs($officer)->get(route('dashboard'))->assertForbidden();
        $this->actingAs($officer)->get(route('mill.index'))->assertForbidden();
        $this->actingAs($officer)->get(route('dds.index'))->assertForbidden();
        $this->actingAs($officer)->get(route('batches.index'))->assertForbidden();
        $this->actingAs($officer)->get(route('supervision'))->assertForbidden();

        $mill = User::query()->where('email', 'mill@benso.palm.gh')->firstOrFail();
        $this->actingAs($mill)->get(route('farmers.index'))->assertForbidden();
        $this->actingAs($mill)->get(route('batches.index'))->assertForbidden();
        $this->actingAs($mill)->get(route('dds.index'))->assertForbidden();

        $exporter = User::query()->where('email', 'export@nutex.gh')->firstOrFail();
        $this->actingAs($exporter)->get(route('farmers.index'))->assertForbidden();
        $this->actingAs($exporter)->get(route('mill.index'))->assertForbidden();
        $this->actingAs($exporter)->get(route('dashboard'))->assertForbidden();

        $aggregator = User::query()->where('email', 'agg@western.palm.gh')->firstOrFail();
        $this->actingAs($aggregator)->get(route('dashboard'))->assertForbidden();
        $this->actingAs($aggregator)->get(route('mill.index'))->assertForbidden();
        $this->actingAs($aggregator)->get(route('dds.index'))->assertForbidden();
        $this->actingAs($aggregator)->get(route('buying.farmers'))->assertOk()->assertSee('Farmers I buy from');
    }

    public function test_login_opens_a_desk_picker_not_one_shared_form(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Choose your desk')
            ->assertSee('/officer')
            ->assertSee('/mill')
            ->assertSee('/export');

        $this->get('/login/mill')
            ->assertOk()
            ->assertSee('Weighbridge')
            ->assertDontSee('Enrol farmers and draw farm geofences');
    }

    public function test_a_mill_account_cannot_enter_the_officer_desk(): void
    {
        $this->seed();
        $this->from('/login/officer')->post('/login/officer', [
            'email' => 'mill@benso.palm.gh',
            'password' => 'password',
        ])->assertRedirect('/login/officer')->assertSessionHasErrors('email');
    }
}
