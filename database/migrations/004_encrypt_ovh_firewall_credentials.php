<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected array $columns = ['application_secret', 'consumer_key'];

    /**
     * Run the migrations.
     * Encrypted payloads exceed 255 chars, so columns become TEXT first.
     */
    public function up(): void
    {
        Schema::table('ovh_firewall_settings', function (Blueprint $table) {
            $table->text('application_secret')->nullable()->change();
            $table->text('consumer_key')->nullable()->change();
        });

        DB::table('ovh_firewall_settings')->orderBy('id')->get()->each(function ($row) {
            $updates = [];

            foreach ($this->columns as $column) {
                $value = $row->{$column};

                if ($value === null || $value === '' || $this->isEncrypted($value)) {
                    continue;
                }

                $updates[$column] = Crypt::encryptString($value);
            }

            if ($updates !== []) {
                DB::table('ovh_firewall_settings')->where('id', $row->id)->update($updates);
            }
        });

        Cache::forget('firewall.settings');
        Cache::forget('firewall.stats');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('ovh_firewall_settings')->orderBy('id')->get()->each(function ($row) {
            $updates = [];

            foreach ($this->columns as $column) {
                $value = $row->{$column};

                if ($value !== null && $value !== '' && $this->isEncrypted($value)) {
                    $updates[$column] = Crypt::decryptString($value);
                }
            }

            if ($updates !== []) {
                DB::table('ovh_firewall_settings')->where('id', $row->id)->update($updates);
            }
        });

        Cache::forget('firewall.settings');
        Cache::forget('firewall.stats');
    }

    protected function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
};
