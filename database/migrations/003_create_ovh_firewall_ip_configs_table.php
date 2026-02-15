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
        Schema::create('ovh_firewall_ip_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('node_id')->nullable()->comment('Reference to nodes table');
            $table->string('panel_ip')->unique()->comment('IP address from Pelican Panel node');
            $table->string('ovh_ip')->comment('OVH IP address for firewall API');
            $table->string('ovh_ip_on_game')->comment('OVH IP on Game identifier');
            $table->boolean('enabled')->default(true);
            $table->text('notes')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index(['panel_ip', 'enabled']);
            $table->index('node_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ovh_firewall_ip_configs');
    }
};
