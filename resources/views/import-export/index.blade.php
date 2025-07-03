@extends('layouts.app')

@section('title', 'Import/Export')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Import/Export CSV</h1>
</div>

<div class="row">
    <!-- Import Section -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-upload"></i> Import</h5>
            </div>
            <div class="card-body">
                <form id="importForm" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label for="importType" class="form-label">Type d'import</label>
                        <select class="form-select" id="importType" name="type">
                            <option value="purchases">Commandes Fournisseurs</option>
                            <option value="products">Produits</option>
                            <option value="conditions">Conditions d'Achat</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="csvFile" class="form-label">Fichier CSV</label>
                        <input class="form-control" type="file" id="csvFile" name="file" accept=".csv" required>
                        <div class="form-text">Format CSV, max 10MB</div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-upload"></i> Importer
                    </button>
                    <a href="#" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#templateModal">
                        <i class="bi bi-file-earmark-arrow-down"></i> Télécharger Template
                    </a>
                </form>

                <div id="importResults" class="mt-3" style="display: none;">
                    <div class="alert" role="alert"></div>
                    <div class="progress" style="display: none;">
                        <div class="progress-bar" role="progressbar"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Export Section -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-download"></i> Export</h5>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <a href="#" class="list-group-item list-group-item-action" onclick="exportData('margins')">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">Rapport des Marges</h6>
                            <small><i class="bi bi-file-earmark-spreadsheet"></i></small>
                        </div>
                        <p class="mb-1">Export complet avec calcul des marges et profit potentiel</p>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" onclick="exportData('stocks')">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">Analyse des Stocks</h6>
                            <small><i class="bi bi-file-earmark-spreadsheet"></i></small>
                        </div>
                        <p class="mb-1">État des stocks avec valorisation</p>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" onclick="exportData('purchases')">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">Commandes avec Marges</h6>
                            <small><i class="bi bi-file-earmark-spreadsheet"></i></small>
                        </div>
                        <p class="mb-1">Détail des commandes et marges associées</p>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Historique des fichiers -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Historique des Exports</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Fichier</th>
                        <th>Date</th>
                        <th>Taille</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="exportHistory">
                    <tr>
                        <td colspan="4" class="text-center">Chargement...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Templates -->
<div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Templates CSV</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="list-group">
                    <a href="{{ route('import-export.template', 'purchases') }}" class="list-group-item list-group-item-action">
                        <i class="bi bi-file-earmark-csv"></i> Template Commandes
                    </a>
                    <a href="{{ route('import-export.template', 'products') }}" class="list-group-item list-group-item-action">
                        <i class="bi bi-file-earmark-csv"></i> Template Produits
                    </a>
                    <a href="{{ route('import-export.template', 'conditions') }}" class="list-group-item list-group-item-action">
                        <i class="bi bi-file-earmark-csv"></i> Template Conditions d'Achat
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Import Form
document.getElementById('importForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    const formData = new FormData(e.target);
    const type = formData.get('type');
    const resultDiv = document.getElementById('importResults');
    const alertDiv = resultDiv.querySelector('.alert');

    resultDiv.style.display = 'block';
    alertDiv.className = 'alert alert-info';
    alertDiv.textContent = 'Import en cours...';

    try {
        const response = await fetch(`/api/import/${type}`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });

        const result = await response.json();

        if (result.success) {
            alertDiv.className = 'alert alert-success';
            alertDiv.innerHTML = `<i class="bi bi-check-circle"></i> ${result.message}`;
        } else {
            alertDiv.className = 'alert alert-danger';
            alertDiv.innerHTML = `<i class="bi bi-x-circle"></i> ${result.message}`;
            if (result.errors) {
                alertDiv.innerHTML += '<ul class="mb-0 mt-2">';
                result.errors.forEach(error => {
                    alertDiv.innerHTML += `<li>${error}</li>`;
                });
                alertDiv.innerHTML += '</ul>';
            }
        }
    } catch (error) {
        alertDiv.className = 'alert alert-danger';
        alertDiv.innerHTML = `<i class="bi bi-x-circle"></i> Erreur: ${error.message}`;
    }
});

// Export functions
async function exportData(type) {
    try {
        const response = await fetch(`/api/export/${type}`);
        const result = await response.json();

        if (result.success && result.download_url) {
            window.open(result.download_url, '_blank');
            loadExportHistory();
        }
    } catch (error) {
        alert('Erreur lors de l\'export: ' + error.message);
    }
}

// Load export history
async function loadExportHistory() {
    try {
        const response = await fetch('/api/exports');
        const data = await response.json();

        const tbody = document.getElementById('exportHistory');
        tbody.innerHTML = '';

        if (data.files && data.files.length > 0) {
            data.files.forEach(file => {
                tbody.innerHTML += `
                    <tr>
                        <td>${file.name}</td>
                        <td>${new Date(file.created_at).toLocaleDateString('fr-FR')}</td>
                        <td>${(file.size / 1024).toFixed(2)} KB</td>
                        <td>
                            <a href="${file.url}" class="btn btn-sm btn-outline-primary" target="_blank">
                                <i class="bi bi-download"></i>
                            </a>
                        </td>
                    </tr>
                `;
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center">Aucun export trouvé</td></tr>';
        }
    } catch (error) {
        console.error('Erreur chargement historique:', error);
    }
}

// Load history on page load
loadExportHistory();
</script>
@endpush
