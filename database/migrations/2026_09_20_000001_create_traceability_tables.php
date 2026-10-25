<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->unique();
            $table->string('name');
            $table->string('type'); // regulator, aggregator, mill, exporter
            $table->string('chain')->nullable(); // palm, cashew, both
            $table->string('region')->nullable();
            $table->string('district')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('role')->constrained()->nullOnDelete();
        });

        Schema::create('farmers', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->unique();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('community');
            $table->string('district');
            $table->string('region');
            $table->string('chain'); // palm | cashew
            $table->string('paper_card_no')->nullable();
            $table->timestamp('consent_at')->nullable();
            $table->foreignId('enrolled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('aggregator_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('plots', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->unique();
            $table->foreignId('farmer_id')->constrained()->cascadeOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('geojson')->nullable();
            $table->decimal('area_hectares', 8, 3)->nullable();
            $table->string('land_status')->default('unknown'); // documented, customary, unknown
            $table->timestamp('mapped_at')->nullable();
            $table->foreignId('mapped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->string('public_id')->unique();
            $table->string('chain');
            $table->string('status')->default('open'); // open, closed, mill_received, exported
            $table->string('capture_channel')->default('desk'); // paper, mobile, desk
            $table->foreignId('aggregator_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('mill_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('exporter_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->decimal('mill_weight_kg', 12, 2)->nullable();
            $table->string('shipment_ref')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('batch_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('farmer_id')->constrained()->restrictOnDelete();
            $table->foreignId('plot_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('volume_kg', 12, 2);
            $table->string('paper_stub_no')->nullable();
            $table->string('capture_channel')->default('desk');
            $table->timestamp('delivered_at');
            $table->foreignId('captured_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('custody_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->string('event_type');
            $table->json('payload')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('happened_at');
            $table->timestamps();
        });

        Schema::create('compliance_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status'); // ready, gaps
            $table->json('gaps');
            $table->timestamp('assessed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_assessments');
        Schema::dropIfExists('custody_events');
        Schema::dropIfExists('batch_members');
        Schema::dropIfExists('batches');
        Schema::dropIfExists('plots');
        Schema::dropIfExists('farmers');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
        });
        Schema::dropIfExists('organizations');
    }
};
