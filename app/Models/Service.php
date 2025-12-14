<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Service extends Model
{
    /** @use HasFactory<\Database\Factories\ServiceFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'duration_minutes',
        'price_amount',
        'price_currency',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'duration_minutes' => 'integer',
        'price_amount' => 'integer',
    ];

    protected function price(): Attribute
    {
        return Attribute::make(
            get: fn () => number_format($this->price_amount / 100, 2, '.', ''),
        );
    }
}
