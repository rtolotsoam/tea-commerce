<?php

namespace App\Http\Controllers\Api;

use App\Models\Stock;
use App\Models\Purchase;
use Illuminate\Http\Request;
use App\Models\MarginAnalysis;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class DashboardApiController extends Controller
{
    public function kpis(): JsonResponse
    {
        $stockValue = Stock::join('products', 'stocks.product_id', '=', 'products.id')
            ->sum(DB::raw('stocks.quantity_on_hand * stocks.average_cost'));

        $totalProducts = DB::table('products')->where('is_active', true)->count();

        $avgMargin = MarginAnalysis::avg('margin_percent') ?? 0;

        $potentialProfit = MarginAnalysis::sum('potential_profit') ?? 0;

        $pendingOrders = Purchase::whereIn('status', ['draft', 'ordered'])->count();

        $pendingValue = Purchase::whereIn('status', ['draft', 'ordered'])
            ->sum('total_amount');

        $outOfStock = Stock::where('quantity_available', '<=', 0)->count();

        $lowStock = Stock::whereRaw('quantity_available <= reorder_point')
            ->where('quantity_available', '>', 0)
            ->count();

        return response()->json([
            'stock_value' => round($stockValue, 2),
            'total_products' => $totalProducts,
            'avg_margin' => round($avgMargin, 1),
            'potential_profit' => round($potentialProfit, 2),
            'pending_orders' => $pendingOrders,
            'pending_value' => round($pendingValue, 2),
            'out_of_stock' => $outOfStock,
            'low_stock' => $lowStock,
        ]);
    }

    public function marginTrend(Request $request): JsonResponse
    {
        $period = $request->get('period', 'daily');
        $days = $request->get('days', 30);

        $dateFormat = match($period) {
            'daily' => 'Y-m-d',
            'weekly' => 'Y-W',
            'monthly' => 'Y-m',
            default => 'Y-m-d'
        };

        $data = DB::table('margin_analysis')
            ->select(
                DB::raw("DATE_FORMAT(last_calculated_at, '{$dateFormat}') as date"),
                DB::raw('AVG(margin_percent) as avg_margin'),
                DB::raw('SUM(potential_profit) as total_profit')
            )
            ->where('last_calculated_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'data' => $data->map(function ($item) {
                return [
                    'date' => $item->date,
                    'avg_margin' => round($item->avg_margin, 2),
                    'total_profit' => round($item->total_profit, 2)
                ];
            })
        ]);
    }
}
