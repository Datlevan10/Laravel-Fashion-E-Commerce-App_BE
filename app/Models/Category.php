<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use HasFactory;

    protected $table = 'categories';

    protected $primaryKey = 'categoryId';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'categoryName',
        'imageCategory',
        'description',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($category) {
            $category->categoryId = Str::random(8);
        });
    }
}
