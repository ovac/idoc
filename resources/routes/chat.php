<?php

/**
 * Optional route for AI chat assistant.
 */

// Use string controller reference for broad Laravel compatibility
Route::post(
    config('idoc.chat.uri', 'chat'),
    '\\OVAC\\IDoc\\Http\\Controllers\\ChatController@chat'
)
    ->middleware((array) config('idoc.chat.middleware', []))
    ->withoutMiddleware((array) config('idoc.chat.remove_middleware', []))
    ->name(config('idoc.chat.route', 'idoc.chat'));
