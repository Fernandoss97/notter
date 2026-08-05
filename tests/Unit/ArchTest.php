<?php

arch('no debugging leftovers')
    ->expect('App')
    ->not->toUse(['dd', 'dump', 'die', 'var_dump', 'ray']);

arch('controllers')
    ->expect('App\Http\Controllers')
    ->toBeClasses()
    ->toHaveSuffix('Controller');

arch('form requests')
    ->expect('App\Http\Requests')
    ->toBeClasses()
    ->toHaveSuffix('Request')
    ->toExtend('Illuminate\Foundation\Http\FormRequest');

arch('models')
    ->expect('App\Models')
    ->classes()
    ->toExtend('Illuminate\Database\Eloquent\Model');

arch('model enums')
    ->expect('App\Models\Enums')
    ->toBeEnums();

arch()->preset()->security()->ignoring('md5');
