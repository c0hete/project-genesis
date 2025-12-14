<?php

declare(strict_types=1);

arch('globals')
    ->expect(['dd', 'dump', 'ray'])
    ->not->toBeUsed();

arch('controllers')
    ->expect('App\Http\Controllers')
    ->not->toUse('Illuminate\Database\Eloquent\Model')
    ->ignoring('App\Http\Controllers\Controller');

arch('services')
    ->expect('App\Services')
    ->toUseStrictTypes();

arch('dtos')
    ->expect('App\DTOs')
    ->toUseStrictTypes();

arch('enums')
    ->expect('App\Enums')
    ->toUseStrictTypes();
