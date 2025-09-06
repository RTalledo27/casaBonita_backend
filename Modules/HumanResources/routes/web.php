<?php

use Illuminate\Support\Facades\Route;
use Modules\HumanResources\Http\Controllers\HumanResourcesController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('humanresources', HumanResourcesController::class)->names('web.humanresources');
});
