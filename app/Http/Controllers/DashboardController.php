<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function stats()
    {
        $businessId = auth()->user()->business_id;

        $currency = \App\Models\Setting::where('business_id', $businessId)
            ->where('key', 'currency')
            ->value('value') ?? 'ر.س';

        // Total Revenue (all completed invoices)
        $totalRevenue = Invoice::where('business_id', $businessId)
            ->where('status', 'completed')
            ->sum('total');

        // Total Products
        $totalProducts = Product::where('business_id', $businessId)->count();

        // Low Stock Alerts
        $lowStockCount = Product::where('business_id', $businessId)
            ->whereRaw('initial_quantity <= alert_quantity')
            ->count();

        // Recent Activity (Sales + Stock Movements combined)
        $recentSales = Invoice::where('business_id', $businessId)
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->take(3)
            ->get()
            ->map(function ($invoice) use ($currency) {
                return [
                    'id' => 'sale_' . $invoice->id,
                    'action' => 'عملية بيع جديدة',
                    'amount' => $invoice->total . ' ' . $currency,
                    'time' => $invoice->created_at->diffForHumans(),
                    'type' => 'sale',
                    'created_at' => $invoice->created_at
                ];
            });

        $recentStock = StockMovement::where('business_id', $businessId)
            ->with('product')
            ->orderBy('created_at', 'desc')
            ->take(3)
            ->get()
            ->map(function ($movement) {
                $type = $movement->type === 'in_add' ? 'إضافة مخزون' : ($movement->type === 'out_sale' ? 'سحب للمبيعات' : 'تعديل مخزون');
                return [
                    'id' => 'stock_' . $movement->id,
                    'action' => $type . ' (' . ($movement->product->name ?? 'منتج محذوف') . ')',
                    'amount' => ($movement->quantity_change > 0 ? '+' : '') . $movement->quantity_change . ' وحدة',
                    'time' => $movement->created_at->diffForHumans(),
                    'type' => 'inventory',
                    'created_at' => $movement->created_at
                ];
            });

        // Merge, sort by created_at desc, and take top 5
        $recentActivity = $recentSales->concat($recentStock)
            ->sortByDesc('created_at')
            ->take(5)
            ->values()
            ->all();

        return response()->json([
            'total_revenue' => $totalRevenue,
            'total_products' => $totalProducts,
            'low_stock_count' => $lowStockCount,
            'recent_activity' => $recentActivity,
            'revenue_trend' => '+12%' // Dummy trend for now, can be calculated dynamically later
        ]);
    }
}
