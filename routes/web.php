<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/docs', function () {
    return view('swagger');
})->name('swagger');

Route::get('/openapi.yaml', function () {
    $path = base_path('openapi.yaml');
    abort_if(!File::exists($path), 404);

    return response(File::get($path), 200, [
        'Content-Type' => 'application/x-yaml',
        'Cache-Control' => 'public, max-age=60',
    ]);
});
