<?php

use Illuminate\Support\Facades\Route;
use Modules\Accounting\Http\Controllers\AccountingController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('account', AccountingController::class)->names('web.accounting');
});
