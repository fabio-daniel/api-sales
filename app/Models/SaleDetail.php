<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleDetail extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'sale_id',
        'product_id',
        'quantity',
        'price',
        'subtotal',
        'discount_percent',
        'discount_amount',
        'subtotal_with_discount'
    ];

    protected $casts = [
        'price' => 'float',
        'subtotal' => 'float',
        'discount_percent' => 'float',
        'discount_amount' => 'float',
        'subtotal_with_discount' => 'float',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
