<?php

namespace App\Filament\Manage\Resources\Reviews\Pages;

use App\Filament\Manage\Resources\Reviews\ReviewResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageReviews extends ManageRecords
{
    protected static string $resource = ReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
