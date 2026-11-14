<?php

namespace App\Filament\Manage\Resources\Skills\Pages;

use App\Filament\Manage\Resources\Skills\SkillResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSkills extends ManageRecords
{
    protected static string $resource = SkillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
