<?php

use Illuminate\Support\Facades\Route;
use Modules\Collections\Http\Controllers\CollectionsController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('collections', CollectionsController::class)->names('collections');
});
