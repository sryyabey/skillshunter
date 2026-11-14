<?php

use App\Http\Controllers\Api\V1\SkillController;
use App\Http\Middleware\AuthenticateSkillIntegration;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')
    ->middleware(['throttle:60,1,skill-integration', AuthenticateSkillIntegration::class])
    ->group(function (): void {
        Route::get('/skill-categories', [SkillController::class, 'categories'])->name('skill-categories.index');
        Route::get('/skills', [SkillController::class, 'index'])->name('skills.index');
        Route::get('/skills/{slug}', [SkillController::class, 'show'])->name('skills.show');
        Route::put('/skills/{slug}', [SkillController::class, 'upsert'])->name('skills.upsert');
    });
