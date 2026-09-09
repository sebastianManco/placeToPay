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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_identification')->nullable();
            $table->string('title', 255);
            $table->string('type', 64)->default('complete')->index();
            $table->string('format', 32)->default('pdf');
            $table->string('status', 32)->default('pending')->index();
            $table->json('parameters')->nullable();
            $table->string('file_path', 512)->nullable();
            $table->string('excel_file_path', 512)->nullable();
            $table->json('summary_data')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_identification')
                ->references('identification')
                ->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
