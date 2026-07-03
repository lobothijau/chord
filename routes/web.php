<?php

use App\Http\Controllers\SongController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('songs.index'));

Route::post('api/import', [SongController::class, 'apiImport'])->name('api.import');

Route::post('songs/preview', [SongController::class, 'preview'])->name('songs.preview');
Route::post('songs/fetch-url', [SongController::class, 'fetchUrl'])->name('songs.fetch-url');

Route::resource('songs', SongController::class);
