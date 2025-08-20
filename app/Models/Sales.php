<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Customers;
use App\Models\Products;

class Sales extends Model
{
    use HasFactory;
    protected $table = 'sales';
    protected $fillable = [
        'order_number',
        'invoice_number',
        'contract_number',
        'expiry_date',
        'selling_price',
        'commercial_quantity',
        'invoice_confirmed_date',
        'net_sales_value',
        'accounts_receivable_date',
        'customer_id'
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'commercial_quantity' => 'float',
        'invoice_confirmed_date' => 'date',
        'accounts_receivable_date' => 'date'
    ];

    public function customers()
    {
        return $this->belongsTo(Customers::class, 'customer_id', 'id');
    }

    public function variants()
    {
        return $this->belongsToMany(Variants::class, 'variants_sales', 'sale_id', 'variant_id', 'id', 'id');
    }
}
