<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $branch = Branch::query()->inRandomOrder()->first() ?? Branch::factory()->create();
        $channelKey = 'oa_'.fake()->numerify('####');
        $externalUserId = 'zalo_user_'.fake()->numerify('#####');

        return [
            'provider' => Conversation::PROVIDER_ZALO,
            'channel_key' => $channelKey,
            'external_conversation_key' => implode(':', [
                Conversation::PROVIDER_ZALO,
                $channelKey,
                $externalUserId,
            ]),
            'external_user_id' => $externalUserId,
            'external_display_name' => fake()->name(),
            'branch_id' => $branch->id,
            'status' => Conversation::STATUS_OPEN,
            'unread_count' => 0,
            'latest_message_preview' => fake()->sentence(),
            'last_message_at' => now(),
            'last_inbound_at' => now(),
            'handoff_priority' => Conversation::PRIORITY_NORMAL,
            'handoff_status' => Conversation::HANDOFF_STATUS_NEW,
            'handoff_version' => 0,
        ];
    }

    public function facebook(): static
    {
        return $this->state(function (): array {
            $channelKey = 'page_'.fake()->numerify('#####');
            $externalUserId = 'psid_'.fake()->numerify('########');

            return [
                'provider' => Conversation::PROVIDER_FACEBOOK,
                'channel_key' => $channelKey,
                'external_conversation_key' => implode(':', [
                    Conversation::PROVIDER_FACEBOOK,
                    $channelKey,
                    $externalUserId,
                ]),
                'external_user_id' => $externalUserId,
            ];
        });
    }
}
