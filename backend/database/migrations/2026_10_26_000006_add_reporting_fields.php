<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('ready_for_delivery_at')->nullable()->after('balance_paid_at');
        });

        Schema::create('studio_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('session_id');
            $table->unsignedTinyInteger('step');
            $table->string('event')->default('viewed');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['created_at', 'step', 'event']);
            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_events');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('ready_for_delivery_at');
        });
    }
};
