<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Jobs\SendConversationMessage;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use App\Services\ConversationInboxReadModelService;
use App\Services\ConversationLeadBindingService;
use App\Services\PatientAssignmentAuthorizer;
use App\Support\BranchAccess;
use App\Support\ClinicRuntimeSettings;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use UnitEnum;

class ConversationInbox extends Page
{
    protected const DEFAULT_VISIBLE_MESSAGE_LIMIT = 30;

    protected const MESSAGE_BATCH_SIZE = 30;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Inbox hội thoại';

    protected static string|UnitEnum|null $navigationGroup = 'Chăm sóc khách hàng';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'conversation-inbox';

    protected string $view = 'filament.pages.conversation-inbox';

    public ?int $selectedConversationId = null;

    public string $draftReply = '';

    public bool $showLeadModal = false;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'provider')]
    public string $providerFilter = 'all';

    #[Url(as: 'tab')]
    public string $inboxTab = 'all';

    public int $visibleMessageLimit = self::DEFAULT_VISIBLE_MESSAGE_LIMIT;

    /**
     * @var array<string, mixed>
     */
    public array $leadForm = [];

    /**
     * @var array<string, mixed>
     */
    public array $handoffForm = [
        'priority' => Conversation::PRIORITY_NORMAL,
        'status' => Conversation::HANDOFF_STATUS_NEW,
        'next_action_at' => '',
        'summary' => '',
    ];

    /**
     * @var array<string, mixed>
     */
    public array $handoffSnapshot = [
        'priority' => Conversation::PRIORITY_NORMAL,
        'status' => Conversation::HANDOFF_STATUS_NEW,
        'next_action_at' => '',
        'summary' => '',
        'version' => 0,
    ];

    /**
     * @var array{assigned_to:string}
     */
    public array $assignmentForm = [
        'assigned_to' => '',
    ];

    /**
     * @var array{assigned_to:string}
     */
    public array $assignmentSnapshot = [
        'assigned_to' => '',
    ];

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess() && parent::shouldRegisterNavigation();
    }

    public static function canAccess(): bool
    {
        $authUser = auth()->user();

        return $authUser instanceof User
            && $authUser->can('View:ConversationInbox')
            && $authUser->hasAnyAccessibleBranch();
    }

    public function mount(): void
    {
        if (! $this->isConversationSchemaReady()) {
            $this->selectedConversationId = null;
            $this->resetVisibleMessageLimit();
            $this->showLeadModal = false;
            $this->syncHandoffForm(null);
            $this->syncAssignmentForm(null);

            return;
        }

        $this->syncSelectionToCurrentFilters();
    }

    public function getHeading(): string
    {
        return 'Inbox hội thoại đa kênh';
    }

    public function getTitle(): string
    {
        return $this->getHeading();
    }

    public function getSubheading(): string
    {
        return 'Theo dõi hội thoại Zalo OA và Facebook Messenger theo thời gian thực bằng polling, phản hồi ngay trên CRM và gắn lead vào đúng luồng tư vấn.';
    }

    public function getPollingIntervalSeconds(): int
    {
        return ClinicRuntimeSettings::conversationInboxPollingSeconds();
    }

    /**
     * @return array{
     *   is_schema_ready:bool,
     *   polling_interval_seconds:int,
     *   schema_notice:array{
     *      heading:string,
     *      description:string,
     *      message:string
     *   },
     *   page_panel:array{
     *      queue_panel:array{
     *          heading:string,
     *          description:string,
     *          search_label:string,
     *          search_placeholder:string,
     *          provider_label:string,
     *          results_text:string,
     *          empty_state_text:string,
     *          inbox_stat_cards:list<array{
     *              key:string,
     *              label:string,
     *              count:int,
     *              description:string,
     *              card_class:string,
     *              label_class:string,
     *              value_class:string,
     *              description_class:string
     *          }>,
     *          rendered_inbox_tabs:list<array{
     *              key:string,
     *              label:string,
     *              button_class:string,
     *              is_active:bool
     *          }>,
     *          provider_filter_options:array<string, string>,
     *          conversation_rows:list<array{
     *              id:int,
     *              button_class:string,
     *              display_name:string,
     *              provider_label:string,
     *              provider_badge_class:string,
     *              handoff_status_label:string,
     *              handoff_status_badge_class:string,
     *              handoff_priority_label:string,
     *              handoff_priority_badge_class:string,
     *              branch_label:string,
     *              unread_count:int,
     *              preview:string,
     *              handoff_summary_preview:?string,
     *              lead_status_label:string,
     *              next_action_label:?string,
     *              last_message_at_human:string
     *          }>
     *      },
     *      detail_panel:array{
     *          heading:string,
     *          description:string,
     *          empty_state_text:string,
     *          conversation:?Conversation,
     *          selected_conversation_view:array{
     *              summary_cards:list<array{label:string,value:string}>,
     *              assignee_options:array<int,string>,
     *              customer_edit_url:?string,
     *              handoff_panel:array{
     *                  summary_heading:string,
     *                  summary_description:string,
     *                  updated_at_text:?string,
     *                  updated_by_name:string,
     *                  summary_label:string,
     *                  summary_placeholder:string,
     *                  status_label:string,
     *                  next_action_label:string,
     *                  priority_label:string,
     *                  priority_options:array<string,string>,
     *                  status_options:array<string,string>,
     *                  guidance:string,
     *                  submit_label:string
     *              },
     *              thread_panel:array{
     *                  heading:string,
     *                  description:string,
     *                  show_load_older_messages:bool,
     *                  empty_state_text:string,
     *                  load_older_label:string
     *              },
     *              composer_panel:array{
     *                  label:string,
     *                  placeholder:string,
     *                  helper_text:string,
     *                  polling_notice:string,
     *                  submit_label:string
     *              },
     *              messages:list<array{
     *                  id:int,
     *                  container_class:string,
     *                  bubble_class:string,
     *                  sender_label:string,
     *                  message_at_text:string,
     *                  body:string,
     *                  status_label:string,
     *                  can_retry:bool,
     *                  last_error:?string
     *              }>
     *          }
     *      },
     *      lead_modal_view:array{
     *          heading:string,
     *          description:string,
     *          close_label:string,
     *          branch_options:array<int,string>,
     *          assignee_options:array<int,string>,
     *          summary_cards:list<array{label:string,value:string}>,
     *          submit_label:string
     *      }
     *   },
     *   inbox_stats:array{unread:int,due:int,unclaimed:int,unbound:int}
     * }
     */
    #[Computed]
    public function inboxViewState(): array
    {
        $selectedConversation = $this->selectedConversation();

        return [
            'is_schema_ready' => $this->isConversationSchemaReady(),
            'polling_interval_seconds' => $this->getPollingIntervalSeconds(),
            'schema_notice' => $this->schemaNoticePanel(),
            'inbox_stats' => $this->inboxStats(),
            'page_panel' => [
                'queue_panel' => [
                    ...$this->queuePanel(),
                    'inbox_stat_cards' => $this->inboxStatCards(),
                    'rendered_inbox_tabs' => $this->renderedInboxTabs(),
                    'provider_filter_options' => $this->providerFilterOptions(),
                    'conversation_rows' => $this->renderedConversationRows(),
                ],
                'detail_panel' => [
                    ...$this->detailPanel(),
                    'conversation' => $selectedConversation,
                    'selected_conversation_view' => $this->selectedConversationView(),
                ],
                'lead_modal_view' => $this->leadModalView(),
            ],
        ];
    }

    /**
     * @return array{
     *   heading:string,
     *   description:string,
     *   message:string
     * }
     */
    protected function schemaNoticePanel(): array
    {
        return [
            'heading' => 'Inbox hội thoại chưa sẵn sàng',
            'description' => 'Trang này sẽ tự hoạt động lại sau khi schema hội thoại được cài đặt đầy đủ.',
            'message' => 'Quản trị viên cần hoàn tất cài đặt dữ liệu hội thoại trước khi đội CSKH sử dụng màn hình này.',
        ];
    }

    /**
     * @return Collection<int, Conversation>
     */
    #[Computed]
    public function conversationList(): Collection
    {
        if (! $this->isConversationSchemaReady()) {
            return new Collection;
        }

        return app(ConversationInboxReadModelService::class)->list(
            $this->currentUser(),
            filters: $this->currentFilters(),
        );
    }

    #[Computed]
    public function selectedConversation(): ?Conversation
    {
        if (! $this->isConversationSchemaReady()) {
            return null;
        }

        return app(ConversationInboxReadModelService::class)
            ->findVisibleConversation(
                $this->currentUser(),
                $this->selectedConversationId,
                $this->currentFilters(),
                $this->visibleMessageLimit,
            );
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function branchOptions(): array
    {
        return BranchAccess::branchOptionsForCurrentUser();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function assignableStaffOptions(): array
    {
        $branchId = filled($this->leadForm['branch_id'] ?? null)
            ? (int) $this->leadForm['branch_id']
            : null;

        return app(PatientAssignmentAuthorizer::class)
            ->assignableStaffOptions($this->currentUser(), $branchId);
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function conversationAssigneeOptions(): array
    {
        $branchId = $this->selectedConversation()?->branch_id;

        if (! filled($branchId)) {
            return [];
        }

        return app(PatientAssignmentAuthorizer::class)
            ->assignableStaffOptions($this->currentUser(), (int) $branchId);
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function handoffPriorityOptions(): array
    {
        return Conversation::handoffPriorityOptions();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function handoffStatusOptions(): array
    {
        return Conversation::handoffStatusOptions();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function inboxTabOptions(): array
    {
        return [
            'all' => 'Tất cả',
            'priority' => 'Ưu tiên',
            'due' => 'Đến hạn',
            'unbound' => 'Chưa gắn lead',
            'mine' => 'Của tôi',
        ];
    }

    /**
     * @return array{unread:int,due:int,unclaimed:int,unbound:int}
     */
    #[Computed]
    public function inboxStats(): array
    {
        if (! $this->isConversationSchemaReady()) {
            return [
                'unread' => 0,
                'due' => 0,
                'unclaimed' => 0,
                'unbound' => 0,
            ];
        }

        return app(ConversationInboxReadModelService::class)->stats(
            $this->currentUser(),
            $this->currentFilters(),
        );
    }

    /**
     * @return list<array{
     *    key:string,
     *    label:string,
     *    count:int,
     *    description:string,
     *    card_class:string,
     *    label_class:string,
     *    value_class:string,
     *    description_class:string
     * }>
     */
    protected function inboxStatCards(): array
    {
        $stats = $this->inboxStats();

        return [
            [
                'key' => 'unread',
                'label' => 'Chưa đọc',
                'count' => $stats['unread'],
                'description' => 'Cần mở thread để xử lý ngay trong ca.',
                'card_class' => 'border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950/60',
                'label_class' => 'text-gray-400 dark:text-gray-500',
                'value_class' => 'text-gray-950 dark:text-white',
                'description_class' => 'text-gray-500 dark:text-gray-400',
            ],
            [
                'key' => 'due',
                'label' => 'Đến hạn follow-up',
                'count' => $stats['due'],
                'description' => 'Ưu tiên gọi lại hoặc chốt bước tiếp theo.',
                'card_class' => 'border-warning-200 bg-warning-50/80 dark:border-warning-900/60 dark:bg-warning-950/20',
                'label_class' => 'text-warning-700 dark:text-warning-200',
                'value_class' => 'text-warning-900 dark:text-warning-100',
                'description_class' => 'text-warning-700/80 dark:text-warning-200/80',
            ],
            [
                'key' => 'unclaimed',
                'label' => 'Chưa claim',
                'count' => $stats['unclaimed'],
                'description' => 'Hội thoại chưa có người phụ trách rõ ràng.',
                'card_class' => 'border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950/60',
                'label_class' => 'text-gray-400 dark:text-gray-500',
                'value_class' => 'text-gray-950 dark:text-white',
                'description_class' => 'text-gray-500 dark:text-gray-400',
            ],
            [
                'key' => 'unbound',
                'label' => 'Chưa gắn lead',
                'count' => $stats['unbound'],
                'description' => 'Còn cơ hội convert trực tiếp từ inbox.',
                'card_class' => 'border-primary-200 bg-primary-50/80 dark:border-primary-900/60 dark:bg-primary-950/20',
                'label_class' => 'text-primary-700 dark:text-primary-200',
                'value_class' => 'text-primary-900 dark:text-primary-100',
                'description_class' => 'text-primary-700/80 dark:text-primary-200/80',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function providerFilterOptions(): array
    {
        return [
            'all' => 'Tất cả',
            'zalo' => 'Zalo OA',
            'facebook' => 'Facebook Messenger',
        ];
    }

    /**
     * @return list<array{
     *   key:string,
     *   label:string,
     *   button_class:string,
     *   is_active:bool
     * }>
     */
    protected function renderedInboxTabs(): array
    {
        return collect($this->inboxTabOptions())
            ->map(function (string $label, string $key): array {
                $isActive = $this->inboxTab === $key;

                return [
                    'key' => $key,
                    'label' => $label,
                    'is_active' => $isActive,
                    'button_class' => $isActive
                        ? 'border-primary-300 bg-primary-50 text-primary-700 dark:border-primary-700 dark:bg-primary-950/40 dark:text-primary-200'
                        : 'border-gray-200 bg-white text-gray-600 hover:border-primary-200 hover:text-primary-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-primary-800 dark:hover:text-primary-200',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{label:string,value:string}>
     */
    protected function selectedConversationSummaryCards(): array
    {
        $conversation = $this->selectedConversation();

        if (! $conversation instanceof Conversation) {
            return [];
        }

        return [
            [
                'label' => 'Khách ngoài hệ thống',
                'value' => (string) $conversation->external_user_id,
            ],
            [
                'label' => 'Chi nhánh / phụ trách',
                'value' => ($conversation->branch?->name ?? 'Chưa route').' · '.($conversation->assignee?->name ?? 'Chưa claim'),
            ],
            [
                'label' => 'Follow-up tiếp theo',
                'value' => $conversation->handoffNextActionLabel('d/m/Y H:i') ?? 'Chưa đặt lịch follow-up',
            ],
        ];
    }

    /**
     * @return list<array{
     *   id:int,
     *   button_class:string,
     *   display_name:string,
     *   provider_label:string,
     *   provider_badge_class:string,
     *   handoff_status_label:string,
     *   handoff_status_badge_class:string,
     *   handoff_priority_label:string,
     *   handoff_priority_badge_class:string,
     *   branch_label:string,
     *   unread_count:int,
     *   preview:string,
     *   handoff_summary_preview:?string,
     *   lead_status_label:string,
     *   next_action_label:?string,
     *   last_message_at_human:string
     * }>
     */
    protected function renderedConversationRows(): array
    {
        return $this->conversationList()
            ->map(function (Conversation $conversation): array {
                $isSelected = (int) $conversation->id === (int) ($this->selectedConversation()?->id ?? 0);

                return [
                    'id' => (int) $conversation->id,
                    'button_class' => $isSelected
                        ? 'border-primary-300 bg-primary-50/70 dark:border-primary-700 dark:bg-primary-950/30'
                        : 'border-gray-200 bg-white hover:border-primary-200 hover:bg-primary-50/40 dark:border-gray-800 dark:bg-gray-950/50 dark:hover:border-primary-800 dark:hover:bg-primary-950/20',
                    'display_name' => $conversation->displayName(),
                    'provider_label' => $conversation->providerLabel(),
                    'provider_badge_class' => $conversation->providerBadgeClasses(),
                    'handoff_status_label' => $conversation->handoffStatusLabel(),
                    'handoff_status_badge_class' => $conversation->handoffStatusBadgeClasses(),
                    'handoff_priority_label' => $conversation->handoffPriorityLabel(),
                    'handoff_priority_badge_class' => $conversation->handoffPriorityBadgeClasses(),
                    'branch_label' => $conversation->branch?->name ?? 'Chưa route chi nhánh',
                    'unread_count' => (int) $conversation->unread_count,
                    'preview' => $conversation->latestPreview(),
                    'handoff_summary_preview' => filled($conversation->handoffSummaryPreview())
                        ? $conversation->handoffSummaryPreview()
                        : null,
                    'lead_status_label' => $conversation->customer_id ? 'Đã gắn lead' : 'Chưa gắn lead',
                    'next_action_label' => $conversation->handoffNextActionLabel(),
                    'last_message_at_human' => optional($conversation->last_message_at)->diffForHumans() ?? '-',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{
     *   heading:string,
     *   description:string,
     *   search_label:string,
     *   search_placeholder:string,
     *   provider_label:string,
     *   results_text:string,
     *   empty_state_text:string
     * }
     */
    protected function queuePanel(): array
    {
        return [
            'heading' => 'Queue hội thoại',
            'description' => 'Tin nhắn inbound mới từ Zalo OA và Facebook Messenger sẽ tự vào đây theo chu kỳ polling đã cấu hình.',
            'search_label' => 'Tìm nhanh hội thoại',
            'search_placeholder' => 'Tìm theo tên, lead, note…',
            'provider_label' => 'Kênh',
            'results_text' => 'Đang hiển thị '.count($this->renderedConversationRows()).' hội thoại theo bộ lọc hiện tại.',
            'empty_state_text' => 'Không có hội thoại nào khớp bộ lọc hiện tại.',
        ];
    }

    /**
     * @return array{
     *   heading:string,
     *   description:string,
     *   empty_state_text:string
     * }
     */
    protected function detailPanel(): array
    {
        return [
            'heading' => 'Chi tiết hội thoại',
            'description' => 'Phản hồi trực tiếp, gắn lead vào đúng conversation và tiếp tục giữ luồng tin nhắn về sau.',
            'empty_state_text' => 'Chọn một hội thoại ở cột bên trái để xem thread chi tiết.',
        ];
    }

    /**
     * @return array{
     *   summary_cards:list<array{label:string,value:string}>,
     *   assignee_options:array<int,string>,
     *   customer_edit_url:?string,
     *   handoff_panel:array{
     *       summary_heading:string,
     *       summary_description:string,
     *       updated_at_text:?string,
     *       updated_by_name:string,
     *       summary_label:string,
     *       summary_placeholder:string,
     *       status_label:string,
     *       next_action_label:string,
     *       priority_label:string,
     *       priority_options:array<string,string>,
     *       status_options:array<string,string>,
     *       guidance:string,
     *       submit_label:string
     *   },
     *   thread_panel:array{
     *       heading:string,
     *       description:string,
     *       show_load_older_messages:bool,
     *       empty_state_text:string,
     *       load_older_label:string
     *   },
     *   composer_panel:array{
     *       label:string,
     *       placeholder:string,
     *       helper_text:string,
     *       polling_notice:string,
     *       submit_label:string
     *   },
     *   messages:list<array{
     *       id:int,
     *       container_class:string,
     *       bubble_class:string,
     *       sender_label:string,
     *       message_at_text:string,
     *       body:string,
     *       status_label:string,
     *       can_retry:bool,
     *       last_error:?string
     *   }>
     * }
     */
    protected function selectedConversationView(): array
    {
        $conversation = $this->selectedConversation();

        return [
            'summary_cards' => $this->selectedConversationSummaryCards(),
            'assignee_options' => $this->conversationAssigneeOptions(),
            'customer_edit_url' => $this->customerEditUrl($conversation),
            'handoff_panel' => [
                'summary_heading' => 'Note bàn giao nội bộ',
                'summary_description' => 'Chỉ hiển thị trong CRM để CSKH khác mở vào là nắm nhanh bối cảnh, ưu tiên hiện tại và bước follow-up tiếp theo.',
                'updated_at_text' => $conversation?->handoff_updated_at?->format('d/m/Y H:i'),
                'updated_by_name' => $conversation?->handoffEditor?->name ?? 'CRM',
                'summary_label' => 'Tóm tắt bàn giao',
                'summary_placeholder' => 'VD: Khách đang so sánh 2 gói, đã hẹn gọi lại 17h, cần ưu tiên tư vấn giá.',
                'status_label' => 'Trạng thái xử lý',
                'next_action_label' => 'Follow-up tiếp theo',
                'priority_label' => 'Mức ưu tiên',
                'priority_options' => $this->handoffPriorityOptions(),
                'status_options' => $this->handoffStatusOptions(),
                'guidance' => 'Gợi ý: ghi nhu cầu chính, cam kết đã hẹn, thông tin còn thiếu và điều gì phải xử lý trước trong ca trực tiếp theo.',
                'submit_label' => 'Lưu note bàn giao',
            ],
            'thread_panel' => [
                'heading' => 'Thread hội thoại',
                'description' => $conversation instanceof Conversation
                    ? 'Đang hiển thị '.$conversation->getAttribute('loaded_message_count').' tin gần nhất. Polling sẽ chỉ cập nhật phần thread đang mở thay vì kéo toàn bộ lịch sử.'
                    : '',
                'show_load_older_messages' => (bool) ($conversation?->getAttribute('has_more_messages') ?? false),
                'empty_state_text' => 'Hội thoại này chưa có tin nhắn nào.',
                'load_older_label' => 'Xem tin cũ hơn',
            ],
            'composer_panel' => [
                'label' => 'Phản hồi từ CRM',
                'placeholder' => 'Nhập phản hồi gửi qua hội thoại đang chọn...',
                'helper_text' => 'Composer được giữ cố định ở cuối thread để CSKH không phải kéo xuống đáy lịch sử sau mỗi lần xem lại tin cũ.',
                'polling_notice' => 'Chưa có websocket ở v1, nên page sẽ tự polling để cập nhật thread mới giữa các kênh.',
                'submit_label' => 'Gửi phản hồi',
            ],
            'messages' => $this->renderedSelectedConversationMessages(),
        ];
    }

    /**
     * @return list<array{
     *   id:int,
     *   container_class:string,
     *   bubble_class:string,
     *   sender_label:string,
     *   message_at_text:string,
     *   body:string,
     *   status_label:string,
     *   can_retry:bool,
     *   last_error:?string
     * }>
     */
    protected function renderedSelectedConversationMessages(): array
    {
        $conversation = $this->selectedConversation();

        if (! $conversation instanceof Conversation) {
            return [];
        }

        return $conversation->messages
            ->map(function (ConversationMessage $message): array {
                $isInbound = $message->isInbound();

                return [
                    'id' => (int) $message->id,
                    'container_class' => $isInbound ? 'justify-start' : 'justify-end',
                    'bubble_class' => $isInbound
                        ? 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900'
                        : 'border-primary-200 bg-primary-50/80 dark:border-primary-800 dark:bg-primary-950/30',
                    'sender_label' => $isInbound ? 'Khách' : ($message->sender?->name ?? 'CRM'),
                    'message_at_text' => optional($message->message_at)->format('d/m/Y H:i') ?? '-',
                    'body' => filled($message->body) ? $message->body : 'Tin nhắn không hỗ trợ hiển thị ở v1.',
                    'status_label' => $this->messageStatusLabel($message->status),
                    'can_retry' => $message->status === ConversationMessage::STATUS_FAILED,
                    'last_error' => $message->status === ConversationMessage::STATUS_FAILED && filled($message->last_error)
                        ? (string) $message->last_error
                        : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{
     *   heading:string,
     *   description:string,
     *   close_label:string,
     *   branch_options:array<int,string>,
     *   assignee_options:array<int,string>,
     *   summary_cards:list<array{label:string,value:string}>,
     *   submit_label:string
     * }
     */
    protected function leadModalView(): array
    {
        return [
            'heading' => 'Tạo lead từ hội thoại',
            'description' => 'Prefill từ kênh chat hiện tại và gắn lead vào đúng conversation đang chọn.',
            'close_label' => 'Đóng',
            'branch_options' => $this->branchOptions(),
            'assignee_options' => $this->assignableStaffOptions(),
            'summary_cards' => [
                [
                    'label' => 'Nguồn',
                    'value' => (string) ($this->leadForm['source'] ?? '-'),
                ],
                [
                    'label' => 'Nguồn chi tiết',
                    'value' => (string) ($this->leadForm['source_detail'] ?? '-'),
                ],
                [
                    'label' => 'Trạng thái',
                    'value' => (string) ($this->leadForm['status'] ?? '-'),
                ],
            ],
            'submit_label' => 'Lưu lead',
        ];
    }

    public function refreshInbox(): void
    {
        if (! $this->isConversationSchemaReady()) {
            $this->selectedConversationId = null;

            return;
        }

        $service = app(ConversationInboxReadModelService::class);
        $selectedConversation = $service->findVisibleConversation(
            $this->currentUser(),
            $this->selectedConversationId,
            $this->currentFilters(),
            $this->visibleMessageLimit,
        );

        if (! $selectedConversation instanceof Conversation) {
            $this->syncSelectionToCurrentFilters();

            return;
        }

        if ((int) $selectedConversation->unread_count > 0) {
            $this->markConversationAsRead($selectedConversation->id);
        }

        if (! $this->handoffFormIsDirty()) {
            $this->syncHandoffForm($selectedConversation);
        }

        if (! $this->assignmentFormIsDirty()) {
            $this->syncAssignmentForm($selectedConversation);
        }
    }

    public function selectConversation(int $conversationId): void
    {
        if (! $this->isConversationSchemaReady()) {
            return;
        }

        if ($conversationId !== $this->selectedConversationId) {
            $this->resetVisibleMessageLimit();
        }

        $conversation = app(ConversationInboxReadModelService::class)
            ->findVisibleConversation(
                $this->currentUser(),
                $conversationId,
                $this->currentFilters(),
                $this->visibleMessageLimit,
            );

        if (! $conversation instanceof Conversation) {
            return;
        }

        $this->selectedConversationId = $conversation->id;
        $this->markConversationAsRead($conversation->id);
        $this->syncHandoffForm($conversation);
        $this->syncAssignmentForm($conversation);
    }

    public function updatedSearch(): void
    {
        $this->syncSelectionToCurrentFilters();
    }

    public function updatedProviderFilter(): void
    {
        $this->syncSelectionToCurrentFilters();
    }

    public function updatedInboxTab(): void
    {
        $this->syncSelectionToCurrentFilters();
    }

    public function loadOlderMessages(): void
    {
        if (! $this->isConversationSchemaReady() || $this->selectedConversationId === null) {
            return;
        }

        $this->visibleMessageLimit += static::MESSAGE_BATCH_SIZE;
    }

    public function claimConversation(): void
    {
        $actor = $this->currentUser();

        if (! $actor instanceof User) {
            return;
        }

        $this->assignmentForm['assigned_to'] = (string) $actor->id;
        $this->saveConversationAssignee();
    }

    public function releaseConversation(): void
    {
        $this->assignmentForm['assigned_to'] = '';
        $this->saveConversationAssignee();
    }

    public function saveConversationAssignee(): void
    {
        if (! $this->isConversationSchemaReady()) {
            return;
        }

        $conversation = $this->selectedConversation;
        $actor = $this->currentUser();

        if (! $conversation instanceof Conversation || ! $actor instanceof User) {
            return;
        }

        $validated = $this->validate([
            'assignmentForm.assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ]);

        $assigneeId = filled($validated['assignmentForm']['assigned_to'] ?? null)
            ? app(PatientAssignmentAuthorizer::class)->assertAssignableStaffId(
                actor: $actor,
                staffId: (int) $validated['assignmentForm']['assigned_to'],
                branchId: $conversation->branch_id,
                field: 'assignmentForm.assigned_to',
            )
            : null;

        $updatedConversation = DB::transaction(function () use ($actor, $assigneeId, $conversation): Conversation {
            $lockedConversation = Conversation::query()
                ->visibleTo($actor)
                ->lockForUpdate()
                ->findOrFail($conversation->id);

            $lockedConversation->forceFill([
                'assigned_to' => $assigneeId,
            ])->save();

            $freshConversation = $lockedConversation->fresh([
                'assignee:id,name',
                'handoffEditor:id,name',
            ]);

            return $freshConversation instanceof Conversation ? $freshConversation : $lockedConversation;
        }, 3);

        $this->syncAssignmentForm($updatedConversation);

        Notification::make()
            ->title('Đã cập nhật người phụ trách hội thoại')
            ->body($updatedConversation->assignee?->name
                ? 'Hội thoại này hiện do '.$updatedConversation->assignee->name.' phụ trách.'
                : 'Hội thoại đã được nhả claim và quay về queue chung.')
            ->success()
            ->send();
    }

    public function saveHandoffNote(): void
    {
        if (! $this->isConversationSchemaReady()) {
            return;
        }

        $conversation = $this->selectedConversation;
        $actor = $this->currentUser();

        if (! $conversation instanceof Conversation || ! $actor instanceof User) {
            return;
        }

        $validated = $this->validate([
            'handoffForm.priority' => ['required', 'string', Rule::in(array_keys(Conversation::handoffPriorityOptions()))],
            'handoffForm.status' => ['required', 'string', Rule::in(array_keys(Conversation::handoffStatusOptions()))],
            'handoffForm.next_action_at' => ['nullable', 'date'],
            'handoffForm.summary' => ['nullable', 'string', 'max:4000'],
        ]);

        $summary = trim((string) ($validated['handoffForm']['summary'] ?? ''));
        $nextActionAt = filled($validated['handoffForm']['next_action_at'] ?? null)
            ? Carbon::parse((string) $validated['handoffForm']['next_action_at'])->seconds(0)
            : null;
        $expectedVersion = (int) ($this->handoffSnapshot['version'] ?? 0);

        /** @var array{saved: bool, conversation: Conversation} $result */
        $result = DB::transaction(function () use ($actor, $conversation, $expectedVersion, $nextActionAt, $summary, $validated): array {
            $lockedConversation = Conversation::query()
                ->visibleTo($actor)
                ->lockForUpdate()
                ->findOrFail($conversation->id);

            if ((int) $lockedConversation->handoff_version !== $expectedVersion) {
                $freshConversation = $lockedConversation->fresh(['handoffEditor']);

                return [
                    'saved' => false,
                    'conversation' => $freshConversation instanceof Conversation ? $freshConversation : $lockedConversation,
                ];
            }

            $lockedConversation->forceFill([
                'handoff_priority' => (string) $validated['handoffForm']['priority'],
                'handoff_status' => (string) $validated['handoffForm']['status'],
                'handoff_summary' => $summary !== '' ? $summary : null,
                'handoff_next_action_at' => $nextActionAt,
                'handoff_updated_by' => $actor->id,
                'handoff_updated_at' => now(),
                'handoff_version' => (int) $lockedConversation->handoff_version + 1,
            ])->save();

            $freshConversation = $lockedConversation->fresh(['handoffEditor']);

            return [
                'saved' => true,
                'conversation' => $freshConversation instanceof Conversation ? $freshConversation : $lockedConversation,
            ];
        }, 3);

        $this->syncHandoffForm($result['conversation']);

        if (! $result['saved']) {
            Notification::make()
                ->title('Note vừa được người khác cập nhật trước bạn')
                ->body('CRM đã tải lại nội dung mới nhất để tránh ghi đè nhầm phần bàn giao nội bộ.')
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title('Đã lưu note bàn giao')
            ->body('Tóm tắt nội bộ, trạng thái xử lý và lịch follow-up đã được cập nhật cho cả team CSKH.')
            ->success()
            ->send();
    }

    public function sendReply(): void
    {
        if (! $this->isConversationSchemaReady()) {
            return;
        }

        $validated = $this->validate([
            'draftReply' => ['required', 'string', 'max:4000'],
        ]);

        $conversation = $this->selectedConversation;
        $actor = $this->currentUser();

        if (! $conversation instanceof Conversation || ! $actor instanceof User) {
            return;
        }

        $message = null;
        $body = trim((string) $validated['draftReply']);

        DB::transaction(function () use (&$message, $conversation, $actor, $body): void {
            $lockedConversation = Conversation::query()
                ->visibleTo($actor)
                ->lockForUpdate()
                ->findOrFail($conversation->id);

            $messageAt = now();

            $lockedConversation->forceFill([
                'assigned_to' => $lockedConversation->assigned_to ?: $actor->id,
                'status' => Conversation::STATUS_OPEN,
                'unread_count' => 0,
                'latest_message_preview' => Str::limit($body, 120),
                'last_message_at' => $messageAt,
                'last_outbound_at' => $messageAt,
            ])->save();

            $message = ConversationMessage::query()->create([
                'conversation_id' => $lockedConversation->id,
                'direction' => ConversationMessage::DIRECTION_OUTBOUND,
                'message_type' => ConversationMessage::TYPE_TEXT,
                'body' => $body,
                'status' => ConversationMessage::STATUS_PENDING,
                'sent_by_user_id' => $actor->id,
                'message_at' => $messageAt,
            ]);

            SendConversationMessage::dispatch((int) $message->id)->afterCommit();
        }, 3);

        $this->draftReply = '';

        Notification::make()
            ->title('Đã xếp tin nhắn vào hàng gửi')
            ->body('CRM sẽ gửi phản hồi qua '.$conversation->providerLabel().' ngay khi queue xử lý xong.')
            ->success()
            ->send();
    }

    public function retryMessage(int $messageId): void
    {
        if (! $this->isConversationSchemaReady()) {
            return;
        }

        $conversation = $this->selectedConversation;

        if (! $conversation instanceof Conversation) {
            return;
        }

        DB::transaction(function () use ($conversation, $messageId): void {
            $message = ConversationMessage::query()
                ->where('conversation_id', $conversation->id)
                ->whereKey($messageId)
                ->lockForUpdate()
                ->first();

            if (! $message instanceof ConversationMessage || $message->status !== ConversationMessage::STATUS_FAILED) {
                return;
            }

            $message->forceFill([
                'status' => ConversationMessage::STATUS_PENDING,
                'processing_token' => null,
                'processed_at' => null,
                'next_retry_at' => null,
                'last_error' => null,
            ])->save();

            SendConversationMessage::dispatch($message->id)->afterCommit();
        }, 3);

        Notification::make()
            ->title('Đã xếp lại tin nhắn vào hàng gửi')
            ->success()
            ->send();
    }

    public function openLeadForm(): void
    {
        if (! $this->isConversationSchemaReady()) {
            return;
        }

        $conversation = $this->selectedConversation;

        if (! $conversation instanceof Conversation) {
            return;
        }

        if ($conversation->customer_id !== null) {
            Notification::make()
                ->title('Hội thoại này đã được gắn lead')
                ->warning()
                ->send();

            return;
        }

        $this->leadForm = app(ConversationLeadBindingService::class)
            ->prefillForm($conversation, $this->currentUser());
        $this->showLeadModal = true;
        $this->resetErrorBag();
    }

    public function closeLeadForm(): void
    {
        $this->showLeadModal = false;
        $this->resetErrorBag();
    }

    public function createLead(): void
    {
        if (! $this->isConversationSchemaReady()) {
            return;
        }

        $conversation = $this->selectedConversation;

        if (! $conversation instanceof Conversation) {
            return;
        }

        $validated = $this->validate([
            'leadForm.full_name' => ['required', 'string', 'max:255'],
            'leadForm.phone' => ['nullable', 'string', 'max:20'],
            'leadForm.email' => ['nullable', 'email', 'max:255'],
            'leadForm.branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
            'leadForm.assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'leadForm.notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $customer = app(ConversationLeadBindingService::class)->createLead(
            $conversation,
            $validated['leadForm'],
            $this->currentUser(),
        );

        $this->showLeadModal = false;
        $this->leadForm = [];

        Notification::make()
            ->title('Đã tạo lead từ hội thoại')
            ->body('Lead mới: '.$customer->full_name)
            ->success()
            ->send();
    }

    public function updatedLeadFormBranchId(mixed $value): void
    {
        $assignedTo = $this->leadForm['assigned_to'] ?? null;

        if (! filled($assignedTo)) {
            return;
        }

        $isAllowed = app(PatientAssignmentAuthorizer::class)
            ->scopeAssignableStaff(
                query: User::query()->whereKey((int) $assignedTo),
                actor: $this->currentUser(),
                branchId: filled($value) ? (int) $value : null,
            )
            ->exists();

        if (! $isAllowed) {
            $this->leadForm['assigned_to'] = null;
        }
    }

    public function customerEditUrl(?Conversation $conversation): ?string
    {
        if (! $conversation instanceof Conversation || $conversation->customer_id === null) {
            return null;
        }

        return CustomerResource::getUrl('edit', ['record' => $conversation->customer_id]);
    }

    public function messageStatusLabel(string $status): string
    {
        return match ($status) {
            ConversationMessage::STATUS_SENT => 'Đã gửi',
            ConversationMessage::STATUS_FAILED => 'Lỗi gửi',
            ConversationMessage::STATUS_PENDING => 'Đang chờ gửi',
            ConversationMessage::STATUS_IGNORED => 'Đã bỏ qua',
            default => 'Đã nhận',
        };
    }

    protected function currentUser(): ?User
    {
        $authUser = auth()->user();

        return $authUser instanceof User ? $authUser : null;
    }

    protected function markConversationAsRead(int $conversationId): void
    {
        if (! $this->isConversationSchemaReady()) {
            return;
        }

        $actor = $this->currentUser();

        if (! $actor instanceof User) {
            return;
        }

        Conversation::query()
            ->visibleTo($actor)
            ->whereKey($conversationId)
            ->where('unread_count', '>', 0)
            ->update(['unread_count' => 0]);
    }

    /**
     * @return array{search:string,provider:string,tab:string}
     */
    protected function currentFilters(): array
    {
        return [
            'search' => trim($this->search),
            'provider' => trim($this->providerFilter),
            'tab' => trim($this->inboxTab),
        ];
    }

    protected function syncHandoffForm(?Conversation $conversation): void
    {
        $payload = [
            'priority' => $conversation?->handoffPriorityValue() ?? Conversation::PRIORITY_NORMAL,
            'status' => $conversation?->handoffStatusValue() ?? Conversation::HANDOFF_STATUS_NEW,
            'next_action_at' => $conversation?->handoff_next_action_at?->format('Y-m-d\TH:i') ?? '',
            'summary' => (string) ($conversation?->handoff_summary ?? ''),
            'version' => (int) ($conversation?->handoff_version ?? 0),
        ];

        $this->handoffForm = $payload;
        $this->handoffSnapshot = $payload;
    }

    protected function handoffFormIsDirty(): bool
    {
        return $this->handoffForm !== $this->handoffSnapshot;
    }

    protected function syncAssignmentForm(?Conversation $conversation): void
    {
        $payload = [
            'assigned_to' => $conversation?->assigned_to !== null
                ? (string) $conversation->assigned_to
                : '',
        ];

        $this->assignmentForm = $payload;
        $this->assignmentSnapshot = $payload;
    }

    protected function assignmentFormIsDirty(): bool
    {
        return $this->assignmentForm !== $this->assignmentSnapshot;
    }

    protected function syncSelectionToCurrentFilters(): void
    {
        if (! $this->isConversationSchemaReady()) {
            $this->selectedConversationId = null;
            $this->resetVisibleMessageLimit();
            $this->syncHandoffForm(null);
            $this->syncAssignmentForm(null);

            return;
        }

        $service = app(ConversationInboxReadModelService::class);
        $currentUser = $this->currentUser();
        $filters = $this->currentFilters();
        $previousConversationId = $this->selectedConversationId;
        $selectedConversation = $service->findVisibleConversation(
            $currentUser,
            $this->selectedConversationId,
            $filters,
            $this->visibleMessageLimit,
        );

        if (! $selectedConversation instanceof Conversation) {
            $this->selectedConversationId = $service->resolveInitialConversationId($currentUser, $filters);
        }

        if ($this->selectedConversationId !== $previousConversationId) {
            $this->resetVisibleMessageLimit();
        }

        if ($this->selectedConversationId !== null) {
            $this->markConversationAsRead($this->selectedConversationId);
        }

        $this->syncHandoffForm($this->selectedConversation);
        $this->syncAssignmentForm($this->selectedConversation);
    }

    protected function resetVisibleMessageLimit(): void
    {
        $this->visibleMessageLimit = static::DEFAULT_VISIBLE_MESSAGE_LIMIT;
    }

    public function isConversationSchemaReady(): bool
    {
        return Schema::hasTable('conversations')
            && Schema::hasTable('conversation_messages');
    }
}
