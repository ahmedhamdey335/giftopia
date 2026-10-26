<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->timestamp('planned_start')->nullable();
            $table->timestamp('planned_finish')->nullable();
            $table->json('schedule_allocations')->nullable();
        });

        Schema::create('proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->text('text');
            $table->string('font_name');
            $table->string('text_color_name');
            $table->text('note')->nullable();
            $table->foreignUuid('preview_asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->timestamp('requested_at');
            $table->timestamp('responded_at')->nullable();
            $table->text('response_note')->nullable();
            $table->timestamp('auto_approve_at');
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamps();
            $table->unique(['order_item_id', 'version']);
        });

        Schema::create('blackout_dates', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('reason', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('refund_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->string('status')->default('requested');
            $table->unsignedInteger('requested_amount')->nullable();
            $table->unsignedInteger('approved_amount')->nullable();
            $table->text('decision_note')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->foreign('refund_request_id')->references('id')->on('refund_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['refund_request_id']);
        });
        Schema::dropIfExists('refund_requests');
        Schema::dropIfExists('blackout_dates');
        Schema::dropIfExists('proofs');
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['planned_start', 'planned_finish', 'schedule_allocations']);
        });
    }
};
