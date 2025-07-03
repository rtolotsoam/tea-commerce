<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('margin_analysis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained();
            $table->decimal('purchase_price', 10, 4);
            $table->decimal('selling_price', 10, 2)->nullable();
            $table->decimal('stock_quantity', 10, 2)->default(0);
            $table->timestamp('last_calculated_at')->useCurrent();

            // Colonnes calculées classiques (non générées)
            $table->decimal('margin_amount', 10, 2)->default(0);
            $table->decimal('margin_percent', 5, 2)->default(0);
            $table->decimal('potential_profit', 12, 2)->default(0);
        });

        // Création de la fonction trigger qui calcule les colonnes
        DB::statement(
            <<<SQL
CREATE OR REPLACE FUNCTION update_margin_analysis_calculated_columns()
RETURNS TRIGGER AS \$\$
BEGIN
    NEW.margin_amount := COALESCE(NEW.selling_price, 0) - COALESCE(NEW.purchase_price, 0);

    IF NEW.selling_price > 0 THEN
        NEW.margin_percent := ((NEW.selling_price - NEW.purchase_price) / NEW.selling_price) * 100;
    ELSE
        NEW.margin_percent := 0;
    END IF;

    NEW.potential_profit := NEW.margin_amount * COALESCE(NEW.stock_quantity, 0);

    RETURN NEW;
END;
\$\$ LANGUAGE plpgsql;
SQL
        );

        // Création du trigger BEFORE INSERT OR UPDATE
        DB::statement(
            <<<SQL
CREATE TRIGGER trg_update_margin_analysis_calculated_columns
BEFORE INSERT OR UPDATE ON margin_analysis
FOR EACH ROW EXECUTE FUNCTION update_margin_analysis_calculated_columns();
SQL
        );

        // Création des index
        DB::statement('CREATE INDEX idx_margin_percent ON margin_analysis(margin_percent)');
        DB::statement('CREATE INDEX idx_potential_profit ON margin_analysis(potential_profit)');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS trg_update_margin_analysis_calculated_columns ON margin_analysis');
        DB::statement('DROP FUNCTION IF EXISTS update_margin_analysis_calculated_columns()');

        Schema::dropIfExists('margin_analysis');
    }
};
