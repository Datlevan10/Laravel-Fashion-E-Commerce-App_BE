<?php

namespace App\Models;

use App\Models\Staff;
use App\Models\Customer;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;

    protected $table = 'orders';

    protected $primaryKey = 'order_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'customer_id',
        'staff_id',
        'order_date',
        'payment_method',
        'shipping_address',
        'notes',
        'discount',
        'total_price',
        'order_status',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            $order->order_id = Str::random(8);
        });

        static::updated(function ($order) {
            if ($order->wasChanged('order_status')) {
                DB::table('notifications')->insert([
                    'notification_id' => Str::random(8),
                    'type' => 'orders',
                    'related_id' => $order->order_id,
                    'customer_id' => $order->customer_id,
                    'message' => "Your order status #{$order->order_id} has been updated to {$order->order_status}.",
                    'is_read' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class, 'order_id', 'order_id');
    }

    public function paymentTransactions()
    {
        return $this->hasMany(PaymentTransaction::class, 'order_id', 'order_id');
    }

    public function latestPaymentTransaction()
    {
        return $this->hasOne(PaymentTransaction::class, 'order_id', 'order_id')->latest();
    }
}
