<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $primaryKey = 'payment_method_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'payment_method_id',
        'name',
        'code',
        'type',
        'logo',
        'is_active',
        'transaction_fee_percentage',
        'transaction_fee_fixed',
        'minimum_amount',
        'maximum_amount',
        'api_config',
        'supported_currencies',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'transaction_fee_percentage' => 'decimal:2',
        'transaction_fee_fixed' => 'decimal:2',
        'minimum_amount' => 'decimal:2',
        'maximum_amount' => 'decimal:2',
        'api_config' => 'array',
        'supported_currencies' => 'array',
    ];

    public function transactions()
    {
        return $this->hasMany(PaymentTransaction::class, 'payment_method_id', 'payment_method_id');
    }
}