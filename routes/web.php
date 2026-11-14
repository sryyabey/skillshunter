<?php

use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SkillCatalogController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SkillCatalogController::class, 'index'])->name('welcome');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/skills/{slug}', [SkillCatalogController::class, 'show'])->name('detail');

Route::get('/skills/{slug}/download', [SkillCatalogController::class, 'download'])->name('skills.download');

Route::view('/privacy', 'legal.privacy')->name('privacy');
Route::view('/terms', 'legal.terms')->name('terms');

Route::middleware('throttle:10,1')->group(function (): void {
    Route::post('/reviews', [SkillCatalogController::class, 'review'])->name('reviews.store');
    Route::post('/skill-suggestions', [SkillCatalogController::class, 'suggest'])->name('suggestions.store');
    Route::post('/skills/{slug}/vote', [SkillCatalogController::class, 'voteSkill'])->name('skills.vote');
    Route::post('/reviews/{id}/vote', [SkillCatalogController::class, 'voteReview'])->whereNumber('id')->name('reviews.vote');
});
