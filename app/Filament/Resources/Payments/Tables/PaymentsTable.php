<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Support\ClinicRuntimeSettings;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // Invoice number with patient info
                TextColumn::make('invoice.invoice_no')
                    ->label('Số hóa đơn')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->invoice?->patient?->full_name ?? 'N/A'),

                // Patient name (toggleable)
                TextColumn::make('invoice.patient.full_name')
                    ->label('Bệnh nhân')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                // Amount with color by method
                TextColumn::make('amount')
                    ->label('Số tiền')
                    ->money('VND', divideBy: 1)
                    ->sortable()
                    ->color(fn ($record) => $record->direction === 'refund' ? 'danger' : $record->getMethodBadgeColor())
                    ->weight('bold')
                    ->description(fn ($record) => $record->direction === 'refund' ? 'Phiếu hoàn' : 'Phiếu thu'),

                BadgeColumn::make('direction')
                    ->label('Loại phiếu')
                    ->formatStateUsing(fn ($record) => $record->getDirectionLabel())
                    ->color(fn ($record) => $record->direction === 'refund' ? 'danger' : 'success'),

                // Payment method badge with Vietnamese labels
                BadgeColumn::make('method')
                    ->label('Phương thức')
                    ->formatStateUsing(fn ($record) => $record->getMethodLabel())
                    ->icon(fn ($record) => $record->getMethodIcon())
                    ->color(fn ($record) => $record->getMethodBadgeColor()),

                // Payment source badge
                BadgeColumn::make('payment_source')
                    ->label('Nguồn')
                    ->formatStateUsing(fn ($record) => $record->getSourceLabel())
                    ->color(fn ($record) => $record->getSourceBadgeColor())
                    ->toggleable(isToggledHiddenByDefault: false),

                // Paid date with Vietnamese format
                TextColumn::make('paid_at')
                    ->label('Thời gian TT')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                // Receiver name (toggleable)
                TextColumn::make('receiver.name')
                    ->label('Người nhận')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // Transaction reference (toggleable)
                TextColumn::make('transaction_ref')
                    ->label('Mã GD')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('refund_reason')
                    ->label('Lý do hoàn')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),

                // Invoice status
                BadgeColumn::make('invoice.status')
                    ->label('TT Hóa đơn')
                    ->formatStateUsing(fn ($record) => match($record->invoice?->status) {
                        'draft' => 'Nháp',
                        'issued' => 'Đã xuất',
                        'partial' => 'TT 1 phần',
                        'paid' => 'Đã TT',
                        'overdue' => 'Quá hạn',
                        'cancelled' => 'Đã hủy',
                        default => 'N/A',
                    })
                    ->color(fn ($record) => $record->invoice?->getStatusBadgeColor() ?? 'gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Filter by payment method
                SelectFilter::make('method')
                    ->label('Phương thức')
                    ->options(ClinicRuntimeSettings::paymentMethodOptions(withEmoji: true))
                    ->multiple(),

                // Filter by payment source
                SelectFilter::make('payment_source')
                    ->label('Nguồn thanh toán')
                    ->options([
                        'patient' => '👤 Bệnh nhân',
                        'insurance' => '🏥 Bảo hiểm',
                        'other' => '📄 Khác',
                    ])
                    ->multiple(),

                SelectFilter::make('direction')
                    ->label('Loại phiếu')
                    ->options([
                        'receipt' => 'Phiếu thu',
                        'refund' => 'Phiếu hoàn',
                    ])
                    ->multiple(),

                // Filter by receiver
                SelectFilter::make('received_by')
                    ->label('Người nhận')
                    ->relationship('receiver', 'name')
                    ->searchable()
                    ->preload(),

                // Filter by date range
                Filter::make('paid_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('paid_from')
                            ->label('Từ ngày'),
                        \Filament\Forms\Components\DatePicker::make('paid_until')
                            ->label('Đến ngày'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['paid_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('paid_at', '>=', $date),
                            )
                            ->when(
                                $data['paid_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('paid_at', '<=', $date),
                            );
                    }),
            ])
            ->defaultSort('paid_at', 'desc')
            ->recordActions([
                ViewAction::make()
                    ->label('Xem'),
                EditAction::make()
                    ->label('Sửa'),
                \Filament\Actions\Action::make('view_invoice')
                    ->label('Xem HĐ')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->url(fn ($record) => $record->invoice_id 
                        ? route('filament.admin.resources.invoices.edit', ['record' => $record->invoice_id])
                        : null)
                    ->openUrlInNewTab(),
                \Filament\Actions\Action::make('print')
                    ->label('In phiếu')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn ($record) => route('payments.print', $record))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Xóa đã chọn'),
                ]),
            ])
            ->emptyStateHeading('Chưa có thanh toán nào')
            ->emptyStateDescription('Tạo thanh toán mới bằng cách nhấn nút bên dưới')
            ->emptyStateIcon('heroicon-o-banknotes');
    }
}
