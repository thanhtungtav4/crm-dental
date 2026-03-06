<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            if (! Schema::hasColumn('patients', 'phone_search_hash')) {
                $table->string('phone_search_hash', 64)->nullable()->after('phone');
                $table->index(['first_branch_id', 'phone_search_hash'], 'patients_branch_phone_hash_idx');
            }

            if (! Schema::hasColumn('patients', 'email_search_hash')) {
                $table->string('email_search_hash', 64)->nullable()->after('email');
                $table->index('email_search_hash', 'patients_email_hash_idx');
            }
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->text('phone')->nullable()->change();
            $table->text('email')->nullable()->change();
            $table->text('cccd')->nullable()->change();
        });

        DB::table('patients')
            ->select(['id', 'phone', 'email', 'address', 'cccd'])
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $plainPhone = $this->plainValue($row->phone);
                    $plainEmail = $this->plainValue($row->email);
                    $plainAddress = $this->plainValue($row->address);
                    $plainCccd = $this->plainValue($row->cccd);

                    DB::table('patients')
                        ->where('id', $row->id)
                        ->update([
                            'phone' => $this->encryptNullable($plainPhone),
                            'email' => $this->encryptNullable($plainEmail),
                            'address' => $this->encryptNullable($plainAddress),
                            'cccd' => $this->encryptNullable($plainCccd),
                            'phone_search_hash' => $this->hashPhone($plainPhone),
                            'email_search_hash' => $this->hashEmail($plainEmail),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            if (Schema::hasColumn('patients', 'phone_search_hash')) {
                $table->dropIndex('patients_branch_phone_hash_idx');
                $table->dropColumn('phone_search_hash');
            }

            if (Schema::hasColumn('patients', 'email_search_hash')) {
                $table->dropIndex('patients_email_hash_idx');
                $table->dropColumn('email_search_hash');
            }
        });
    }

    protected function plainValue(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return trim($value);
        }
    }

    protected function encryptNullable(?string $value): ?string
    {
        return $value === null || trim($value) === ''
            ? null
            : Crypt::encryptString($value);
    }

    protected function hashPhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        if ($digits === '') {
            return null;
        }

        if (Str::startsWith($digits, '84')) {
            $digits = '0'.substr($digits, 2);
        }

        return hash('sha256', 'patient-phone|'.$digits);
    }

    protected function hashEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $normalized = Str::lower(trim($email));

        return $normalized === ''
            ? null
            : hash('sha256', 'patient-email|'.$normalized);
    }
};
