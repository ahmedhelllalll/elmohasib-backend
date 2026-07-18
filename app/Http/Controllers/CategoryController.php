<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $categories = Category::where('business_id', auth()->user()->business_id)
            ->latest()
            ->get();
        return response()->json($categories);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $category = Category::create([
            'business_id' => auth()->user()->business_id,
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return response()->json($category, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $category = Category::where('business_id', auth()->user()->business_id)
            ->findOrFail($id);
        return response()->json($category);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $category = Category::where('business_id', auth()->user()->business_id)
            ->findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $category->update($request->only('name', 'description'));

        return response()->json($category);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $category = Category::where('business_id', auth()->user()->business_id)
            ->findOrFail($id);

        // Optional: Check if products exist in category before deletion, 
        // or let the DB foreign key cascade/set null depending on schema.
        
        $category->delete();

        return response()->json(['message' => 'Category deleted']);
    }
}
