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
        Schema::create('scraped_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_url', 500)->nullable();
            $table->enum('source_type', ['scraping', 'api']);
            $table->string('supplier_ref', 100)->nullable();
            $table->string('product_name')->nullable();
            $table->decimal('price', 10, 4)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->string('availability', 100)->nullable();
            $table->decimal('stock_quantity', 10, 2)->nullable();
            $table->jsonb('raw_data')->nullable();
            $table->timestamp('scraped_at')->useCurrent();

            $table->index('supplier_id');
            $table->index('scraped_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scraped_data');
    }
};
