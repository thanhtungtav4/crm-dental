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
            'branch_id' => $invoice->branch_id,
            'amount' => fake()->numberBetween(100_000, (int) $invoice->total_amount),
            'method' => fake()->randomElement(['cash', 'card', 'transfer', 'vnpay', 'other']),
            'paid_at' => fake()->dateTimeBetween('-3 days', 'now'),
            'received_by' => $receiver->id,
            'note' => fake()->randomElement(['Thanh toán đợt 1', 'Thanh toán đủ', 'Thanh toán chuyển khoản', 'Giảm giá khuyến mãi']),
        ];
    }
}
