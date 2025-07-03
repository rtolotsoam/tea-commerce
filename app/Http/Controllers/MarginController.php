<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MarginAnalysis;
use App\Http\Controllers\Controller;

class MarginController extends Controller
{
    public function index()
    {
        $margins = MarginAnalysis::with('product')->paginate(20);
        return view('margins.index', compact('margins'));
    }

    public function calculate(Request $request)
    {
        // Logique de calcul ou appel d'un service
        // Par exemple recalculer les marges

        // Pour l'exemple, on simule une réussite
        return redirect()->route('margins.index')->with('success', 'Marges recalculées.');
    }
}
