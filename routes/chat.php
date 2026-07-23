<?php

use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('chat', [ChatController::class, 'index'])->name('chat.index');
    Route::post('chat', [ChatController::class, 'store'])->name('chat.store');
    Route::get('chat/conversations', [ChatController::class, 'conversations'])->name('chat.conversations');
    Route::get('chat/{conversation}', [ChatController::class, 'show'])->name('chat.show');
    Route::post('chat/{conversation}/messages', [ChatController::class, 'messages'])->name('chat.messages');
    Route::patch('chat/{conversation}/messages/{message}', [ChatController::class, 'updateMessage'])->name('chat.messages.update');
    Route::post('chat/{conversation}/regenerate', [ChatController::class, 'regenerate'])->name('chat.regenerate');
    Route::delete('chat/{conversation}', [ChatController::class, 'destroy'])->name('chat.destroy');
});
