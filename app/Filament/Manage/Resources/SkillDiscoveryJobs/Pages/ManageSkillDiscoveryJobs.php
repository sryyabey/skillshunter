<?php

namespace App\Filament\Manage\Resources\SkillDiscoveryJobs\Pages;

use App\Filament\Manage\Resources\SkillDiscoveryJobs\SkillDiscoveryJobResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSkillDiscoveryJobs extends ManageRecords
{
    protected static string $resource = SkillDiscoveryJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
