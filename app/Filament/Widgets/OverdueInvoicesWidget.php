<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class OverdueInvoicesWidget extends BaseWidget
{
    protected static ?int $sort = 5;
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?string $heading = 'Hóa đơn quá hạn cần xử lý';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Invoice::query()
                    ->overdue()
                    ->with(['patient', 'plan'])
                    ->orderBy('due_date', 'asc')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('invoice_no')
                    ->label('Số HĐ')
                    ->searchable()
                    ->weight('bold')
                    ->color('danger'),
                
                TextColumn::make('patient.full_name')
                    ->label('Bệnh nhân')
                    ->searchable()
                    ->description(fn ($record) => $record->patient?->phone),
                
                TextColumn::make('total_amount')
                    ->label('Tổng tiền')
                    ->money('VND')
                    ->weight('bold'),
                
                TextColumn::make('balance')
                    ->label('Còn lại')
                    ->formatStateUsing(fn ($record) => number_format($record->calculateBalance(), 0, ',', '.') . 'đ')
                    ->weight('bold')
                    ->color('danger'),
                
                TextColumn::make('due_date')
                    ->label('Ngày đến hạn')
                    ->date('d/m/Y')
                    ->description(function ($record) {
                        $days = $record->getDaysOverdue();
                        return "Quá hạn {$days} ngày";
                    })
                    ->color('danger'),
                
                BadgeColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn ($record) => $record->getPaymentStatusLabel())
                    ->color(fn ($record) => $record->getStatusBadgeColor())
                    ->icon(Heroicon::OutlinedExclamationCircle),
            ])
            ->actions([
                Action::make('view')
                    ->label('Xem')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn ($record) => route('filament.admin.resources.invoices.edit', ['record' => $record->id])),
                
                Action::make('record_payment')
                    ->label('Thanh toán')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('success')
                    ->url(fn ($record) => route('filament.admin.resources.invoices.edit', ['record' => $record->id])),
            ])
            ->emptyStateHeading('Không có hóa đơn quá hạn')
            ->emptyStateDescription('Tất cả hóa đơn đều được thanh toán đúng hạn')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->heading(function () {
                $count = Invoice::overdue()->count();
                $total = Invoice::overdue()
                    ->get()
                    ->sum(fn($invoice) => $invoice->calculateBalance());
                
                return "Hóa đơn quá hạn ({$count} hóa đơn, nợ: " . number_format($total, 0, ',', '.') . 'đ)';
            });
    }
}
