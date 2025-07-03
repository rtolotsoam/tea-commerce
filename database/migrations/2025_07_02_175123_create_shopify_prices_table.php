<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shopify_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('shopify_product_id', 100);
            $table->string('shopify_variant_id', 100)->nullable();
            $table->decimal('selling_price', 10, 2);
            $table->decimal('compare_at_price', 10, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'shopify_product_id', 'shopify_variant_id'], 'unique_shopify_product');
            $table->index('last_sync_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopify_prices');
    }
};
