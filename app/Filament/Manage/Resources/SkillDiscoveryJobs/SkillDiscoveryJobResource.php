<?php

namespace App\Filament\Manage\Resources\SkillDiscoveryJobs;

use App\Filament\Manage\Resources\SkillDiscoveryJobs\Pages\ManageSkillDiscoveryJobs;
use App\Models\SkillDiscoveryJob;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class SkillDiscoveryJobResource extends Resource
{
    protected static ?string $model = SkillDiscoveryJob::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('topic')->required()->maxLength(160)->placeholder('PowerPoint slide generation'),
            Select::make('category_id')->relationship('category', 'name')->required(),
            Select::make('result_limit')->options([1 => '1', 2 => '2', 3 => '3'])->default(3)->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([
            TextColumn::make('topic')->searchable(),
            TextColumn::make('category.name'),
            TextColumn::make('status')->badge(),
            TextColumn::make('attempts'),
            TextColumn::make('results')
                ->state(fn (SkillDiscoveryJob $record): array => collect($record->results)->map(
                    fn (array $result): string => $result['slug'].' ('.$result['status'].') · '.$result['source_url']
                        .($result['warnings'] ? ' · '.implode(' ', $result['warnings']) : '')
                )->all())
                ->listWithLineBreaks()->wrap(),
            TextColumn::make('error')->wrap(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSkillDiscoveryJobs::route('/')];
    }
}
