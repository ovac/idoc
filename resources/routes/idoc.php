<?php

/**
 * Routes for the api documentation.
 */

//This is the route for the documentation info page.
Route::view(config('idoc.documentation-route') . '/info', 'idoc::partials.info')->name('idoc.info');

//This is the route for the root documentation view page.
Route::view(config('idoc.documentation-route'), 'idoc::documentation')->name('idoc.root');
