<?php

use App\Http\Controllers\ChatGPTController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\RecordController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// ユーザー認証
Auth::routes();

// プロフィール
Route::controller(UserController::class)->name('users.')->middleware('auth')->group(function () {
    Route::get('/records/users/{user}/drafts', 'myDrafts')->name('myDrafts');
    Route::prefix('/users')->group(function () {
        Route::get('{user}', 'showRecords')->name('showRecords');
        Route::get('/{user}/likes', 'showLikes')->name('showLikes');
        Route::get('/{user}/studyAnalytics', 'showStudyAnalytics')->name('showStudyAnalytics');
        Route::put('/{user}', 'updateGoal')->name('updateGoal');
    });
});

// プログラミング学習
Route::get('/', [RecordController::class, 'index'])->name('top');
Route::get('/records/getTotalStudyTime', [RecordController::class, 'getTotalStudyTime'])->name('records.getTotalStudyTime');
Route::resource('/records', RecordController::class)->except(['index', 'show'])->middleware('auth');
Route::resource('/records', RecordController::class)->only('show');

// テンプレート
Route::resource('/templates', TemplateController::class)->only('create', 'store')->middleware('auth');
Route::get('/api/templates', [TemplateController::class, 'index'])->name('templates.index')->middleware('auth');

// ChatGPT
Route::get('/records/{record}/review', [ChatGPTController::class, 'getReview'])->name('chatgpt.getReview')->middleware('auth');

// いいね
Route::prefix('/records')
    ->name('likes.')
    ->controller(LikeController::class)
    ->middleware('auth')
    ->group(function () {
        Route::put('{record}/like', 'store')->name('store');
        Route::delete('{record}/unlike', 'destroy')->name('destroy');
    });

// コメント
Route::resource('/records/comments', CommentController::class)->except(['index', 'show', 'create', 'edit'])->middleware('auth');
Route::get('/comments/{record}', [CommentController::class, 'getComments'])->name('comments.getComments');
