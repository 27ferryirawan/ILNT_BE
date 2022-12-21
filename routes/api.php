<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

// Route::get('/exchange-rates', [App\Http\Controllers\PhitomasController::class@exchangeRates']);
Route::get('/exchange-rates', 'PhitomasController@exchangeRates');
Route::get('/get-batch-id', 'PhitomasController@getBatchId');
Route::get('/read-config', 'PhitomasController@readConfig');
Route::post('/create-config', 'PhitomasController@createConfig');
Route::post('/update-config', 'PhitomasController@updateConfig');
Route::post('/delete-config', 'PhitomasController@deleteConfig');
Route::post('/import-config', 'PhitomasController@importConfig');
Route::post('/batch-delete-config', 'PhitomasController@batchDeleteConfig');

Route::post('/inventory-data-migration', 'PhitomasController@inventoryDataMigration');
Route::get('/inventory-data-migration-log', 'PhitomasController@dataMigratioonLog');
Route::post('/inventory-data-migration-v2', 'PhitomasControllerV2@inventoryDataMigration');


Route::get('/bnm-exchange-rates', 'PhitomasBNMController@exchangeRates');
