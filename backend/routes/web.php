<?php

use Illuminate\Support\Facades\Route;

// API-only application. Redirect to documentation or API base.
Route::get('/', function () {
    return response()->json([
        'message' => 'Logiscool Pays Vert Portal API',
        'version' => '1.0',
        'documentation' => 'See /api/documentation',
    ]);
});
