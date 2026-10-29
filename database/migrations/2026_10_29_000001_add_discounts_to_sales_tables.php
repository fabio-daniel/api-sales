<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sale_details', function (Blueprint $table) {
            $table->decimal('discount_percent', 5, 2)->default(0)->after('subtotal');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('discount_percent');
            $table->decimal('subtotal_with_discount', 10, 2)->default(0)->after('discount_amount');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('total_discount', 10, 2)->default(0)->after('total');
        });

        // Las ventas anteriores no tenían descuento: el subtotal con descuento es el subtotal.
        DB::table('sale_details')->update(['subtotal_with_discount' => DB::raw('subtotal')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('total_discount');
        });

        Schema::table('sale_details', function (Blueprint $table) {
            $table->dropColumn(['discount_percent', 'discount_amount', 'subtotal_with_discount']);
        });
    }
};
