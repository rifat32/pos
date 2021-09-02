<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class VariationLocationDetails extends Model
{
    public function product()
    {
        return $this->belongsTo(\App\Product::class, 'product_id');
    }

}
