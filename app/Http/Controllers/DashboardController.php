<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use App\Models\Product;
use App\Models\Category;
use App\Models\Purchase;
use Illuminate\Http\Request;
use App\Models\MarginAnalysis;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        // Calculer les KPIs
        $kpis = $this->calculateKpis();

        // Top 10 produits par marge
        $topProducts = Product::with(['marginAnalysis', 'stock'])
            ->whereHas('marginAnalysis')
            ->join('margin_analysis', 'products.id', '=', 'margin_analysis.product_id')
            ->orderBy('margin_analysis.margin_percent', 'desc')
            ->take(10)
            ->select('products.*')
            ->get();

        // Dernières commandes
        $recentOrders = Purchase::with('supplier')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        // Tendance des marges (30 derniers jours)
        $marginTrend = DB::table('margin_analysis')
            ->select(
                DB::raw('DATE(last_calculated_at) as date'),
                DB::raw('AVG(margin_percent) as avg_margin')
            )
            ->where('last_calculated_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Statistiques par catégorie
        $categoryStats = Category::withCount('products')
            ->get();

        return view('dashboard.index', compact(
            'kpis',
            'topProducts',
            'recentOrders',
            'marginTrend',
            'categoryStats'
        ));
    }

    private function calculateKpis()
    {
        $stockValue = Stock::join('products', 'stocks.product_id', '=', 'products.id')
            ->sum(DB::raw('stocks.quantity_on_hand * stocks.average_cost'));

        $totalProducts = Product::where('is_active', true)->count();

        $avgMargin = MarginAnalysis::avg('margin_percent') ?? 0;

        $potentialProfit = MarginAnalysis::sum('potential_profit') ?? 0;

        $pendingOrders = Purchase::whereIn('status', ['draft', 'ordered'])->count();

        $pendingValue = Purchase::whereIn('status', ['draft', 'ordered'])
            ->sum('total_amount');

        $outOfStock = Stock::where('quantity_available', '<=', 0)->count();

        $lowStock = Stock::whereRaw('quantity_available <= reorder_point')
            ->where('quantity_available', '>', 0)
            ->count();

        return [
            'stock_value' => round($stockValue, 2),
            'total_products' => $totalProducts,
            'avg_margin' => round($avgMargin, 1),
            'potential_profit' => round($potentialProfit, 2),
            'pending_orders' => $pendingOrders,
            'pending_value' => round($pendingValue, 2),
            'out_of_stock' => $outOfStock,
            'low_stock' => $lowStock,
        ];
    }

    public function export()
    {
        $kpis = $this->calculateKpis();

        // Créer un CSV avec les KPIs
        $csv = "Indicateur,Valeur\n";
        $csv .= "Valeur du Stock," . $kpis['stock_value'] . " EUR\n";
        $csv .= "Nombre de Produits," . $kpis['total_products'] . "\n";
        $csv .= "Marge Moyenne," . $kpis['avg_margin'] . "%\n";
        $csv .= "Profit Potentiel," . $kpis['potential_profit'] . " EUR\n";
        $csv .= "Commandes en Cours," . $kpis['pending_orders'] . "\n";
        $csv .= "Valeur Commandes en Cours," . $kpis['pending_value'] . " EUR\n";
        $csv .= "Produits en Rupture," . $kpis['out_of_stock'] . "\n";
        $csv .= "Produits Stock Faible," . $kpis['low_stock'] . "\n";

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="dashboard_' . date('Y-m-d') . '.csv"');
    }
}
