<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared('
            CREATE OR REPLACE FUNCTION update_stock_after_delivery()
            RETURNS TRIGGER AS $$
            BEGIN
                IF NEW.status = \'delivered\' AND OLD.status != \'delivered\' THEN
                    -- Insérer les mouvements de stock
                    INSERT INTO stock_movements (product_id, movement_type, movement_reason, reference_type, reference_id, quantity, unit_cost, created_at)
                    SELECT
                        pi.product_id,
                        \'in\'::text,
                        \'purchase\'::text,
                        \'purchase\'::text,
                        NEW.id,
                        pi.quantity,
                        pi.unit_price,
                        NOW()
                    FROM purchase_items pi
                    WHERE pi.purchase_id = NEW.id;

                    -- Mettre à jour les stocks
                    INSERT INTO stocks (product_id, quantity_on_hand, last_purchase_date, last_purchase_price, created_at, updated_at)
                    SELECT
                        pi.product_id,
                        pi.quantity,
                        NEW.delivery_date,
                        pi.unit_price,
                        NOW(),
                        NOW()
                    FROM purchase_items pi
                    WHERE pi.purchase_id = NEW.id
                    ON CONFLICT (product_id) DO UPDATE
                    SET
                        quantity_on_hand = stocks.quantity_on_hand + EXCLUDED.quantity_on_hand,
                        last_purchase_date = EXCLUDED.last_purchase_date,
                        last_purchase_price = EXCLUDED.last_purchase_price,
                        updated_at = NOW();
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');

        DB::unprepared('
            CREATE TRIGGER trigger_update_stock_after_delivery
            AFTER UPDATE ON purchases
            FOR EACH ROW
            EXECUTE FUNCTION update_stock_after_delivery();
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trigger_update_stock_after_delivery ON purchases');
        DB::unprepared('DROP FUNCTION IF EXISTS update_stock_after_delivery()');
    }
};
