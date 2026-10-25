<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('device_id');
            $table->string('device_name')->nullable();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('last_pull_at')->nullable();
            $table->timestamp('last_push_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'device_id']);
        });

        Schema::create('sync_operations', function (Blueprint $table) {
            $table->id();
            $table->uuid('op_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('device_id');
            $table->string('type');
            $table->uuid('client_uuid')->nullable();
            $table->json('payload');
            $table->string('status'); // applied, rejected
            $table->string('reject_reason')->nullable();
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('public_id')->nullable();
            $table->timestamp('happened_at');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });

        foreach (['farmers', 'plots', 'batches'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->uuid('client_uuid')->nullable()->after('public_id');
            });
        }
        Schema::table('batch_members', function (Blueprint $table) {
            $table->uuid('client_uuid')->nullable()->after('id');
        });

        foreach (['farmers', 'plots', 'batches', 'batch_members'] as $tableName) {
            $rows = DB::table($tableName)->whereNull('client_uuid')->get(['id']);
            foreach ($rows as $row) {
                DB::table($tableName)->where('id', $row->id)->update([
                    'client_uuid' => (string) Str::uuid(),
                ]);
            }
        }

        foreach (['farmers', 'plots', 'batches', 'batch_members'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unique('client_uuid');
            });
        }
    }

    public function down(): void
    {
        foreach (['farmers', 'plots', 'batches', 'batch_members'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropUnique(['client_uuid']);
                $table->dropColumn('client_uuid');
            });
        }
        Schema::dropIfExists('sync_operations');
        Schema::dropIfExists('device_tokens');
    }
};
