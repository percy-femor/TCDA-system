<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('region')->nullable()->after('organization_id');
        });

        DB::table('users')->where('email', 'officer@tcda.gov.gh')->update(['region' => 'Western']);
        DB::table('users')->where('email', 'agg@western.palm.gh')->update(['region' => 'Western']);
        DB::table('users')->where('email', 'mill@benso.palm.gh')->update(['region' => 'Western']);

        $tcdaId = DB::table('organizations')->where('public_id', 'ORG-00001')->value('id');
        if ($tcdaId && ! DB::table('users')->where('email', 'officer.bono@tcda.gov.gh')->exists()) {
            DB::table('users')->insert([
                'name' => 'Field officer (Bono)',
                'email' => 'officer.bono@tcda.gov.gh',
                'password' => Illuminate\Support\Facades\Hash::make('password'),
                'role' => 'field_officer',
                'organization_id' => $tcdaId,
                'region' => 'Bono',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('region');
        });
    }
};
