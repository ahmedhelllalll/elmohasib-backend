<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::where('business_id', auth()->user()->business_id)
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json($suppliers);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
        ]);

        $supplier = Supplier::create([
            ...$request->all(),
            'business_id' => auth()->user()->business_id
        ]);

        return response()->json($supplier, 201);
    }

    public function update(Request $request, Supplier $supplier)
    {
        if ($supplier->business_id !== auth()->user()->business_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
        ]);

        $supplier->update($request->all());

        return response()->json($supplier);
    }

    public function destroy(Supplier $supplier)
    {
        if ($supplier->business_id !== auth()->user()->business_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $supplier->delete();
        return response()->json(null, 204);
    }
}
