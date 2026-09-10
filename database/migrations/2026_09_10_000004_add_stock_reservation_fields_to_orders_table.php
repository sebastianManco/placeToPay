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
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('stock_reserved')->default(false)->after('status')->index();
            $table->timestamp('stock_reserved_at')->nullable()->after('stock_reserved');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['stock_reserved']);
            $table->dropColumn(['stock_reserved', 'stock_reserved_at']);
        });
    }
};
