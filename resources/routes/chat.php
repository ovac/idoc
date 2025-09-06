<?php

use Illuminate\Support\Facades\Route;

/**
 * Optional route for AI chat assistant.
 */

// Use string controller reference for broad Laravel compatibility
Route::post('chat', '\\OVAC\\IDoc\\Http\\Controllers\\ChatController@chat')
    ->middleware('api')
    ->name('chat');
