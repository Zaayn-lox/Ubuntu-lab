<?php

use App\Http\Controllers\Auth\GitHubController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\PostController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('posts.index');
});

Route::get('/dashboard', function () {
    return redirect()->route('posts.index');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::resource('posts', PostController::class)
    ->except(['index', 'show'])
    ->middleware('auth');

Route::resource('posts', PostController::class)
    ->only(['index', 'show']);

Route::post('/comments', [CommentController::class, 'store'])
    ->middleware('auth')
    ->name('comments.store');

Route::get('/auth/github', [GitHubController::class, 'redirect'])
    ->middleware('guest')
    ->name('auth.github');

Route::get('/auth/github/callback', [GitHubController::class, 'callback'])
    ->middleware('guest')
    ->name('auth.github.callback');

require __DIR__.'/auth.php';
