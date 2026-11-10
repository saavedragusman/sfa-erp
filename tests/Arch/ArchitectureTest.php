<?php

declare(strict_types=1);

arch('every app file declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('actions are final classes with the Action suffix')
    ->expect('App\Actions')
    ->classes()
    ->toBeFinal()
    ->toHaveSuffix('Action');

arch('DTOs are readonly')
    ->expect('App\DTOs')
    ->classes()
    ->toBeReadonly();

arch('the DB facade stays out of the UI and job layers')
    ->expect(['App\Filament', 'App\Http\Controllers', 'App\Jobs'])
    ->not->toUse(['Illuminate\Support\Facades\DB']);

arch('actions do not depend on the transport layer')
    ->expect('App\Actions')
    ->not->toUse(['Livewire', 'Filament', 'Illuminate\Http\Request']);

arch('no abort or debugging helpers in app code')
    ->expect('App')
    ->not->toUse(['abort', 'abort_if', 'abort_unless', 'dd', 'dump']);
