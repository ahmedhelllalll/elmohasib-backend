<?php

namespace App\Http\Controllers;

use App\Models\StockMovement;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    /**
     * Display a listing of the resource for a specific product.
     */
    public function index(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $movements = StockMovement::with('user')
            ->where('business_id', auth()->user()->business_id)
            ->where('product_id', $request->product_id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($movements);
    }
}
