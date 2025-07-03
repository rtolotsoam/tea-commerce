<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class StockController extends Controller
{
    public function index()
    {
        $stocks = Stock::with('product')->paginate(20);
        return view('stocks.index', compact('stocks'));
    }

    public function adjust(Request $request, Stock $stock)
    {
        $data = $request->validate([
            'adjustment' => 'required|numeric',
        ]);

        $stock->quantity_available += $data['adjustment'];
        $stock->save();

        return redirect()->route('stocks.index')->with('success', 'Stock ajusté avec succès.');
    }
}
