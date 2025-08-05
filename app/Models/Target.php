<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Products;
use App\Models\User;

class Target extends Model
{
    use HasFactory;
    protected $table = 'target';
    protected $fillable = [
        'quantity_target',
        'product_id',
        'user_id'
    ];

    public function products()
    {
        return $this->belongsTo(Products::class, 'product_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
