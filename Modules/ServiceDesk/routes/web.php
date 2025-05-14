<?php

use Illuminate\Support\Facades\Route;
use Modules\ServiceDesk\Http\Controllers\ServiceDeskController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('servicedesks', ServiceDeskController::class)->names('servicedesk');
});
