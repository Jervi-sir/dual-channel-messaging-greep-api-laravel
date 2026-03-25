<?php

use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MessageController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::get('conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('conversations/search-users', [ConversationController::class, 'searchUsers'])->name('conversations.search-users');
    Route::post('conversations', [ConversationController::class, 'store'])->name('conversations.store');
    Route::get('conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::get('conversations/{conversation}/messages', [ConversationController::class, 'loadMessages'])->name('conversations.messages');
    Route::post('conversations/{conversation}/messages', [MessageController::class, 'store'])->name('messages.store');
});

require __DIR__.'/settings.php';
