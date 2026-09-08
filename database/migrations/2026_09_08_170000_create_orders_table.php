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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 64)->unique();
            $table->unsignedInteger('user_identification')->nullable();
            $table->string('session_id', 128)->nullable()->index();
            $table->string('status', 32)->default('in_cart')->index();
            $table->decimal('total_amount', 12, 2)->default(0.00);
            $table->string('currency', 8)->default('COP');
            $table->string('customer_name', 128)->nullable();
            $table->string('customer_email', 128)->nullable();
            $table->string('customer_phone', 32)->nullable();
            $table->string('customer_address', 255)->nullable();
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
        Schema::dropIfExists('orders');
    }
};
