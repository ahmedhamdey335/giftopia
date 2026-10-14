<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('option_rules', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->enum('rule', ['blocks', 'requires']);
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->string('message');
            $table->timestamps();

            $table->index(['subject_type', 'subject_id'], 'option_rules_subject_index');
            $table->index(['target_type', 'target_id'], 'option_rules_target_index');
            $table->index('rule');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('option_rules');
    }
};
