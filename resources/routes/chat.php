<?php

/**
 * Optional route for AI chat assistant.
 */

// Use string controller reference for broad Laravel compatibility
Route::post('chat', '\\OVAC\\IDoc\\Http\\Controllers\\ChatController@chat')
    ->middleware(['throttle:30,1'])
    ->name('chat');
