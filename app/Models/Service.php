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
        'description',
        'duration_minutes',
        'price_cents',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'duration_minutes' => 'integer',
        'price_cents' => 'integer',
    ];

    protected function price(): Attribute
    {
        return Attribute::make(
            get: fn () => number_format($this->price_cents / 100, 2, '.', ''),
        );
    }

    protected function formattedPrice(): Attribute
    {
        return Attribute::make(
            get: fn () => number_format($this->price_cents / 100, 0, ',', '.'),
        );
    }
}
