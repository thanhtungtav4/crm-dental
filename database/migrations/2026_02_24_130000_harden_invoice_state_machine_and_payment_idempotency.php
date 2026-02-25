<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $this->migrateInvoiceStatusEnum();
        $this->normalizeAndProtectPaymentTransactionRef();
        $this->syncInvoiceFinancialState();
        $this->addInvoiceStatusIndex();
    }

    public function down(): void
    {
        $this->downgradeOverdueToIssuedOrPartial();
        $this->dropInvoiceStatusIndex();
        $this->dropPaymentTransactionRefUniqueIndex();
        $this->rollbackInvoiceStatusEnum();
    }

    private function migrateInvoiceStatusEnum(): void
    {
        if (! Schema::hasTable('invoices') || ! Schema::hasColumn('invoices', 'status')) {
            return;
        }

        if (! $this->isMysql()) {
            return;
        }

        DB::statement("
            ALTER TABLE `invoices`
            MODIFY `status` ENUM('draft', 'issued', 'partial', 'paid', 'overdue', 'cancelled')
            NOT NULL DEFAULT 'draft'
        ");
    }

    private function rollbackInvoiceStatusEnum(): void
    {
        if (! Schema::hasTable('invoices') || ! Schema::hasColumn('invoices', 'status')) {
            return;
        }

        if (! $this->isMysql()) {
            return;
        }

        DB::statement("
            ALTER TABLE `invoices`
            MODIFY `status` ENUM('draft', 'issued', 'partial', 'paid', 'cancelled')
            NOT NULL DEFAULT 'draft'
        ");
    }

    private function normalizeAndProtectPaymentTransactionRef(): void
    {
        if (! Schema::hasTable('payments') || ! Schema::hasColumn('payments', 'transaction_ref')) {
            return;
        }

        DB::table('payments')
            ->whereNotNull('transaction_ref')
            ->orderBy('id')
            ->chunkById(300, function (Collection $payments): void {
                foreach ($payments as $payment) {
                    $normalizedRef = trim((string) $payment->transaction_ref);
                    $normalizedRef = $normalizedRef === '' ? null : $normalizedRef;

                    if ($normalizedRef === $payment->transaction_ref) {
                        continue;
                    }

                    DB::table('payments')
                        ->where('id', $payment->id)
                        ->update(['transaction_ref' => $normalizedRef]);
                }
            });

        $duplicateRefs = DB::table('payments')
            ->select('invoice_id', 'transaction_ref', DB::raw('COUNT(*) as aggregate'))
            ->whereNotNull('transaction_ref')
            ->groupBy('invoice_id', 'transaction_ref')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateRefs as $duplicateRef) {
            $duplicatedIds = DB::table('payments')
                ->where('invoice_id', $duplicateRef->invoice_id)
                ->where('transaction_ref', $duplicateRef->transaction_ref)
                ->orderBy('id')
                ->pluck('id')
                ->all();

            $keepFirst = true;
            foreach ($duplicatedIds as $paymentId) {
                if ($keepFirst) {
                    $keepFirst = false;

                    continue;
                }

                DB::table('payments')
                    ->where('id', $paymentId)
                    ->update([
                        'transaction_ref' => $duplicateRef->transaction_ref . '-legacy-' . $paymentId,
                    ]);
            }
        }

        $indexName = 'payments_invoice_id_transaction_ref_unique';
        if ($this->indexExists('payments', $indexName)) {
            return;
        }

        Schema::table('payments', function (Blueprint $table): void {
            $table->unique(['invoice_id', 'transaction_ref'], 'payments_invoice_id_transaction_ref_unique');
        });
    }

    private function dropPaymentTransactionRefUniqueIndex(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        $indexName = 'payments_invoice_id_transaction_ref_unique';
        if ($this->isMysql() && ! $this->indexExists('payments', $indexName)) {
            return;
        }

        try {
            Schema::table('payments', function (Blueprint $table): void {
                $table->dropUnique('payments_invoice_id_transaction_ref_unique');
            });
        } catch (QueryException) {
            // Ignore missing index errors for cross-environment compatibility.
        }
    }

    private function syncInvoiceFinancialState(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        $today = CarbonImmutable::today();
        $now = CarbonImmutable::now();

        DB::table('invoices')
            ->select(['id', 'status', 'total_amount', 'due_date', 'paid_at'])
            ->orderBy('id')
            ->chunkById(200, function (Collection $invoices) use ($today, $now): void {
                $invoiceIds = $invoices->pluck('id')->all();

                $paidTotalsByInvoiceId = DB::table('payments')
                    ->select('invoice_id', DB::raw('COALESCE(SUM(amount), 0) as total_paid'))
                    ->whereIn('invoice_id', $invoiceIds)
                    ->groupBy('invoice_id')
                    ->pluck('total_paid', 'invoice_id');

                foreach ($invoices as $invoice) {
                    $paidAmount = round((float) ($paidTotalsByInvoiceId[$invoice->id] ?? 0), 2);
                    $totalAmount = round((float) $invoice->total_amount, 2);
                    $status = (string) $invoice->status;
                    $paidAt = $invoice->paid_at;

                    if ($status === 'cancelled') {
                        DB::table('invoices')
                            ->where('id', $invoice->id)
                            ->update(['paid_amount' => $paidAmount]);

                        continue;
                    }

                    if ($totalAmount <= 0 || $paidAmount >= $totalAmount) {
                        $nextStatus = 'paid';
                        $nextPaidAt = $paidAt ?: $now;
                    } elseif ($status === 'draft') {
                        $nextStatus = 'draft';
                        $nextPaidAt = null;
                    } else {
                        $isOverdue = false;
                        if ($invoice->due_date) {
                            $dueDate = CarbonImmutable::parse((string) $invoice->due_date)->startOfDay();
                            $isOverdue = $today->greaterThan($dueDate);
                        }

                        if ($isOverdue) {
                            $nextStatus = 'overdue';
                        } elseif ($paidAmount > 0) {
                            $nextStatus = 'partial';
                        } else {
                            $nextStatus = 'issued';
                        }

                        $nextPaidAt = null;
                    }

                    DB::table('invoices')
                        ->where('id', $invoice->id)
                        ->update([
                            'paid_amount' => $paidAmount,
                            'status' => $nextStatus,
                            'paid_at' => $nextPaidAt,
                        ]);
                }
            });
    }

    private function downgradeOverdueToIssuedOrPartial(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        DB::table('invoices')
            ->where('status', 'overdue')
            ->orderBy('id')
            ->chunkById(200, function (Collection $invoices): void {
                foreach ($invoices as $invoice) {
                    $paidAmount = round((float) $invoice->paid_amount, 2);
                    $totalAmount = round((float) $invoice->total_amount, 2);

                    if ($totalAmount <= 0 || $paidAmount >= $totalAmount) {
                        $status = 'paid';
                    } elseif ($paidAmount > 0) {
                        $status = 'partial';
                    } else {
                        $status = 'issued';
                    }

                    DB::table('invoices')
                        ->where('id', $invoice->id)
                        ->update(['status' => $status]);
                }
            });
    }

    private function addInvoiceStatusIndex(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        $indexName = 'invoices_status_due_date_index';
        if ($this->indexExists('invoices', $indexName)) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table): void {
            $table->index(['status', 'due_date'], 'invoices_status_due_date_index');
        });
    }

    private function dropInvoiceStatusIndex(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        $indexName = 'invoices_status_due_date_index';
        if ($this->isMysql() && ! $this->indexExists('invoices', $indexName)) {
            return;
        }

        try {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->dropIndex('invoices_status_due_date_index');
            });
        } catch (QueryException) {
            // Ignore missing index errors for cross-environment compatibility.
        }
    }

    private function isMysql(): bool
    {
        return Schema::getConnection()->getDriverName() === 'mysql';
    }

    private function indexExists(string $table, string $indexName): bool
    {
        if (! $this->isMysql()) {
            return false;
        }

        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);

        return $indexes !== [];
    }
};

