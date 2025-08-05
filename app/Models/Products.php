<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Tender;
use App\Models\Sales;
use App\Models\Target;

class Products extends Model
{
    use HasFactory;
    protected $table = 'products';
    protected $fillable = [
        'sap_item_code',
        'item_short_description'
    ];

    public function target()
    {
        return $this->hasMany(Target::class, 'product_id', 'id');
    }

    public function tender()
    {
        return $this->belongsToMany(Tender::class, 'products_tender', 'product_id', 'tender_id', 'id', 'id');
    }

    public function sales()
    {
        return $this->belongsToMany(Sales::class, 'product_sales', 'product_id', 'sale_id', 'id', 'id');
    }
}
