<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CartDetail extends Model
{
    use HasFactory;

    protected $table = 'cart_details';

    protected $primaryKey = 'cart_detail_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'cart_id',
        'customer_id',
        'product_id',
        'product_name',
        'quantity',
        'color',
        'size',
        'image',
        'unit_price',
        'total_price',
        'is_checked_out',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($cart_detail) {
            $cart_detail->cart_detail_id = Str::random(8);
        });
    }

    public function product()
    {
        return $this->belongsTo(\App\Models\Product::class, 'product_id', 'product_id');
    }

    public function cart()
    {
        return $this->belongsTo(\App\Models\Cart::class, 'cart_id', 'cart_id');
    }
}
