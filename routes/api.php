<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DrugController;
use App\Http\Controllers\UserDrugController;

Route::post('register', [AuthController::class,'register']);
Route::post('login', [AuthController::class,'login']);
Route::get('search-drugs', [DrugController::class,'search'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class,'logout']);

    Route::get('user-drugs', [UserDrugController::class,'getUserDrugs']);
    Route::post('user-drugs', [UserDrugController::class,'addDrug']);
    Route::delete('user-drugs/{rxcui}', [UserDrugController::class,'deleteDrug']);
});
