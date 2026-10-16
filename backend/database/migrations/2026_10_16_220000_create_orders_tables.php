<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('order_number', 32)->unique();
            $table->enum('status', ['placed', 'awaiting_payment'])->index();
            $table->char('currency', 3)->default('EGP');
            $table->unsignedInteger('subtotal')->comment('Minor units (piasters)');
            $table->unsignedInteger('total')->comment('Minor units (piasters)');
            $table->json('price_breakdown');
            $table->string('shipping_name', 120);
            $table->string('shipping_phone', 32);
            $table->string('shipping_address_line1');
            $table->string('shipping_address_line2')->nullable();
            $table->string('shipping_city', 120);
            $table->string('shipping_governorate', 120);
            $table->string('shipping_postal_code', 20)->nullable();
            $table->string('shipping_country', 100);
            $table->enum('payment_method', ['cash_on_delivery', 'online']);
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->timestamp('ready_by');
            $table->timestamp('accepted_terms_at');
            $table->boolean('gift_wrap')->default(false);
            $table->string('gift_message', 500)->nullable();
            $table->string('idempotency_key', 255);
            $table->timestamps();

            $table->unique(['user_id', 'idempotency_key'], 'orders_user_idempotency_unique');
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('mood_id')->nullable();
            $table->unsignedBigInteger('technique_id')->nullable();
            $table->boolean('has_text')->default(false);
            $table->unsignedSmallInteger('labor_minutes_total');
            $table->unsignedInteger('unit_price')->comment('Minor units (piasters)');
            $table->enum('production_status', ['placed', 'in_progress', 'completed'])->default('placed');
            $table->json('customization');
            $table->timestamps();

            $table->index(['mood_id', 'technique_id', 'has_text'], 'order_items_filter_index');
            $table->index('production_status');
        });

        Schema::create('production_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('event_type', ['placed']);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_events');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
