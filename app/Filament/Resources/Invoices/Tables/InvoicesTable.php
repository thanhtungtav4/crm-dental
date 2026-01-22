<?php

namespace App\Filament\Resources\Invoices\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_no')
                    ->label('Số hoá đơn')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->patient?->full_name)
                    ->weight('bold'),
                
                TextColumn::make('patient.full_name')
                    ->label('Bệnh nhân')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                TextColumn::make('plan.title')
                    ->label('Kế hoạch điều trị')
                    ->toggleable()
                    ->limit(30),
                
                TextColumn::make('total_amount')
                    ->label('Tổng tiền')
                    ->money('VND')
                    ->sortable()
                    ->weight('bold'),
                
                TextColumn::make('payment_info')
                    ->label('Thanh toán')
                    ->formatStateUsing(function ($record) {
                        $paid = number_format($record->getTotalPaid(), 0, ',', '.');
                        $total = number_format($record->total_amount, 0, ',', '.');
                        return "{$paid}đ / {$total}đ";
                    })
                    ->description(function ($record) {
                        $progress = $record->getPaymentProgress();
                        return round($progress, 1) . '% hoàn thành';
                    })
                    ->color(function ($record) {
                        $progress = $record->getPaymentProgress();
                        if ($progress >= 100) return 'success';
                        if ($progress >= 50) return 'info';
                        if ($progress > 0) return 'warning';
                        return 'gray';
                    })
                    ->weight(fn ($record) => $record->getTotalPaid() > 0 ? 'bold' : 'normal')
                    ->sortable(query: function ($query, string $direction) {
                        return $query->orderBy('paid_amount', $direction);
                    }),
                
                TextColumn::make('balance')
                    ->label('Còn lại')
                    ->formatStateUsing(fn ($record) => number_format($record->calculateBalance(), 0, ',', '.') . 'đ')
                    ->color(fn ($record) => $record->calculateBalance() > 0 ? 'danger' : 'success')
                    ->weight(fn ($record) => $record->calculateBalance() > 0 ? 'bold' : 'normal')
                    ->sortable(query: function ($query, string $direction) {
                        return $query->selectRaw('(total_amount - COALESCE(paid_amount, 0)) as balance')
                            ->orderBy('balance', $direction);
                    }),
                
                BadgeColumn::make('installment_status')
                    ->label('Trả góp')
                    ->formatStateUsing(function ($record) {
                        if (!$record->hasInstallmentPlan()) {
                            return 'Không';
                        }
                        $plan = $record->installmentPlan;
                        return $plan->number_of_installments . ' kỳ';
                    })
                    ->description(function ($record) {
                        if ($record->hasInstallmentPlan()) {
                            $plan = $record->installmentPlan;
                            return $plan->getStatusLabel();
                        }
                        return null;
                    })
                    ->color(function ($record) {
                        if (!$record->hasInstallmentPlan()) return 'gray';
                        return $record->installmentPlan->getStatusBadgeColor();
                    })
                    ->icon(function ($record) {
                        if (!$record->hasInstallmentPlan()) return null;
                        return Heroicon::OutlinedCreditCard;
                    })
                    ->toggleable(),
                
                BadgeColumn::make('status')
                    ->label('Trạng thái')
                    ->formatStateUsing(fn ($record) => $record->getPaymentStatusLabel())
                    ->color(fn ($record) => $record->getStatusBadgeColor())
                    ->icon(function ($record) {
                        return match($record->getStatusBadgeColor()) {
                            'danger' => Heroicon::OutlinedExclamationCircle,
                            'success' => Heroicon::OutlinedCheckCircle,
                            'warning' => Heroicon::OutlinedClock,
                            'info' => Heroicon::OutlinedInformationCircle,
                            default => null,
                        };
                    }),
                
                TextColumn::make('due_date')
                    ->label('Ngày đến hạn')
                    ->date('d/m/Y')
                    ->sortable()
                    ->formatStateUsing(function ($record) {
                        if (!$record->due_date) return '—';
                        $dueDate = \Carbon\Carbon::parse($record->due_date);
                        if ($record->isOverdue()) {
                            $days = $record->getDaysOverdue();
                            return '⚠️ ' . $dueDate->format('d/m/Y') . " (quá hạn {$days} ngày)";
                        }
                        $daysUntil = now()->diffInDays($dueDate, false);
                        if ($daysUntil <= 7 && $daysUntil >= 0) {
                            return '⏰ ' . $dueDate->format('d/m/Y') . " (còn {$daysUntil} ngày)";
                        }
                        return $dueDate->format('d/m/Y');
                    })
                    ->color(function ($record) {
                        if ($record->isOverdue()) return 'danger';
                        if ($record->due_date && now()->diffInDays($record->due_date, false) <= 7) return 'warning';
                        return 'success';
                    })
                    ->toggleable(),
                
                TextColumn::make('payments_count')
                    ->label('Số lần TT')
                    ->counts('payments')
                    ->badge()
                    ->color('info')
                    ->toggleable(isToggledHiddenByDefault: true),
                
                TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Trạng thái thanh toán')
                    ->multiple()
                    ->options([
                        'draft' => '📝 Nháp',
                        'issued' => '📋 Đã phát hành',
                        'partial' => '⚠️ TT một phần',
                        'paid' => '✅ Đã thanh toán',
                        'overdue' => '🔴 Quá hạn',
                        'cancelled' => '❌ Đã hủy',
                    ]),
                
                SelectFilter::make('has_installment')
                    ->label('Trả góp')
                    ->options([
                        'yes' => 'Có kế hoạch trả góp',
                        'no' => 'Không trả góp',
                    ])
                    ->query(function ($query, $state) {
                        if ($state['value'] === 'yes') {
                            return $query->has('installmentPlan');
                        }
                        if ($state['value'] === 'no') {
                            return $query->doesntHave('installmentPlan');
                        }
                    }),
                
                SelectFilter::make('payment_progress')
                    ->label('Tiến độ thanh toán')
                    ->options([
                        'unpaid' => 'Chưa thanh toán (0%)',
                        'partial' => 'Đã thanh toán một phần (1-99%)',
                        'paid' => 'Đã thanh toán đủ (100%)',
                    ])
                    ->query(function ($query, $state) {
                        if ($state['value'] === 'unpaid') {
                            return $query->where('paid_amount', 0);
                        }
                        if ($state['value'] === 'partial') {
                            return $query->whereRaw('paid_amount > 0 AND paid_amount < total_amount');
                        }
                        if ($state['value'] === 'paid') {
                            return $query->whereRaw('paid_amount >= total_amount');
                        }
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Xem'),
                
                EditAction::make()
                    ->label('Sửa'),
                
                Action::make('record_payment')
                    ->label('Thanh toán')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('success')
                    ->form([
                        TextInput::make('amount')
                            ->label('Số tiền')
                            ->numeric()
                            ->required()
                            ->prefix('VNĐ')
                            ->suffix('đ')
                            ->minValue(0)
                            ->default(fn ($record) => $record->calculateBalance())
                            ->helperText(fn ($record) => 'Còn lại: ' . number_format($record->calculateBalance(), 0, ',', '.') . 'đ'),
                        
                        Select::make('method')
                            ->label('Phương thức')
                            ->required()
                            ->options([
                                'cash' => '💵 Tiền mặt',
                                'card' => '💳 Thẻ tín dụng/ghi nợ',
                                'transfer' => '🏦 Chuyển khoản',
                            ])
                            ->default('cash')
                            ->native(false),
                        
                        DateTimePicker::make('paid_at')
                            ->label('Ngày thanh toán')
                            ->required()
                            ->default(now())
                            ->format('d/m/Y H:i')
                            ->native(false),
                    ])
                    ->action(function ($record, array $data) {
                        $payment = $record->recordPayment(
                            $data['amount'],
                            $data['method'],
                            'Thanh toán hóa đơn ' . $record->invoice_no,
                            $data['paid_at']
                        );
                        
                        Notification::make()
                            ->success()
                            ->title('Thanh toán thành công')
                            ->body('Đã ghi nhận thanh toán ' . number_format($data['amount'], 0, ',', '.') . 'đ')
                            ->send();
                    })
                    ->visible(fn ($record) => $record->calculateBalance() > 0)
                    ->modalWidth('md'),
                
                Action::make('view_payments')
                    ->label('Xem thanh toán')
                    ->icon(Heroicon::OutlinedCurrencyDollar)
                    ->color('info')
                    ->url(fn ($record) => route('filament.admin.resources.payments.index', [
                        'tableFilters' => ['invoice_id' => ['value' => $record->id]],
                    ]))
                    ->visible(fn ($record) => $record->hasPayments())
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Chưa có hóa đơn')
            ->emptyStateDescription('Tạo hóa đơn đầu tiên cho bệnh nhân')
            ->emptyStateIcon('heroicon-o-document-chart-bar');
    }
}
