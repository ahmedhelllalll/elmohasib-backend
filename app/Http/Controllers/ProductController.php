<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Fetch products with their category
        $products = Product::with('category')
            ->where('business_id', auth()->user()->business_id)
            ->latest()
            ->get();
            
        return response()->json($products);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'name' => 'required|string|max:255',
            'barcode' => 'nullable|string|max:255',
            'cost_price' => 'required|numeric|min:0',
            'retail_price' => 'required|numeric|min:0',
            'initial_quantity' => 'required|integer|min:0',
            'expiration_date' => 'nullable|date',
            'alert_quantity' => 'required|integer|min:0',
        ]);

        $product = Product::create([
            'business_id' => auth()->user()->business_id,
            'category_id' => $request->category_id,
            'name' => $request->name,
            'barcode' => $request->barcode,
            'cost_price' => $request->cost_price,
            'retail_price' => $request->retail_price,
            'initial_quantity' => $request->initial_quantity,
            'expiration_date' => $request->expiration_date,
            'alert_quantity' => $request->alert_quantity,
        ]);

        if ($product->initial_quantity > 0) {
            \App\Models\StockMovement::create([
                'business_id' => $product->business_id,
                'product_id' => $product->id,
                'user_id' => auth()->id(),
                'type' => 'in_initial',
                'quantity_change' => $product->initial_quantity,
                'balance_after' => $product->initial_quantity,
                'reference' => 'رصيد افتتاحي'
            ]);
        }

        return response()->json($product->load('category'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $product = Product::with('category')
            ->where('business_id', auth()->user()->business_id)
            ->findOrFail($id);
            
        return response()->json($product);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $product = Product::where('business_id', auth()->user()->business_id)
            ->findOrFail($id);

        $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'name' => 'sometimes|required|string|max:255',
            'barcode' => 'nullable|string|max:255',
            'cost_price' => 'sometimes|required|numeric|min:0',
            'retail_price' => 'sometimes|required|numeric|min:0',
            'initial_quantity' => 'sometimes|required|integer|min:0',
            'expiration_date' => 'nullable|date',
            'alert_quantity' => 'sometimes|required|integer|min:0',
        ]);

        $oldQuantity = $product->initial_quantity;

        $product->update($request->only([
            'category_id', 'name', 'barcode', 'cost_price', 
            'retail_price', 'initial_quantity', 'expiration_date', 'alert_quantity'
        ]));

        if ($request->has('initial_quantity') && $request->initial_quantity != $oldQuantity) {
            $difference = $request->initial_quantity - $oldQuantity;
            $type = $difference > 0 ? 'in_adjustment' : 'out_adjustment';
            
            \App\Models\StockMovement::create([
                'business_id' => $product->business_id,
                'product_id' => $product->id,
                'user_id' => auth()->id(),
                'type' => $type,
                'quantity_change' => $difference,
                'balance_after' => $product->initial_quantity,
                'reference' => 'تعديل يدوي'
            ]);
        }

        return response()->json($product->load('category'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $product = Product::where('business_id', auth()->user()->business_id)
            ->findOrFail($id);
            
        $product->delete();

        return response()->json(['message' => 'Product deleted']);
    }
}
