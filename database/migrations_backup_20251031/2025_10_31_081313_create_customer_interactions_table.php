<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customer_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // Staff who handled
            $table->enum('type', [
                'call',           // Gọi điện
                'sms',            // Gửi SMS
                'email',          // Gửi email
                'facebook',       // Chat Facebook
                'zalo',           // Chat Zalo
                'meeting',        // Gặp trực tiếp
                'appointment',    // Đặt lịch hẹn
                'follow_up',      // Theo dõi
                'other'
            ])->default('call');
            $table->enum('direction', ['inbound', 'outbound'])->default('outbound'); // Incoming or Outgoing
            $table->string('subject')->nullable(); // Tiêu đề cuộc tương tác
            $table->text('description')->nullable(); // Nội dung chi tiết
            $table->enum('outcome', [
                'successful',     // Thành công
                'no_answer',      // Không nghe máy
                'busy',           // Máy bận
                'voicemail',      // Để lại tin nhắn
                'interested',     // Quan tâm
                'not_interested', // Không quan tâm
                'callback',       // Hẹn gọi lại
                'scheduled',      // Đã đặt lịch
                'other'
            ])->nullable();
            $table->dateTime('interacted_at')->nullable(); // Thời gian tương tác
            $table->integer('duration')->nullable()->comment('Duration in seconds'); // Thời lượng (giây)
            $table->text('notes')->nullable(); // Ghi chú thêm
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['customer_id', 'interacted_at']);
            $table->index(['type', 'interacted_at']);
            $table->index('outcome');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_interactions');
    }
};
