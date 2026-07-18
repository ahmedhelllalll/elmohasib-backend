<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseController extends Controller
{
    public function index()
    {
        $purchases = Purchase::where('business_id', auth()->user()->business_id)
            ->with(['items', 'supplier', 'user'])
            ->orderBy('purchase_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json($purchases);
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'reference_number' => 'nullable|string|max:255',
            'purchase_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'paid_amount' => 'required|numeric|min:0'
        ]);

        try {
            DB::beginTransaction();

            $businessId = auth()->user()->business_id;
            $userId = auth()->id();
            
            $subtotal = 0;

            foreach ($request->items as $item) {
                $subtotal += ($item['quantity'] * $item['unit_cost']);
            }

            $discount = $request->discount_amount ?: 0;
            $taxAmount = $request->tax_amount ?: 0;
            $total = ($subtotal - $discount) + $taxAmount;

            $paidAmount = $request->paid_amount;
            // Prevent paying more than total logically, or just cap it
            $paidAmount = min($paidAmount, $total);
            $remainingBalance = max(0, $total - $paidAmount);

            if ($remainingBalance == 0) {
                $paymentStatus = 'paid';
            } elseif ($paidAmount > 0) {
                $paymentStatus = 'partial';
            } else {
                $paymentStatus = 'unpaid';
            }

            $purchaseNumber = 'PO-' . date('Ymd') . '-' . strtoupper(Str::random(4));

            $purchase = Purchase::create([
                'business_id' => $businessId,
                'user_id' => $userId,
                'supplier_id' => $request->supplier_id,
                'purchase_number' => $purchaseNumber,
                'reference_number' => $request->reference_number,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discount,
                'total' => $total,
                'paid_amount' => $paidAmount,
                'remaining_balance' => $remainingBalance,
                'payment_status' => $paymentStatus,
                'status' => 'completed',
                'purchase_date' => $request->purchase_date
            ]);

            foreach ($request->items as $item) {
                $product = Product::where('id', $item['product_id'])
                                  ->where('business_id', $businessId)
                                  ->lockForUpdate()
                                  ->firstOrFail();
                
                $itemSubtotal = $item['quantity'] * $item['unit_cost'];

                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'subtotal' => $itemSubtotal
                ]);

                // Increase Stock
                $product->initial_quantity += $item['quantity'];
                // Update dynamic cost price as per spec
                $product->cost_price = $item['unit_cost'];
                $product->save();

                // Log Movement
                StockMovement::create([
                    'business_id' => $businessId,
                    'product_id' => $product->id,
                    'user_id' => $userId,
                    'type' => 'in_purchase',
                    'quantity_change' => $item['quantity'],
                    'balance_after' => $product->initial_quantity,
                    'reference' => 'فاتورة مشتريات: ' . $purchaseNumber
                ]);
            }

            // Update supplier financials
            $supplier = $purchase->supplier;
            $supplier->total_purchases += $total;
            if ($remainingBalance > 0) {
                $supplier->credit_balance += $remainingBalance;
            }
            $supplier->save();

            DB::commit();

            return response()->json([
                'message' => 'تم تسجيل المشتريات بنجاح',
                'purchase' => $purchase->load('items')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
