<?php

use App\Http\Controllers\API\BarlistController;
use App\Http\Controllers\API\BillingsController;
use App\Http\Controllers\API\MembersController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\POWASController;
use App\Http\Controllers\API\ReadingsController as APIReadingsController;
use App\Http\Controllers\API\RegisterController;
use App\Models\User;

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
    // dd($request->user()->user_id);

    $result = User::with(['userinfo', 'roles.permissions'])->find($request->user()->user_id);

    $response = [
        'success' => true,
        'data' => $result,
        'message' => 'User with ID ' . $request->user()->user_id . ' retrieved',
    ];

    return response()->json($response, 200);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::resource('powas', POWASController::class);
    Route::resource('members', MembersController::class);
    Route::resource('readings', APIReadingsController::class);
    Route::resource('billings', BillingsController::class);
});

Route::controller(RegisterController::class)->group(function () {
    Route::post('register', 'register');
    Route::post('login', 'login');
    Route::post('logout', 'logout');
});

// Route::controller(RegisterController::class)->group(function () {
//     Route::post('logout', 'logout');
// })->middleware('auth:sanctum');

Route::post('/logout', [RegisterController::class, 'logout'])->middleware('auth:sanctum');

Route::get('/test-tokens', function () {
    return auth()->user()->tokens; // Should return a collection
})->middleware('auth:sanctum');

Route::get('/bar-list', [BarlistController::class, 'index']);
