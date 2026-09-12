<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BabyController;
use App\Http\Controllers\Api\DiaperChangeController;
use App\Http\Controllers\Api\FeedingSessionController;
use App\Http\Controllers\Api\GrowthMeasurementController;
use App\Http\Controllers\Api\MedicationDoseController;
use App\Http\Controllers\Api\SleepSessionController;
use App\Http\Controllers\Api\SymptomLogController;
use App\Http\Controllers\Api\TemperatureReadingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);

    Route::apiResource('babies', BabyController::class)->only(['index', 'store', 'show']);

    $trackers = [
        'feedings' => FeedingSessionController::class,
        'sleeps' => SleepSessionController::class,
        'diapers' => DiaperChangeController::class,
        'temperatures' => TemperatureReadingController::class,
        'growths' => GrowthMeasurementController::class,
        'medications' => MedicationDoseController::class,
        'symptoms' => SymptomLogController::class,
    ];

    foreach ($trackers as $uri => $controller) {
        Route::apiResource("babies.{$uri}", $controller)
            ->shallow()
            ->only(['index', 'store', 'update', 'destroy']);
    }
});
