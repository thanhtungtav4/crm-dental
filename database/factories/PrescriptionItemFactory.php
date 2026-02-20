<?php

namespace Database\Factories;

use App\Models\Prescription;
use App\Models\PrescriptionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PrescriptionItem>
 */
class PrescriptionItemFactory extends Factory
{
    protected $model = PrescriptionItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $medications = [
            'Amoxicillin' => ['500mg', '250mg'],
            'Paracetamol' => ['500mg', '325mg'],
            'Ibuprofen' => ['400mg', '200mg'],
            'Metronidazole' => ['500mg', '250mg'],
            'Clindamycin' => ['300mg', '150mg'],
            'Cephalexin' => ['500mg', '250mg'],
            'Tramadol' => ['50mg', '100mg'],
            'Meloxicam' => ['7.5mg', '15mg'],
            'Chlorhexidine' => ['0.12%'],
            'Prednisolone' => ['5mg', '10mg'],
        ];

        $medication = fake()->randomElement(array_keys($medications));
        $dosage = fake()->randomElement($medications[$medication]);
        $units = array_keys(PrescriptionItem::getUnits());
        $instructions = PrescriptionItem::getCommonInstructions();

        return [
            'prescription_id' => Prescription::factory(),
            'medication_name' => $medication,
            'dosage' => $dosage,
            'quantity' => fake()->numberBetween(5, 30),
            'unit' => fake()->randomElement($units),
            'instructions' => fake()->randomElement($instructions),
            'duration' => fake()->randomElement(['3 ngày', '5 ngày', '7 ngày', '10 ngày', '14 ngày']),
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }

    /**
     * Indicate that the item is for a specific prescription.
     */
    public function forPrescription(Prescription $prescription): static
    {
        return $this->state(fn(array $attributes) => [
            'prescription_id' => $prescription->id,
        ]);
    }

    /**
     * Indicate antibiotic medication.
     */
    public function antibiotic(): static
    {
        return $this->state(fn(array $attributes) => [
            'medication_name' => 'Amoxicillin',
            'dosage' => '500mg',
            'quantity' => 21,
            'unit' => 'viên',
            'instructions' => 'Ngày uống 3 lần, sáng - trưa - tối',
            'duration' => '7 ngày',
        ]);
    }

    /**
     * Indicate pain medication.
     */
    public function painkiller(): static
    {
        return $this->state(fn(array $attributes) => [
            'medication_name' => 'Paracetamol',
            'dosage' => '500mg',
            'quantity' => 10,
            'unit' => 'viên',
            'instructions' => 'Uống khi đau',
            'duration' => '5 ngày',
        ]);
    }
}
