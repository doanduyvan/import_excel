<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Tender;
use App\Models\Sales;
use App\Models\Target;

class Variants extends Model
{
    use HasFactory;
    protected $table = 'variants';
    protected $fillable = [
        'sap_item_code',
        'item_short_description'
    ];

    public function target()
    {
        return $this->hasMany(Target::class, 'variants_id', 'id');
    }

    public function tender()
    {
        return $this->belongsToMany(Tender::class, 'variants_tender', 'variant_id', 'tender_id', 'id', 'id');
    }

    public function sales()
    {
        return $this->belongsToMany(Sales::class, 'variants_sales', 'variant_id', 'sale_id', 'id', 'id');
    }
}
