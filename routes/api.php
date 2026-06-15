<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Padosoft\EvidenceRiskReview\Http\Controllers\OpenApiController;
use Padosoft\EvidenceRiskReview\Http\Controllers\ProfileController;
use Padosoft\EvidenceRiskReview\Http\Controllers\ReviewController;
use Padosoft\EvidenceRiskReview\Http\Controllers\TaxonomyController;

Route::post('reviews', [ReviewController::class, 'store']);
Route::get('reviews', [ReviewController::class, 'index']);
Route::get('reviews/{review}', [ReviewController::class, 'show']);
Route::get('profiles', [ProfileController::class, 'index']);
Route::get('profiles/{key}', [ProfileController::class, 'show']);
Route::get('taxonomy', [TaxonomyController::class, 'show']);
Route::get('openapi.yaml', [OpenApiController::class, 'show']);
