<?php

use App\Http\Controllers\SkillCatalogController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SkillCatalogController::class, 'index'])->name('welcome');
Route::get('/skills/{slug}', [SkillCatalogController::class, 'show'])->name('detail');

Route::middleware('throttle:10,1')->group(function (): void {
    Route::post('/reviews', [SkillCatalogController::class, 'review'])->name('reviews.store');
    Route::post('/skill-suggestions', [SkillCatalogController::class, 'suggest'])->name('suggestions.store');
    Route::post('/skills/{slug}/vote', [SkillCatalogController::class, 'voteSkill'])->name('skills.vote');
    Route::post('/reviews/{id}/vote', [SkillCatalogController::class, 'voteReview'])->whereNumber('id')->name('reviews.vote');
});
