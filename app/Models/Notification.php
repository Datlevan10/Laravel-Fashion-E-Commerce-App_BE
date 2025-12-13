<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Notification extends Model
{
    use HasFactory;

    protected $table = 'notifications';

    protected $primaryKey = 'notification_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'customer_id',
        'type',
        'related_id',
        'product_id',
        'message',
        'is_read',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($notification) {
            $notification->notification_id = Str::random(8);
        });

        static::created(function ($notification) {
            $customers = DB::table('customers')->pluck('customer_id');

            $customerNotifications = [];
            foreach ($customers as $customerId) {
                $customerNotifications[] = [
                    'customer_notification_id' => Str::random(8),
                    'notification_id' => $notification->notification_id,
                    'customer_id' => $customerId,
                    'is_hidden' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::table('customer_notifications')->insert($customerNotifications);
        });
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'related_id', 'product_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'related_id', 'order_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class, 'related_id', 'event_id');
    }

    public function customerNotifications()
    {
        return $this->hasMany(CustomerNotification::class, 'notification_id', 'notification_id');
    }

}
