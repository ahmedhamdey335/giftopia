<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('status')->change();
            $table->string('payment_status')->default('pending')->change();
            $table->string('payment_method')->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('production_status')->default('placed')->change();
        });

        Schema::table('production_events', function (Blueprint $table) {
            $table->string('event_type')->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', ['placed', 'awaiting_payment'])->change();
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending')->change();
            $table->enum('payment_method', ['cash_on_delivery', 'online'])->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->enum('production_status', ['placed', 'in_progress', 'completed'])->default('placed')->change();
        });

        Schema::table('production_events', function (Blueprint $table) {
            $table->enum('event_type', ['placed'])->change();
        });
    }
};
