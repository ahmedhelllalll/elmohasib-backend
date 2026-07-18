<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Business;
use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockMovement;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DummyDataSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create('ar_SA');

        // Create or get a Business
        $business = Business::first();
        if (!$business) {
            $business = Business::create([
                'name' => 'مؤسسة المحاسب التجارية',
                'currency' => 'SAR',
                'tax_rate' => 15.00
            ]);
        }
        $businessId = $business->id;

        // Ensure we have an admin user so we don't lock the user out if they migrate:fresh
        $admin = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'المدير العام',
                'password' => Hash::make('password'),
                'business_id' => $businessId,
                'role' => 'admin'
            ]
        );

        // 15 Users
        for ($i = 0; $i < 15; $i++) {
            User::create([
                'name' => $faker->name,
                'email' => Str::random(10) . '@example.com',
                'password' => Hash::make('password'),
                'business_id' => $businessId
            ]);
        }

        // 15 Categories
        $categories = [];
        for ($i = 0; $i < 15; $i++) {
            $categories[] = Category::create([
                'business_id' => $businessId,
                'name' => 'قسم ' . $faker->word . ' ' . $i
            ]);
        }

        // 15 Products
        $products = [];
        foreach ($categories as $category) {
            $products[] = Product::create([
                'business_id' => $businessId,
                'category_id' => $category->id,
                'name' => 'منتج ' . $faker->word . ' ' . Str::random(4),
                'barcode' => $faker->ean13 . Str::random(4),
                'cost_price' => $faker->randomFloat(2, 10, 100),
                'retail_price' => $faker->randomFloat(2, 150, 500),
                'initial_quantity' => $faker->numberBetween(10, 200),
                'alert_quantity' => 10
            ]);
        }

        // 15 Customers
        $customers = [];
        for ($i = 0; $i < 15; $i++) {
            $customers[] = Customer::create([
                'business_id' => $businessId,
                'name' => $faker->name,
                'phone' => $faker->phoneNumber,
                'email' => $faker->safeEmail,
                'address' => $faker->address
            ]);
        }

        // 15 Suppliers
        $suppliers = [];
        for ($i = 0; $i < 15; $i++) {
            $suppliers[] = Supplier::create([
                'business_id' => $businessId,
                'name' => $faker->name,
                'company_name' => $faker->company,
                'phone' => $faker->phoneNumber,
                'email' => $faker->safeEmail,
                'address' => $faker->address,
                'credit_balance' => 0
            ]);
        }

        // 15 Invoices (Sales)
        for ($i = 0; $i < 15; $i++) {
            $customer = $faker->randomElement($customers);
            
            $invoice = Invoice::create([
                'business_id' => $businessId,
                'customer_id' => $customer->id,
                'user_id' => $admin->id,
                'invoice_number' => 'INV-' . Str::random(8),
                'subtotal' => 0,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total' => 0,
                'status' => 'completed'
            ]);

            $invTotal = 0;
            // 2 items per invoice
            for ($j = 0; $j < 2; $j++) {
                $product = $faker->randomElement($products);
                $qty = $faker->numberBetween(1, 5);
                $price = $product->retail_price;
                $lineTotal = $qty * $price;
                
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'subtotal' => $lineTotal
                ]);

                StockMovement::create([
                    'business_id' => $businessId,
                    'product_id' => $product->id,
                    'user_id' => $admin->id,
                    'type' => 'out_sale',
                    'quantity_change' => -$qty,
                    'balance_after' => 0, // Mocked for seeder
                    'reference' => 'Sale INV ' . $invoice->id
                ]);

                $invTotal += $lineTotal;
            }

            $invoice->update([
                'subtotal' => $invTotal,
                'total' => $invTotal
            ]);
        }

        // 15 Purchases
        for ($i = 0; $i < 15; $i++) {
            $supplier = $faker->randomElement($suppliers);
            
            $purchase = Purchase::create([
                'business_id' => $businessId,
                'supplier_id' => $supplier->id,
                'user_id' => $admin->id,
                'purchase_number' => 'PUR-' . Str::random(8),
                'reference_number' => 'REF-' . Str::random(8),
                'purchase_date' => $faker->date(),
                'subtotal' => 0,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total' => 0,
                'status' => 'completed'
            ]);

            $purTotal = 0;
            for ($j = 0; $j < 2; $j++) {
                $product = $faker->randomElement($products);
                $qty = $faker->numberBetween(5, 20);
                $cost = $product->cost_price;
                $lineTotal = $qty * $cost;
                
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $qty,
                    'unit_cost' => $cost,
                    'subtotal' => $lineTotal
                ]);

                StockMovement::create([
                    'business_id' => $businessId,
                    'product_id' => $product->id,
                    'user_id' => $admin->id,
                    'type' => 'in_purchase',
                    'quantity_change' => $qty,
                    'balance_after' => $qty, // Mocked for seeder
                    'reference' => 'Purchase PUR ' . $purchase->id
                ]);

                $purTotal += $lineTotal;
            }

            $purchase->update([
                'subtotal' => $purTotal,
                'total' => $purTotal
            ]);
        }

        $this->command->info('Seeded 15 rows of dummy data for all tables successfully!');
    }
}
