<?php

namespace App\Filament\Manage\Resources\SkillSourceFiles;

use App\Filament\Manage\Resources\SkillSourceFiles\Pages\ManageSkillSourceFiles;
use App\Models\SkillDiscoveryJob;
use App\Models\SkillSourceFile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class SkillSourceFileResource extends Resource
{
    protected static ?string $model = SkillSourceFile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 32;

    protected static ?string $pluralModelLabel = 'Source Files';

    public static function canViewAny(): bool
    {
        return Gate::allows('viewAny', SkillDiscoveryJob::class);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('repository.full_name')->label('Repository')->searchable(),
                TextColumn::make('path')->label('SKILL.md path')->searchable()->limit(55)
                    ->tooltip(fn (SkillSourceFile $record): string => $record->path),
                TextColumn::make('skill.title')->label('Catalog entry')->placeholder('Unlinked')->limit(35)
                    ->tooltip(fn (SkillSourceFile $record): ?string => $record->skill?->title),
                TextColumn::make('discoveryJob.status')->label('Draft job')->badge()->placeholder('No job'),
                TextColumn::make('removed_at')->label('Removed')->dateTime()->placeholder('Active')->sortable()->toggleable(),
                TextColumn::make('commit_sha')->label('Commit')->limit(12)
                    ->tooltip(fn (SkillSourceFile $record): string => $record->commit_sha)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('blob_sha')->label('Blob SHA')->limit(12)
                    ->tooltip(fn (SkillSourceFile $record): string => $record->blob_sha)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('repository')->relationship('repository', 'full_name')->searchable(),
                TernaryFilter::make('skill_id')->label('Catalog match')->nullable()
                    ->trueLabel('Linked')->falseLabel('Unlinked'),
                TernaryFilter::make('removed_at')->label('Source status')->nullable()
                    ->trueLabel('Removed')->falseLabel('Active'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSkillSourceFiles::route('/')];
    }
}
