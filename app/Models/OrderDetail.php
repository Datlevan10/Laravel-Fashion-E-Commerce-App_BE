<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use App\Http\Resources\OrderDetailResource;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderDetail extends Model
{
    use HasFactory;

    protected $table = 'order_details';

    protected $primaryKey = 'order_detail_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'quantity',
        'color',
        'size',
        'image',
        'unit_price',
        'total_price',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order_detail) {
            $order_detail->order_detail_id = Str::random(8);
        });
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }

    public function product()
    {
        return $this->belongsTo(\App\Models\Product::class, 'product_id', 'product_id');
    }

    public function show($id)
    {
        $orderDetail = OrderDetail::with('order.customer', 'order.staff')->findOrFail($id);
        return new OrderDetailResource($orderDetail);
    }
}
