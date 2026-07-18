<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'user_id',
        'supplier_id',
        'purchase_number',
        'reference_number',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total',
        'paid_amount',
        'remaining_balance',
        'payment_status',
        'status',
        'purchase_date'
    ];

    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
