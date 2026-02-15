<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ovh_firewall_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('ip')->index();
            $table->string('ip_on_game')->nullable();
            $table->enum('action', ['add', 'update', 'delete', 'sync'])->index();
            $table->enum('status', ['success', 'failed', 'pending'])->default('pending')->index();
            $table->integer('port')->nullable();
            $table->string('protocol')->nullable();
            $table->integer('rule_id')->nullable()->comment('OVH firewall rule ID');
            $table->text('message')->nullable();
            $table->json('details')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['created_at', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ovh_firewall_sync_logs');
    }
};
