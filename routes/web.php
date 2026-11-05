<?php

use App\Http\Controllers\ServerController;
Route::get('/servidor', [ServerController::class, 'index']);
