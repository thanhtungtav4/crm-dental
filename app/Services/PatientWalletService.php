<?php

namespace App\Services;

use App\Models\PatientWallet;
use App\Models\Payment;
use App\Models\WalletLedgerEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PatientWalletService
{
    public function postPayment(Payment $payment): void
    {
        $invoice = $payment->invoice;
        $patient = $invoice?->patient;

        if (! $invoice || ! $patient) {
            return;
        }

        $entryDefinition = $this->resolveEntryFromPayment($payment);
        if ($entryDefinition === null) {
            return;
        }

        DB::transaction(function () use ($payment, $invoice, $patient, $entryDefinition): void {
            $wallet = PatientWallet::query()->firstOrCreate(
                ['patient_id' => $patient->id],
                ['branch_id' => $payment->branch_id ?? $invoice->branch_id, 'balance' => 0]
            );

            $existingEntry = WalletLedgerEntry::query()
                ->where('payment_id', $payment->id)
                ->where('entry_type', $entryDefinition['entry_type'])
                ->first();

            if ($existingEntry) {
                return;
            }

            $amount = abs((float) $payment->amount);
            $balanceBefore = (float) $wallet->balance;
            $balanceAfter = $entryDefinition['direction'] === WalletLedgerEntry::DIRECTION_CREDIT
                ? $balanceBefore + $amount
                : $balanceBefore - $amount;

            if ($balanceAfter < 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Số dư ví không đủ để ghi nhận giao dịch này.',
                ]);
            }

            WalletLedgerEntry::query()->create([
                'patient_wallet_id' => $wallet->id,
                'patient_id' => $patient->id,
                'branch_id' => $payment->branch_id ?? $invoice->branch_id,
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'entry_type' => $entryDefinition['entry_type'],
                'direction' => $entryDefinition['direction'],
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_no' => $payment->transaction_ref ?: $invoice->invoice_no,
                'note' => $entryDefinition['note'],
                'metadata' => [
                    'payment_direction' => $payment->direction,
                    'payment_source' => $payment->payment_source,
                    'is_deposit' => (bool) $payment->is_deposit,
                ],
                'created_by' => $payment->received_by,
            ]);

            $wallet->balance = $balanceAfter;

            if ($entryDefinition['entry_type'] === 'deposit') {
                $wallet->total_deposit = (float) $wallet->total_deposit + $amount;
            }

            if ($entryDefinition['entry_type'] === 'spend') {
                $wallet->total_spent = (float) $wallet->total_spent + $amount;
            }

            if ($entryDefinition['entry_type'] === 'refund') {
                $wallet->total_refunded = (float) $wallet->total_refunded + $amount;
            }

            $wallet->save();
        }, 3);
    }

    public function adjustBalance(PatientWallet $wallet, float $amount, string $note, ?int $actorId = null): WalletLedgerEntry
    {
        return DB::transaction(function () use ($wallet, $amount, $note, $actorId): WalletLedgerEntry {
            $wallet->refresh();

            $balanceBefore = (float) $wallet->balance;
            $balanceAfter = $balanceBefore + $amount;

            if ($balanceAfter < 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Không thể điều chỉnh làm số dư âm.',
                ]);
            }

            $entry = WalletLedgerEntry::query()->create([
                'patient_wallet_id' => $wallet->id,
                'patient_id' => $wallet->patient_id,
                'branch_id' => $wallet->branch_id,
                'entry_type' => 'adjustment',
                'direction' => $amount >= 0 ? WalletLedgerEntry::DIRECTION_CREDIT : WalletLedgerEntry::DIRECTION_DEBIT,
                'amount' => abs($amount),
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'note' => $note,
                'created_by' => $actorId ?? auth()->id(),
            ]);

            $wallet->balance = $balanceAfter;
            $wallet->save();

            return $entry;
        }, 3);
    }

    /**
     * @return array{entry_type:string,direction:string,note:string}|null
     */
    protected function resolveEntryFromPayment(Payment $payment): ?array
    {
        if ($payment->reversal_of_id !== null) {
            $originalDirection = WalletLedgerEntry::query()
                ->where('payment_id', (int) $payment->reversal_of_id)
                ->value('direction');

            if (is_string($originalDirection)) {
                return [
                    'entry_type' => 'reversal',
                    'direction' => $originalDirection === WalletLedgerEntry::DIRECTION_CREDIT
                        ? WalletLedgerEntry::DIRECTION_DEBIT
                        : WalletLedgerEntry::DIRECTION_CREDIT,
                    'note' => 'Đảo phiếu thanh toán #'.$payment->reversal_of_id,
                ];
            }
        }

        if ($payment->is_deposit && $payment->direction === 'receipt') {
            return [
                'entry_type' => 'deposit',
                'direction' => WalletLedgerEntry::DIRECTION_CREDIT,
                'note' => 'Nạp cọc từ phiếu thu',
            ];
        }

        if ($payment->is_deposit && $payment->direction === 'refund') {
            return [
                'entry_type' => 'refund',
                'direction' => WalletLedgerEntry::DIRECTION_DEBIT,
                'note' => 'Hoàn cọc từ phiếu hoàn',
            ];
        }

        if ($payment->payment_source === 'wallet' && $payment->direction === 'receipt') {
            return [
                'entry_type' => 'spend',
                'direction' => WalletLedgerEntry::DIRECTION_DEBIT,
                'note' => 'Khấu trừ ví để thanh toán hóa đơn',
            ];
        }

        if ($payment->payment_source === 'wallet' && $payment->direction === 'refund') {
            return [
                'entry_type' => 'refund',
                'direction' => WalletLedgerEntry::DIRECTION_CREDIT,
                'note' => 'Hoàn tiền về ví',
            ];
        }

        return null;
    }
}
