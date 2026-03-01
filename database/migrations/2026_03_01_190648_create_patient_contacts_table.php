<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('patient_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('full_name');
            $table->string('relationship')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_emergency')->default(false);
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['patient_id', 'is_primary'], 'patient_contacts_primary_idx');
            $table->index(['patient_id', 'is_emergency'], 'patient_contacts_emergency_idx');
        });

        if (! Schema::hasTable('patient_medical_records')) {
            return;
        }

        DB::table('patient_medical_records')
            ->select([
                'patient_id',
                'emergency_contact_name',
                'emergency_contact_phone',
                'emergency_contact_email',
                'emergency_contact_relationship',
            ])
            ->whereNotNull('patient_id')
            ->where(function ($query): void {
                $query->whereNotNull('emergency_contact_name')
                    ->orWhereNotNull('emergency_contact_phone')
                    ->orWhereNotNull('emergency_contact_email');
            })
            ->orderBy('patient_id')
            ->chunk(200, function ($records): void {
                foreach ($records as $record) {
                    $name = trim((string) ($record->emergency_contact_name ?? ''));
                    $phone = trim((string) ($record->emergency_contact_phone ?? ''));
                    $email = trim((string) ($record->emergency_contact_email ?? ''));

                    if ($name === '' && $phone === '' && $email === '') {
                        continue;
                    }

                    $exists = DB::table('patient_contacts')
                        ->where('patient_id', (int) $record->patient_id)
                        ->where(function ($query) use ($name, $phone, $email): void {
                            if ($name !== '') {
                                $query->orWhere('full_name', $name);
                            }

                            if ($phone !== '') {
                                $query->orWhere('phone', $phone);
                            }

                            if ($email !== '') {
                                $query->orWhere('email', $email);
                            }
                        })
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('patient_contacts')->insert([
                        'patient_id' => (int) $record->patient_id,
                        'full_name' => $name !== '' ? $name : 'Người liên hệ',
                        'relationship' => trim((string) ($record->emergency_contact_relationship ?? '')) ?: null,
                        'phone' => $phone !== '' ? $phone : null,
                        'email' => $email !== '' ? $email : null,
                        'is_primary' => false,
                        'is_emergency' => true,
                        'note' => 'Backfill từ hồ sơ y tế',
                        'created_at' => now(),
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
        Schema::dropIfExists('patient_contacts');
    }
};
