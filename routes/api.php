<?php

use App\Http\Controllers\Api\GreenApiWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/green-api/webhook', GreenApiWebhookController::class)->name('api.green-api.webhook');
