<?php

use App\Http\Controllers\BookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('books')->group(function () {
    Route::get('/', [BookController::class, 'index']);          // list all
    Route::get('/{id}', [BookController::class, 'show']);       // single metadata
    Route::get('/{id}/download', [BookController::class, 'download']); // download PDF
    Route::post('/store', [BookController::class, 'store']);        // upload new book
    Route::put('/{id}', [BookController::class, 'update']);    // update metadata or replace pdf
    Route::delete('/{id}', [BookController::class, 'destroy']);// delete
});
