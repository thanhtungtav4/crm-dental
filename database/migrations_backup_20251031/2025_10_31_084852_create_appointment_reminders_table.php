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
        Schema::create('appointment_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['sms', 'email', 'call'])->default('sms');
            $table->dateTime('scheduled_at'); // Thời gian dự định gửi
            $table->dateTime('sent_at')->nullable(); // Thời gian đã gửi thực tế
            $table->enum('status', [
                'pending',   // Chờ gửi
                'sent',      // Đã gửi
                'failed',    // Gửi thất bại
                'cancelled'  // Đã hủy
            ])->default('pending');
            $table->text('message')->nullable(); // Nội dung tin nhắn
            $table->string('recipient')->nullable(); // Số điện thoại/email người nhận
            $table->text('error_message')->nullable(); // Thông báo lỗi nếu failed
            $table->json('metadata')->nullable(); // Dữ liệu thêm (response từ SMS gateway)
            $table->timestamps();
            
            // Indexes
            $table->index(['appointment_id', 'status']);
            $table->index(['scheduled_at', 'status']);
            $table->index('sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_reminders');
    }
};
