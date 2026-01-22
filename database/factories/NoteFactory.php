<?php

namespace Database\Factories;

use App\Models\Note;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NoteFactory extends Factory
{
    protected $model = Note::class;

    public function definition(): array
    {
        $patient = Patient::inRandomOrder()->first() ?? Patient::factory()->create();
        $user = User::inRandomOrder()->first() ?? User::factory()->create();
        return [
            'patient_id' => $patient->id,
            'user_id' => $user->id,
            'type' => $this->faker->randomElement(['general','behavior','complaint','preference']),
            'content' => $this->faker->randomElement([
                'Bệnh nhân sợ đau, cần gây tê đủ.',
                'Ưu tiên lịch chiều thứ 7.',
                'Đề nghị tư vấn thêm về implant.',
                'Dặn dò vệ sinh răng miệng kỹ.',
            ]),
        ];
    }
}
