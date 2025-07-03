<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ScrapingService;
use App\Http\Controllers\Controller;

class ScrapingController extends Controller
{
    protected $scrapingService;

    public function __construct(ScrapingService $scrapingService)
    {
        $this->scrapingService = $scrapingService;
    }

    public function index()
    {
        return view('scraping.index');
    }

    public function run(Request $request)
    {
        $results = $this->scrapingService->scrapeAllSuppliers();

        return redirect()->route('scraping.index')->with('success', 'Scraping exécuté. ' . count($results['details']) . ' fournisseurs traités.');
    }
}
