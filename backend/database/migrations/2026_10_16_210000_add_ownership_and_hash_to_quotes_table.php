<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->timestamp('used_at')->nullable()->after('expires_at');
            $table->char('config_hash', 64)->after('configuration');
            $table->index('config_hash');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['config_hash']);
            $table->dropColumn(['user_id', 'used_at', 'config_hash']);
        });
    }
};
