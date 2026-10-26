<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kind');
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('refund_request_id')->nullable()->index();
            $table->string('disk');
            $table->string('path');
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->string('original_name');
            $table->timestamp('attached_at')->nullable();
            $table->timestamps();

            $table->index(['kind', 'attached_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
