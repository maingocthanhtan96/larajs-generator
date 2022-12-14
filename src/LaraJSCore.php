<?php

namespace LaraJS\Core;

use Illuminate\Support\Facades\Route;

class LaraJSCore
{
    public static function routes()
    {
        Route::group(
            ['prefix' => 'generators', 'controller' => '\\LaraJS\\Core\Controllers\\GeneratorController'],
            function () {
                Route::get('check-model', 'checkModel');
                Route::get('check-column', 'checkColumn');
                Route::get('get-models', 'getModels');
                Route::get('get-all-models', 'getAllModels');
                Route::get('get-columns', 'getColumns');
                Route::post('relationship', 'generateRelationship');
                Route::get('diagram', 'generateDiagram');
            },
        );
        Route::apiResource('generators', '\\LaraJS\\Core\Controllers\\GeneratorController');
    }
}
