<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('patient_medical_records', function (Blueprint $table) {
            $table->text('insurance_provider')->nullable()->comment('BHYT / Bảo hiểm tư nhân')->change();
            $table->text('insurance_number')->nullable()->change();
            $table->text('emergency_contact_name')->nullable()->change();
            $table->text('emergency_contact_phone')->nullable()->change();
            $table->text('emergency_contact_email')->nullable()->change();
            $table->text('emergency_contact_relationship')->nullable()->comment('Vợ/Chồng/Con/Bố/Mẹ/Anh/Chị')->change();
        });

        DB::table('patient_medical_records')
            ->select([
                'id',
                'insurance_provider',
                'insurance_number',
                'emergency_contact_name',
                'emergency_contact_phone',
                'emergency_contact_email',
                'emergency_contact_relationship',
            ])
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('patient_medical_records')
                        ->where('id', $row->id)
                        ->update([
                            'insurance_provider' => $this->encryptNullable($this->plainValue($row->insurance_provider)),
                            'insurance_number' => $this->encryptNullable($this->plainValue($row->insurance_number)),
                            'emergency_contact_name' => $this->encryptNullable($this->plainValue($row->emergency_contact_name)),
                            'emergency_contact_phone' => $this->encryptNullable($this->plainValue($row->emergency_contact_phone)),
                            'emergency_contact_email' => $this->encryptNullable($this->plainValue($row->emergency_contact_email)),
                            'emergency_contact_relationship' => $this->encryptNullable($this->plainValue($row->emergency_contact_relationship)),
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
        DB::table('patient_medical_records')
            ->select([
                'id',
                'insurance_provider',
                'insurance_number',
                'emergency_contact_name',
                'emergency_contact_phone',
                'emergency_contact_email',
                'emergency_contact_relationship',
            ])
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('patient_medical_records')
                        ->where('id', $row->id)
                        ->update([
                            'insurance_provider' => $this->decryptNullable($row->insurance_provider),
                            'insurance_number' => $this->decryptNullable($row->insurance_number),
                            'emergency_contact_name' => $this->decryptNullable($row->emergency_contact_name),
                            'emergency_contact_phone' => $this->decryptNullable($row->emergency_contact_phone),
                            'emergency_contact_email' => $this->decryptNullable($row->emergency_contact_email),
                            'emergency_contact_relationship' => $this->decryptNullable($row->emergency_contact_relationship),
                            'updated_at' => now(),
                        ]);
                }
            });

        Schema::table('patient_medical_records', function (Blueprint $table) {
            $table->string('insurance_provider')->nullable()->comment('BHYT / Bảo hiểm tư nhân')->change();
            $table->string('insurance_number')->nullable()->change();
            $table->string('emergency_contact_name')->nullable()->change();
            $table->string('emergency_contact_phone')->nullable()->change();
            $table->string('emergency_contact_email')->nullable()->change();
            $table->string('emergency_contact_relationship')->nullable()->comment('Vợ/Chồng/Con/Bố/Mẹ/Anh/Chị')->change();
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

    protected function decryptNullable(mixed $value): ?string
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
};
