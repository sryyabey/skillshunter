<?php

namespace App\Filament\Manage\Widgets;

use App\Filament\Manage\Resources\Reviews\ReviewResource;
use App\Filament\Manage\Resources\Skills\SkillResource;
use App\Filament\Manage\Resources\SkillSuggestions\SkillSuggestionResource;
use App\Models\Review;
use App\Models\Skill;
use App\Models\SkillSuggestion;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CatalogOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Catalog overview';

    protected static ?int $sort = 1;

    protected static ?bool $isLazyLoaded = false;

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return SkillResource::canViewAny() || ReviewResource::canViewAny() || SkillSuggestionResource::canViewAny();
    }

    protected function getStats(): array
    {
        $stats = [];

        if (SkillResource::canViewAny()) {
            $stats[] = Stat::make('Published skills', Skill::published()->count())
                ->description('Visible in the public directory')
                ->icon(Heroicon::OutlinedBookOpen)
                ->url(SkillResource::getUrl());
        }

        if (ReviewResource::canViewAny()) {
            $pending = Review::where('is_approved', false)->count();
            $stats[] = Stat::make('Pending reviews', $pending)
                ->description('Awaiting moderation')
                ->descriptionColor($pending > 0 ? 'warning' : 'gray')
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->url(ReviewResource::getUrl(parameters: ['filters' => ['is_approved' => ['value' => '0']]]));
        }

        if (SkillSuggestionResource::canViewAny()) {
            $stats[] = Stat::make('Skill suggestions', SkillSuggestion::count())
                ->description('Submitted by the community')
                ->icon(Heroicon::OutlinedLightBulb)
                ->url(SkillSuggestionResource::getUrl());
        }

        return $stats;
    }
}
