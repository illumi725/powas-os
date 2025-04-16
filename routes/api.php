<?php

use App\Http\Controllers\API\BillingsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\POWASController;
use App\Http\Controllers\API\MembersController;
use App\Http\Controllers\API\ReadingsAPIController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Route::middleware([
//     'auth:sanctum',
// ])->group(function () {
    Route::get('/powas', [POWASController::class, 'index']);
    Route::get('/powas/{id}', [PowasController::class, 'show']);
    Route::get('/members', [MembersController::class, 'index']);
    Route::get('/billings/{powasID?}', [BillingsController::class, 'unpaidBills']);
    Route::get('/readings/{powasID?}', [ReadingsAPIController::class, 'readingIndex']);
// });
