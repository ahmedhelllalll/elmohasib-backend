<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Product extends Model
{
    use HasUuids;

    protected $fillable = [
        'business_id',
        'category_id',
        'name',
        'barcode',
        'cost_price',
        'retail_price',
        'initial_quantity',
        'expiration_date',
        'alert_quantity',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
