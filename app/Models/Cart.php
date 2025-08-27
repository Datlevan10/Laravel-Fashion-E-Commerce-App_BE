<?php

namespace App\Models;

use App\Models\CartDetail;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Cart extends Model
{
    use HasFactory;

    protected $table = 'carts';

    protected $primaryKey = 'cart_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'customer_id',
        'total_price',
        'cart_status',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($cart) {
            $cart->cart_id = Str::random(8);
        });
    }

    public function cartDetails()
    {
        return $this->hasMany(CartDetail::class, 'cart_id', 'cart_id');
    }

    public function customer()
    {
        return $this->belongsTo(\App\Models\Customer::class, 'customer_id', 'customer_id');
    }
}
