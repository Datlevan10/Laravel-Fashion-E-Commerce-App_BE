<?php

namespace App\Models;

use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Staff extends Authenticatable
{
    use HasFactory, HasApiTokens, Notifiable;

    protected $table = 'staffs';

    protected $primaryKey = 'staff_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_name',
        'full_name',
        'gender',
        'date_of_birth',
        'image',
        'email',
        'phone_number',
        'password',
        'address',
        'hire_date',
        'salary',
        'department',
        'role',
        'is_active',
        'last_login',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($staff) {
            $staff->staff_id = Str::random(8);
        });
    }
    public function updateLastLogin()
    {
        $this->lasLogin = now();
        $this->save();
    }
}
