<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer_account extends Model
{
    use HasFactory;
    protected $table = 'customer_account';
    protected $fillable = [
        'brick_codewo',
        'customer_account_name',
        'user_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function customers()
    {
        return $this->hasMany(Customers::class, 'customer_account_id', 'id');
    }
}
