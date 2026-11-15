<?php

namespace App\Filament\Manage\Resources\SkillSourceRepositories;

use App\Filament\Manage\Resources\SkillSourceRepositories\Pages\ManageSkillSourceRepositories;
use App\Models\SkillDiscoveryJob;
use App\Models\SkillSourceRepository;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class SkillSourceRepositoryResource extends Resource
{
    protected static ?string $model = SkillSourceRepository::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolder;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 31;

    protected static ?string $pluralModelLabel = 'Source Repositories';

    public static function canViewAny(): bool
    {
        return Gate::allows('viewAny', SkillDiscoveryJob::class);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('updated_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount([
                'files as active_files_count' => fn (Builder $files): Builder => $files->whereNull('removed_at'),
            ]))
            ->columns([
                TextColumn::make('full_name')->label('Repository')->searchable()->sortable(),
                TextColumn::make('active_files_count')->label('Active files')->numeric()->sortable(),
                TextColumn::make('default_branch')->toggleable(),
                TextColumn::make('last_scanned_commit')->label('Commit')->limit(12)
                    ->tooltip(fn (SkillSourceRepository $record): string => $record->last_scanned_commit)->toggleable(),
                TextColumn::make('updated_at')->label('Last scanned')->dateTime()->sortable(),
                TextColumn::make('github_id')->label('GitHub ID')->toggleable(isToggledHiddenByDefault: true),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSkillSourceRepositories::route('/')];
    }
}
