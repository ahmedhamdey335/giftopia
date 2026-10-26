<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('delivery_fee')->default(0)->after('subtotal');
            $table->unsignedInteger('deposit_amount')->default(0)->after('total');
            $table->unsignedInteger('balance_amount')->default(0)->after('deposit_amount');
            $table->unsignedInteger('amount_paid')->default(0)->after('balance_amount');
            $table->timestamp('deposit_paid_at')->nullable()->after('ready_by');
            $table->timestamp('balance_paid_at')->nullable()->after('deposit_paid_at');
            $table->timestamp('delivered_at')->nullable()->after('balance_paid_at');
            $table->timestamp('deposit_expires_at')->nullable()->after('delivered_at');
            $table->timestamp('cancelled_at')->nullable()->after('deposit_expires_at');
            $table->string('payment_status')->default('unpaid')->change();
        });

        DB::table('orders')->where('payment_status', 'pending')->update(['payment_status' => 'unpaid']);
        DB::table('orders')->where('payment_status', 'failed')->update(['payment_status' => 'unpaid']);
        DB::table('orders')->where('payment_method', 'online')->update(['payment_method' => 'card']);
        DB::table('orders')->where('payment_method', 'cash_on_delivery')->update(['payment_method' => 'card']);
        DB::table('orders')->whereIn('status', ['placed', 'awaiting_payment'])->update(['status' => 'awaiting_deposit']);
        DB::table('order_items')->where('production_status', 'in_progress')->update(['production_status' => 'in_production']);
        DB::table('order_items')->where('production_status', 'completed')->update(['production_status' => 'packed']);

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->string('method');
            $table->string('provider');
            $table->string('provider_reference');
            $table->unsignedInteger('amount')->comment('Minor units (piasters)');
            $table->char('currency', 3);
            $table->string('status')->default('pending');
            $table->text('redirect_url')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_reference', 'kind'], 'payments_provider_reference_kind_unique');
            $table->index(['order_id', 'kind', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_fee',
                'deposit_amount',
                'balance_amount',
                'amount_paid',
                'deposit_paid_at',
                'balance_paid_at',
                'delivered_at',
                'deposit_expires_at',
                'cancelled_at',
            ]);
        });
    }
};
