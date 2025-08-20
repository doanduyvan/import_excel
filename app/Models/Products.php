<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Products extends Model
{
    use HasFactory;
    protected $table = 'products';
    protected $fillable = [
        'name',
    ];

    public function variants()
    {
        return $this->hasMany(Variants::class, 'product_id', 'id');
    }
}
