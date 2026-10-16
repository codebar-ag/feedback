<?php

declare(strict_types=1);

arch('strict types everywhere')
    ->expect(['App', 'Database\Factories', 'Tests'])
    ->toUseStrictTypes();

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'ddd', 'ray', 'var_dump', 'print_r', 'die', 'exit'])
    ->not->toBeUsed();

arch('env() only in config')
    ->expect('env')
    ->not->toBeUsedIn('App');

arch('controllers do not access the database directly')
    ->expect('App\Http\Controllers')
    ->not->toUse(['Illuminate\Support\Facades\DB', 'Illuminate\Database\DatabaseManager', 'Illuminate\Database\Connection']);

arch('support classes are final and readonly')
    ->expect('App\Support')
    ->toBeFinal()
    ->toBeReadonly();

arch('geo locators are final and readonly')
    ->expect('App\Geo')
    ->classes()
    ->toBeFinal()
    ->toBeReadonly()
    ->ignoring('App\Geo\GeoLocator');

arch('no auth dependencies (starter kit removed)')
    ->expect('App')
    ->not->toUse(['Illuminate\Auth', 'Illuminate\Contracts\Auth', 'Illuminate\Session', 'Illuminate\View']);

arch('models extend Eloquent and are not final')
    ->expect('App\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model')
    ->not->toBeFinal();

arch('commands live in the console namespace')
    ->expect('App\Console\Commands')
    ->classes()
    ->toExtend('Illuminate\Console\Command');

arch('enums are backed')
    ->expect('App\Enums')
    ->toBeStringBackedEnums();

arch('php preset')->preset()->php()->ignoring(['debug_backtrace']);

arch('security preset')->preset()->security()->ignoring(['md5', 'sha1', 'random_int']);
