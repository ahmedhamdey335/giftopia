<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_events', function (Blueprint $table) {
            $table->foreignId('order_item_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
            $table->string('from_status')->nullable()->after('event_type');
            $table->string('to_status')->nullable()->after('from_status');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('proof_status')->default('none')->after('production_status');
            $table->json('checklist')->nullable()->after('proof_status');
            $table->boolean('needs_attention')->default(false)->after('checklist');
            $table->string('attention_note')->nullable()->after('needs_attention');
            $table->timestamp('started_at')->nullable()->after('attention_note');
            $table->timestamp('cure_started_at')->nullable()->after('started_at');
            $table->timestamp('qc_at')->nullable()->after('cure_started_at');
            $table->timestamp('packed_at')->nullable()->after('qc_at');
            $table->unsignedSmallInteger('actual_labor_minutes')->nullable()->after('packed_at');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('courier_name', 120)->nullable();
            $table->string('courier_phone', 32)->nullable();
            $table->string('courier_reference', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['courier_name', 'courier_phone', 'courier_reference']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn([
                'proof_status',
                'checklist',
                'needs_attention',
                'attention_note',
                'started_at',
                'cure_started_at',
                'qc_at',
                'packed_at',
                'actual_labor_minutes',
            ]);
        });

        Schema::table('production_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_item_id');
            $table->dropColumn(['from_status', 'to_status']);
        });
    }
};
