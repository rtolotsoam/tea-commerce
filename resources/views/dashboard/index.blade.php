@extends('layouts.app')

@section('title', 'Tableau de bord')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Tableau de bord</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportDashboard()">
                <i class="bi bi-download"></i> Exporter
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="refreshDashboard()">
                <i class="bi bi-arrow-clockwise"></i> Actualiser
            </button>
        </div>
    </div>
</div>

<!-- KPIs -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-primary mb-3">
            <div class="card-body">
                <h5 class="card-title">Valeur du Stock</h5>
                <h2 class="mb-0">{{ number_format($kpis['stock_value'], 2) }} €</h2>
                <small>{{ $kpis['total_products'] }} produits</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-success mb-3">
            <div class="card-body">
                <h5 class="card-title">Marge Moyenne</h5>
                <h2 class="mb-0">{{ number_format($kpis['avg_margin'], 1) }}%</h2>
                <small>Profit potentiel: {{ number_format($kpis['potential_profit'], 2) }} €</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-warning mb-3">
            <div class="card-body">
                <h5 class="card-title">Commandes en Cours</h5>
                <h2 class="mb-0">{{ $kpis['pending_orders'] }}</h2>
                <small>Valeur: {{ number_format($kpis['pending_value'], 2) }} €</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-info mb-3">
            <div class="card-body">
                <h5 class="card-title">Produits en Rupture</h5>
                <h2 class="mb-0">{{ $kpis['out_of_stock'] }}</h2>
                <small>À réapprovisionner: {{ $kpis['low_stock'] }}</small>
            </div>
        </div>
    </div>
</div>

<!-- Graphiques -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Évolution des Marges</h5>
            </div>
            <div class="card-body">
                <canvas id="marginChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Répartition par Catégorie</h5>
            </div>
            <div class="card-body">
                <canvas id="categoryChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Tableaux -->
<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Top 10 Produits par Marge</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Produit</th>
                                <th>Marge</th>
                                <th>Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($topProducts as $product)
                            <tr>
                                <td>{{ $product->sku }}</td>
                                <td>{{ $product->name }}</td>
                                <td>
                                    <span class="badge bg-success">
                                        {{ number_format($product->marginAnalysis->margin_percent, 1) }}%
                                    </span>
                                </td>
                                <td>{{ $product->stock->quantity_available ?? 0 }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Dernières Commandes</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>N°</th>
                                <th>Fournisseur</th>
                                <th>Date</th>
                                <th>Montant</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentOrders as $order)
                            <tr>
                                <td>{{ $order->purchase_number }}</td>
                                <td>{{ $order->supplier->name }}</td>
                                <td>{{ $order->order_date->format('d/m/Y') }}</td>
                                <td>{{ number_format($order->total_amount, 2) }} €</td>
                                <td>
                                    <span class="badge bg-{{ $order->status === 'delivered' ? 'success' : 'warning' }}">
                                        {{ ucfirst($order->status) }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Graphique des marges
const marginCtx = document.getElementById('marginChart').getContext('2d');
new Chart(marginCtx, {
    type: 'line',
    data: {
        labels: {!! json_encode($marginTrend->pluck('date')) !!},
        datasets: [{
            label: 'Marge Moyenne (%)',
            data: {!! json_encode($marginTrend->pluck('avg_margin')) !!},
            borderColor: 'rgb(75, 192, 192)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});

// Graphique des catégories
const categoryCtx = document.getElementById('categoryChart').getContext('2d');
new Chart(categoryCtx, {
    type: 'doughnut',
    data: {
        labels: {!! json_encode($categoryStats->pluck('name')) !!},
        datasets: [{
            data: {!! json_encode($categoryStats->pluck('product_count')) !!},
            backgroundColor: [
                'rgb(255, 99, 132)',
                'rgb(54, 162, 235)',
                'rgb(255, 205, 86)',
                'rgb(75, 192, 192)'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});

function exportDashboard() {
    window.location.href = '{{ route("dashboard.export") }}';
}

function refreshDashboard() {
    window.location.reload();
}
</script>
@endpush
