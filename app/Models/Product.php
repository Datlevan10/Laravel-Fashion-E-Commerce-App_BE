<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    protected $table = 'products';

    protected $primaryKey = 'product_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'color' => 'array',
        'size' => 'array',
        'image' => 'array',
        'variant' => 'array',
    ];

    protected $fillable = [
        'category_id',
        'product_name',
        'description',
        'variant',
        'color',
        'size',
        'image',
        'old_price',
        'new_price',
        'note',
        'quantity_in_stock',
        'rating_count',
        'rating_average',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            $product->product_id = Str::random(8);
        });

        static::created(function ($product) {
            $storeName = DB::table('stores')->value('store_name') ?? 'Default Store';
            $categoryName = $product->category->category_name ?? null;

            DB::table('notifications')->insert([
                'notification_id' => Str::random(8),
                'type' => 'products',
                'related_id' => $product->product_id,
                'product_id' => $product->product_id,
                'message' => "{$storeName} vừa thêm sản phẩm mới vào danh mục. {$categoryName}. Bạn có thể xem sản phẩm '{$product->product_name}'. Mua sắm ngay!.",
                'is_read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function getRouteKeyName()
    {
        return 'product_id';
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'category_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id', 'store_id');
    }

    public function orderDetails()
    {
        return $this->hasMany(\App\Models\OrderDetail::class, 'product_id', 'product_id');
    }

}
