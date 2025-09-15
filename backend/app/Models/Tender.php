<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Customers;
use App\Models\Products;

class Tender extends Model
{
    use HasFactory;
    protected $table = "tender";
    protected $fillable = [
        'customer_quota_description',
        'cust_quota_start_date',
        'cust_quota_end_date',
        'cust_quota_quantity',
        'invoice_quantity',
        'return_quantity',
        'allocated_quantity',
        'used_quota',
        'remaining_quota',
        'report_run_date',
        'tender_price',
        'customer_id',
    ];

    protected $casts = [
        'cust_quota_start_date' => 'date',
        'cust_quota_end_date' => 'date',
        'cust_quota_quantity' => 'float',
        'invoice_quantity' => 'float',
        'return_quantity' => 'float',
        'allocated_quantity' => 'float',
        'used_quota' => 'float',
        'remaining_quota' => 'float',
        'report_run_date' => 'date',
        'tender_price' => 'float'
    ];

    public function customers()
    {
        return $this->belongsTo(Customers::class, 'customer_id', 'id');
    }

    public function variants()
    {
        return $this->belongsToMany(Variants::class, 'variants_tender', 'tender_id', 'variant_id', 'id', 'id');
    }
}
