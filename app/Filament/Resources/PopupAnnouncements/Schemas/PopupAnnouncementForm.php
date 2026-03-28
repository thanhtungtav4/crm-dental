<?php

namespace App\Filament\Resources\PopupAnnouncements\Schemas;

use App\Models\PopupAnnouncement;
use App\Support\BranchAccess;
use App\Support\ClinicRuntimeSettings;
use Filament\Forms;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

class PopupAnnouncementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make()
                    ->schema([
                        Section::make('Nội dung popup')
                            ->description('Soạn tiêu đề ngắn gọn, rõ hành động cần đọc hoặc cần làm.')
                            ->schema([
                                Forms\Components\TextInput::make('title')
                                    ->label('Tiêu đề')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\RichEditor::make('message')
                                    ->label('Nội dung popup')
                                    ->toolbarButtons([
                                        ['bold', 'italic', 'underline', 'strike', 'link'],
                                        ['h2', 'h3', 'alignStart', 'alignCenter', 'alignEnd'],
                                        ['blockquote', 'bulletList', 'orderedList'],
                                        ['table', 'attachFiles'],
                                        ['undo', 'redo'],
                                    ])
                                    ->fileAttachmentsDisk('public')
                                    ->fileAttachmentsDirectory('popup-announcements')
                                    ->fileAttachmentsVisibility('public')
                                    ->helperText('Hỗ trợ chèn ảnh, bảng và định dạng nâng cao. Ảnh được lưu tại storage/public/popup-announcements.')
                                    ->required(),
                            ]),

                        Section::make('Lịch phát & mức độ')
                            ->description('Xác định khi nào popup bắt đầu hiển thị, hết hiệu lực và mức ưu tiên của nó.')
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->label('Trạng thái')
                                    ->options(PopupAnnouncement::statusOptions())
                                    ->default(PopupAnnouncement::STATUS_DRAFT)
                                    ->required()
                                    ->helperText('Nháp = chỉ lưu. Đã lên lịch = tự phát khi tới giờ. Đang phát = gửi ngay cho đúng đối tượng.'),

                                Forms\Components\Select::make('priority')
                                    ->label('Mức ưu tiên')
                                    ->options(PopupAnnouncement::priorityOptions())
                                    ->default(PopupAnnouncement::PRIORITY_INFO)
                                    ->required()
                                    ->helperText('Chỉ dùng “Khẩn cấp” cho các thông báo cần xử lý hoặc xác nhận ngay.'),

                                Forms\Components\DateTimePicker::make('starts_at')
                                    ->label('Bắt đầu hiển thị')
                                    ->native(false)
                                    ->seconds(false)
                                    ->nullable(),

                                Forms\Components\DateTimePicker::make('ends_at')
                                    ->label('Kết thúc hiển thị')
                                    ->native(false)
                                    ->seconds(false)
                                    ->nullable(),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpan(['lg' => 2]),

                Group::make()
                    ->schema([
                        Section::make('Đối tượng nhận')
                            ->description('Chọn đúng nhóm quyền và chi nhánh để tránh gửi nhầm popup.')
                            ->schema([
                                Forms\Components\CheckboxList::make('target_role_names')
                                    ->label('Nhóm quyền nhận popup')
                                    ->options(fn (): array => Role::query()->orderBy('name')->pluck('name', 'name')->all())
                                    ->columns(2)
                                    ->required()
                                    ->helperText('Popup chỉ gửi tới user thuộc các nhóm quyền đã chọn.')
                                    ->columnSpanFull(),

                                Forms\Components\Select::make('target_branch_ids')
                                    ->label('Chi nhánh nhận popup')
                                    ->options(fn (): array => BranchAccess::branchOptionsForCurrentUser())
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Để trống = gửi toàn hệ thống. Chỉ role được cấu hình mới có quyền phát toàn hệ thống.')
                                    ->columnSpanFull(),
                            ]),

                        Section::make('Quy tắc hiển thị')
                            ->description('Quy định popup có bắt buộc xác nhận hay chỉ cần đọc và đóng.')
                            ->schema([
                                Forms\Components\TextInput::make('code')
                                    ->label('Mã popup')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->visibleOn('edit'),

                                Forms\Components\Toggle::make('require_ack')
                                    ->label('Bắt buộc xác nhận đã đọc')
                                    ->default(false)
                                    ->inline(false)
                                    ->helperText('Bật khi người nhận phải bấm “Tôi đã đọc” trước khi đóng popup.'),

                                Forms\Components\Toggle::make('show_once')
                                    ->label('Hiển thị 1 lần cho mỗi user')
                                    ->default(true)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->helperText('Luôn bật để tránh popup lặp gây nhiễu thao tác.'),

                                Forms\Components\Placeholder::make('sender_roles_note')
                                    ->label('Quyền gửi toàn hệ thống')
                                    ->content(function (): string {
                                        $roles = ClinicRuntimeSettings::popupAnnouncementSenderRoles();

                                        return $roles === []
                                            ? 'Chưa cấu hình role gửi popup toàn hệ thống.'
                                            : 'Role được gửi toàn hệ thống: '.implode(', ', $roles);
                                    }),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ]);
    }
}
