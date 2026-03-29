<?php

use App\Filament\Pages\ConversationInbox;
use App\Models\User;
use Database\Seeders\LocalDemoDataSeeder;
use Illuminate\Support\Facades\Schema;
use Jeffgreco13\FilamentBreezy\Middleware\MustTwoFactor;

use function Pest\Laravel\seed;

beforeEach(function (): void {
    $this->withoutMiddleware(MustTwoFactor::class);
});

it('shows a setup notice instead of crashing when the conversation schema is unavailable', function (): void {
    seed(LocalDemoDataSeeder::class);

    Schema::dropIfExists('conversation_messages');
    Schema::dropIfExists('conversations');

    $staff = User::query()
        ->where('email', 'cskh.q1@demo.ident.test')
        ->firstOrFail();

    $this->actingAs($staff)
        ->get(ConversationInbox::getUrl())
        ->assertOk()
        ->assertSee('Inbox hội thoại chưa sẵn sàng')
        ->assertSee('Quản trị viên cần hoàn tất cài đặt dữ liệu hội thoại trước khi đội CSKH sử dụng màn hình này.')
        ->assertDontSee('Internal Server Error');
});
