<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\FacebookWebhookEvent;
use App\Support\ConversationProvider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class FacebookMessengerInboundMessageNormalizer implements InboundMessageNormalizer
{
    public function __construct(
        protected IntegrationOperationalPayloadSanitizer $integrationOperationalPayloadSanitizer,
    ) {}

    public function provider(): ConversationProvider
    {
        return ConversationProvider::Facebook;
    }

    public function normalize(object $event, array $payload): ?ConversationMessage
    {
        if (! $event instanceof FacebookWebhookEvent) {
            throw new InvalidArgumentException('Facebook inbound normalizer requires a FacebookWebhookEvent instance.');
        }

        $messageText = trim((string) data_get($payload, 'message.text', ''));
        $pageId = trim((string) (data_get($payload, 'page_id') ?? data_get($payload, 'recipient.id') ?? ''));
        $senderId = trim((string) data_get($payload, 'sender.id', ''));
        $isEcho = data_get($payload, 'message.is_echo') === true;

        if ($isEcho) {
            $this->markEventIgnored($event, 'Bỏ qua outbound echo từ Facebook Page.');

            return null;
        }

        if ($messageText === '' || $pageId === '' || $senderId === '') {
            $this->markEventIgnored($event, 'Chỉ hỗ trợ inbound text message Facebook Messenger ở v1.');

            return null;
        }

        $messageAt = $this->resolveMessageTimestamp($payload);
        $providerMessageId = $this->resolveProviderMessageId($payload);
        $externalConversationKey = implode(':', [
            $this->provider()->value,
            $pageId,
            $senderId,
        ]);
        $displayName = $this->resolveDisplayName($payload);
        $branchId = $this->resolveDefaultBranchId();

        try {
            return DB::transaction(function () use (
                $event,
                $payload,
                $messageText,
                $senderId,
                $pageId,
                $messageAt,
                $providerMessageId,
                $externalConversationKey,
                $displayName,
                $branchId,
            ): ?ConversationMessage {
                $conversation = Conversation::query()
                    ->lockForUpdate()
                    ->where('provider', $this->provider()->value)
                    ->where('channel_key', $pageId)
                    ->where('external_conversation_key', $externalConversationKey)
                    ->first();

                if (! $conversation instanceof Conversation) {
                    $conversation = Conversation::query()->create([
                        'provider' => $this->provider()->value,
                        'channel_key' => $pageId,
                        'external_conversation_key' => $externalConversationKey,
                        'external_user_id' => $senderId,
                        'external_display_name' => $displayName,
                        'branch_id' => $branchId,
                        'status' => Conversation::STATUS_OPEN,
                        'unread_count' => 0,
                    ]);
                } else {
                    $conversation->fill([
                        'external_display_name' => $displayName !== '' ? $displayName : $conversation->external_display_name,
                        'branch_id' => $conversation->branch_id ?: $branchId,
                        'status' => Conversation::STATUS_OPEN,
                    ])->save();
                }

                $message = $providerMessageId !== null
                    ? ConversationMessage::query()
                        ->where('conversation_id', $conversation->id)
                        ->where('provider_message_id', $providerMessageId)
                        ->first()
                    : ConversationMessage::query()
                        ->where('conversation_id', $conversation->id)
                        ->where('source_event_fingerprint', $event->event_fingerprint)
                        ->first();

                if ($message instanceof ConversationMessage) {
                    $this->markEventNormalized($event, $conversation, $message);

                    return $message;
                }

                $message = ConversationMessage::query()->create([
                    'conversation_id' => $conversation->id,
                    'direction' => ConversationMessage::DIRECTION_INBOUND,
                    'message_type' => ConversationMessage::TYPE_TEXT,
                    'provider_message_id' => $providerMessageId,
                    'source_event_fingerprint' => $event->event_fingerprint,
                    'body' => $messageText,
                    'payload_summary' => $this->integrationOperationalPayloadSanitizer->sanitizeFacebookWebhookPayload($payload),
                    'status' => ConversationMessage::STATUS_RECEIVED,
                    'message_at' => $messageAt,
                ]);

                $conversation->forceFill([
                    'external_display_name' => $displayName !== '' ? $displayName : $conversation->external_display_name,
                    'branch_id' => $conversation->branch_id ?: $branchId,
                    'status' => Conversation::STATUS_OPEN,
                    'unread_count' => max(0, (int) $conversation->unread_count) + 1,
                    'latest_message_preview' => Str::limit(trim($messageText), 120),
                    'last_message_at' => $messageAt,
                    'last_inbound_at' => $messageAt,
                ])->save();

                $this->markEventNormalized($event, $conversation, $message);

                return $message;
            }, 3);
        } catch (Throwable $throwable) {
            $event->forceFill([
                'normalize_status' => 'failed',
                'normalized_at' => now(),
                'error_message' => $throwable->getMessage(),
            ])->save();

            report($throwable);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveProviderMessageId(array $payload): ?string
    {
        $value = trim((string) data_get($payload, 'message.mid', ''));

        return $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveDisplayName(array $payload): string
    {
        return trim((string) (
            data_get($payload, 'sender.name')
            ?? data_get($payload, 'sender.display_name')
            ?? ''
        ));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveMessageTimestamp(array $payload): Carbon
    {
        $timestamp = data_get($payload, 'timestamp') ?? data_get($payload, 'entry_time');

        if (is_numeric($timestamp)) {
            $normalized = (int) $timestamp;

            if ($normalized > 1_000_000_000_000) {
                $normalized = (int) floor($normalized / 1000);
            }

            if ($normalized > 0) {
                return now()->setTimestamp($normalized);
            }
        }

        return now();
    }

    protected function resolveDefaultBranchId(): ?int
    {
        $defaultBranchCode = $this->provider()->inboxDefaultBranchCode();

        if ($defaultBranchCode === '') {
            return null;
        }

        $branchId = Branch::query()
            ->where('code', $defaultBranchCode)
            ->where('active', true)
            ->value('id');

        return $branchId !== null ? (int) $branchId : null;
    }

    protected function markEventIgnored(FacebookWebhookEvent $event, string $reason): void
    {
        $event->forceFill([
            'normalize_status' => 'ignored',
            'conversation_id' => null,
            'message_id' => null,
            'normalized_at' => now(),
            'error_message' => $reason,
        ])->save();
    }

    protected function markEventNormalized(
        FacebookWebhookEvent $event,
        Conversation $conversation,
        ConversationMessage $message,
    ): void {
        $event->forceFill([
            'normalize_status' => 'normalized',
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'normalized_at' => now(),
            'error_message' => null,
        ])->save();
    }
}
