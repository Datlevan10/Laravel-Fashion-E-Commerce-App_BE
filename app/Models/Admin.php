<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Admin extends Model
{
    use HasFactory;

    protected $table = 'admins';

    protected $primaryKey = 'admin_id';
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
        'role',
        'permissions',
        'is_active',
        'last_login',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($admin) {
            $admin->admin_id = Str::random(8);
        });
    }
    public function updateLastLogin()
    {
        $this->lasLogin = now();
        $this->save();
    }
}
