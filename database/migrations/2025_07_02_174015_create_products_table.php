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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 100)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('supplier_ref', 100)->nullable();
            $table->decimal('unit_weight', 10, 3)->nullable();
            $table->enum('unit_type', ['kg', 'g', 'unit', 'box'])->default('kg');
            $table->decimal('min_order_quantity', 10, 2)->default(1);
            $table->integer('lead_time_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('shopify_product_id', 100)->nullable();
            $table->string('shopify_variant_id', 100)->nullable();
            $table->timestamps();

            $table->index('sku');
            $table->index('supplier_id');
            $table->index(['shopify_product_id', 'shopify_variant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
