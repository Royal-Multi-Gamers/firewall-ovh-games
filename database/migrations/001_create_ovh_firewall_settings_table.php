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
        Schema::create('ovh_firewall_settings', function (Blueprint $table) {
            $table->id();
            $table->string('application_key')->nullable();
            $table->string('application_secret')->nullable();
            $table->string('endpoint')->default('ovh-eu');
            $table->string('consumer_key')->nullable();
            $table->boolean('sync_enabled')->default(true);
            $table->integer('sync_interval')->default(5)->comment('Sync interval in minutes');
            $table->boolean('sync_on_events')->default(true);
            $table->string('default_protocol')->default('other');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ovh_firewall_settings');
    }
};
