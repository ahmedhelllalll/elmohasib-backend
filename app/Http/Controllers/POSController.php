<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class POSController extends Controller
{
    public function checkout(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'required|string',
            'customer_id' => 'nullable|exists:customers,id'
        ]);

        try {
            DB::beginTransaction();

            $businessId = auth()->user()->business_id;
            $userId = auth()->id();
            
            $subtotal = 0;

            // First pass: Calculate subtotal and verify stock
            foreach ($request->items as $item) {
                $product = Product::where('id', $item['product_id'])->where('business_id', $businessId)->lockForUpdate()->firstOrFail();
                
                if ($product->initial_quantity < $item['quantity']) {
                    throw new \Exception("الكمية المطلوبة من {$product->name} غير متوفرة في المخزون.");
                }

                $subtotal += ($item['quantity'] * $item['unit_price']);
            }

            $discount = $request->discount_amount ?: 0;
            
            // Fetch dynamic tax rate from settings
            $taxSetting = \App\Models\Setting::where('business_id', $businessId)->where('key', 'tax_rate')->first();
            $taxRatePercentage = $taxSetting ? floatval($taxSetting->value) : 15; // default 15%
            $taxRate = $taxRatePercentage / 100;
            
            $taxAmount = ($subtotal - $discount) * $taxRate;
            $total = ($subtotal - $discount) + $taxAmount;

            // Generate Invoice Number (e.g., INV-YYYYMMDD-XXXX)
            $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(Str::random(4));

            $invoice = Invoice::create([
                'business_id' => $businessId,
                'user_id' => $userId,
                'customer_id' => $request->customer_id,
                'invoice_number' => $invoiceNumber,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discount,
                'total' => $total,
                'payment_method' => $request->payment_method,
                'status' => 'completed'
            ]);

            // Second pass: Create items, deduct stock, log movement
            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);
                
                $itemSubtotal = $item['quantity'] * $item['unit_price'];

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $itemSubtotal
                ]);

                $oldQuantity = $product->initial_quantity;
                $product->initial_quantity -= $item['quantity'];
                $product->save();

                StockMovement::create([
                    'business_id' => $businessId,
                    'product_id' => $product->id,
                    'user_id' => $userId,
                    'type' => 'out_sale',
                    'quantity_change' => -$item['quantity'],
                    'balance_after' => $product->initial_quantity,
                    'reference' => 'فاتورة مبيعات: ' . $invoiceNumber
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'تم الدفع بنجاح',
                'invoice' => $invoice->load('items')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function getInvoices()
    {
        $invoices = Invoice::where('business_id', auth()->user()->business_id)
            ->with(['items', 'customer'])
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json($invoices);
    }
}
