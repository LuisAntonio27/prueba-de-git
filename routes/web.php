<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::view('/', 'inicio')->name('inicio');
Route::view('/about', 'about')->name('about');

Route::put('titan/{cuenta}', function ($cuenta) {
    return 'Se actualizo la cuenta';
});
Route::get('/portfolio', 'PortfolioController')->name('portfolio');
Route::view('/contact', 'contact')->name('contact');
Route::post('users/{id}', function ($id) {
    return 'Cambio en las rutas para post';
});

// Route::resource('projects', 'PortfolioController')->only(['index', 'show']);

Route::resource('projects', PortfolioController)->only(['index', 'show']);