<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        $invoice = Invoice::inRandomOrder()->first() ?? Invoice::factory()->create();
        $receiver = User::inRandomOrder()->first() ?? User::factory()->create();
        return [
            'invoice_id' => $invoice->id,
            'amount' => $this->faker->numberBetween(100_000, (int) $invoice->total_amount),
            'method' => $this->faker->randomElement(['cash','card','transfer','momo']),
            'paid_at' => $this->faker->dateTimeBetween('-3 days', 'now'),
            'received_by' => $receiver->id,
            'note' => $this->faker->randomElement(['Thanh toán đợt 1','Thanh toán đủ','Thanh toán chuyển khoản','Giảm giá khuyến mãi']),
        ];
    }
}
