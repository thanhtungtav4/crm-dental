<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Support\ConversationProvider;
use InvalidArgumentException;

class ConversationProviderManager
{
    public function __construct(
        protected FacebookMessengerInboundMessageNormalizer $facebookMessengerInboundMessageNormalizer,
        protected FacebookMessengerMessageClient $facebookMessengerMessageClient,
        protected ZaloInboundMessageNormalizer $zaloInboundMessageNormalizer,
        protected ZaloOaMessageClient $zaloOaMessageClient,
    ) {}

    public function inboundNormalizerFor(string|ConversationProvider $provider): InboundMessageNormalizer
    {
        return match ($this->resolveProvider($provider)) {
            ConversationProvider::Zalo => $this->zaloInboundMessageNormalizer,
            ConversationProvider::Facebook => $this->facebookMessengerInboundMessageNormalizer,
        };
    }

    public function outboundClientForMessage(ConversationMessage $message): OutboundMessageClient
    {
        $message->loadMissing('conversation');

        if (! $message->conversation instanceof Conversation) {
            throw new InvalidArgumentException('Conversation message is missing its conversation provider context.');
        }

        return $this->outboundClientForProvider($message->conversation->provider);
    }

    public function outboundClientForProvider(string|ConversationProvider $provider): OutboundMessageClient
    {
        return match ($this->resolveProvider($provider)) {
            ConversationProvider::Zalo => $this->zaloOaMessageClient,
            ConversationProvider::Facebook => $this->facebookMessengerMessageClient,
        };
    }

    protected function resolveProvider(string|ConversationProvider $provider): ConversationProvider
    {
        if ($provider instanceof ConversationProvider) {
            return $provider;
        }

        $resolvedProvider = ConversationProvider::tryFromNullable($provider);

        if (! $resolvedProvider instanceof ConversationProvider) {
            throw new InvalidArgumentException("Unsupported conversation provider [{$provider}].");
        }

        return $resolvedProvider;
    }
}
