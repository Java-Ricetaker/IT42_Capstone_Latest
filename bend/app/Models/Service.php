<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = [
        'name',
        'description',
        'price',
        'category',
        'is_excluded_from_analytics'
    ];


    public function discounts()
    {
        return $this->hasMany(ServiceDiscount::class);
    }

    public function getPriceForDate($date)
    {
        $discount = $this->discounts()
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->where('status', 'launched')
            ->whereDate('activated_at', '<=', now()->subDay()->toDateString()) // must be activated for at least 1 day
            ->first();

        return $discount ? $discount->discounted_price : $this->price;
    }
}
