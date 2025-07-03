<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class PurchaseController extends Controller
{
    public function index()
    {
        $purchases = Purchase::with('supplier')->paginate(15);
        return view('purchases.index', compact('purchases'));
    }

    public function create()
    {
        return view('purchases.create');
    }

    public function store(Request $request)
    {
        // Validation + création
        $data = $request->validate([
            // règles de validation
        ]);

        Purchase::create($data);

        return redirect()->route('purchases.index')->with('success', 'Achat créé avec succès.');
    }

    public function show(Purchase $purchase)
    {
        return view('purchases.show', compact('purchase'));
    }

    public function edit(Purchase $purchase)
    {
        return view('purchases.edit', compact('purchase'));
    }

    public function update(Request $request, Purchase $purchase)
    {
        $data = $request->validate([
            // règles
        ]);

        $purchase->update($data);

        return redirect()->route('purchases.index')->with('success', 'Achat mis à jour.');
    }

    public function destroy(Purchase $purchase)
    {
        $purchase->delete();
        return redirect()->route('purchases.index')->with('success', 'Achat supprimé.');
    }
}
