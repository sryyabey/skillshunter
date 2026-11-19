<?php

use App\Http\Controllers\Api\Public\V1\SkillController as PublicSkillController;
use App\Http\Controllers\Api\V1\DiscoveryJobController;
use App\Http\Controllers\Api\V1\SkillController;
use App\Http\Controllers\Api\V1\SkillQualityReviewController;
use App\Http\Controllers\Api\V1\SkillWorkerStatusController;
use App\Http\Middleware\AuthenticateSkillIntegration;
use Illuminate\Support\Facades\Route;

Route::prefix('public/v1')->name('api.public.v1.')
    ->middleware('throttle:60,1,public-catalog')
    ->group(function (): void {
        Route::get('/skills', [PublicSkillController::class, 'index'])->name('skills.index');
        Route::get('/skills/{slug}', [PublicSkillController::class, 'show'])->name('skills.show');
        Route::get('/categories', [PublicSkillController::class, 'categories'])->name('categories.index');
    });

Route::prefix('v1')->name('api.v1.')
    ->middleware(['throttle:60,1,skill-integration', AuthenticateSkillIntegration::class])
    ->group(function (): void {
        Route::post('/worker-status/finder', [SkillWorkerStatusController::class, 'finder']);
        Route::post('/discovery-jobs/claim', [DiscoveryJobController::class, 'claim']);
        Route::patch('/discovery-jobs/{id}', [DiscoveryJobController::class, 'update'])->whereNumber('id');
        Route::get('/skill-categories', [SkillController::class, 'categories'])->name('skill-categories.index');
        Route::get('/skills', [SkillController::class, 'index'])->name('skills.index');
        Route::get('/skills/{slug}', [SkillController::class, 'show'])->name('skills.show');
        Route::put('/skills/{slug}', [SkillController::class, 'upsert'])->name('skills.upsert');
    });

Route::prefix('v1/skill-quality-reviews')->name('api.v1.skill-quality-reviews.')
    ->middleware(['throttle:60,1,skill-quality-review', AuthenticateSkillIntegration::class.':quality_token'])
    ->group(function (): void {
        Route::post('/claim', [SkillQualityReviewController::class, 'claim'])->name('claim');
        Route::post('/{skill}/reject', [SkillQualityReviewController::class, 'reject'])->whereNumber('skill')->name('reject');
        Route::patch('/{skill}', [SkillQualityReviewController::class, 'complete'])->whereNumber('skill')->name('complete');
    });

Route::post('/v1/worker-status/controller', [SkillWorkerStatusController::class, 'controller'])
    ->middleware(['throttle:60,1,skill-quality-review', AuthenticateSkillIntegration::class.':quality_token']);
