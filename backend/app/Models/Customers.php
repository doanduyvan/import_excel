<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Tender;
use App\Models\Sales;
use App\Models\User;

class Customers extends Model
{
    use HasFactory;
    protected $table = 'customers';
    protected $fillable = [
        'customer_code',
        'customer_name',
        'area',
        'customer_account_id '
    ];

    public function tender()
    {
        return $this->hasMany(Tender::class, 'customer_id', 'id');
    }

    public function sales()
    {
        return $this->hasMany(Sales::class, 'customer_id', 'id');
    }

    public function customer_account()
    {
        return $this->belongsTo(Customer_account::class, 'customer_account_id', 'id');
    }
}
