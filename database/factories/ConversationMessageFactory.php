<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ConversationMessage>
 */
class ConversationMessageFactory extends Factory
{
    protected $model = ConversationMessage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'direction' => ConversationMessage::DIRECTION_INBOUND,
            'message_type' => ConversationMessage::TYPE_TEXT,
            'provider_message_id' => fake()->optional()->uuid(),
            'source_event_fingerprint' => sha1((string) fake()->unique()->uuid()),
            'body' => fake()->sentence(),
            'payload_summary' => ['event_name' => 'user_send_text'],
            'status' => ConversationMessage::STATUS_RECEIVED,
            'attempts' => 0,
            'message_at' => now(),
        ];
    }
}
