<?php

namespace App\Filament\Manage\Resources\SkillSuggestions\Pages;

use App\Filament\Manage\Resources\SkillSuggestions\SkillSuggestionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSkillSuggestions extends ManageRecords
{
    protected static string $resource = SkillSuggestionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
