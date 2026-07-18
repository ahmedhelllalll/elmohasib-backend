<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'phone',
        'company_name',
        'email',
        'address',
        'total_purchases',
        'credit_balance'
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
