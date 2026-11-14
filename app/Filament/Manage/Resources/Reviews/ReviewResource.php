<?php

namespace App\Filament\Manage\Resources\Reviews;

use App\Filament\Manage\Resources\Reviews\Pages\ManageReviews;
use App\Models\Review;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Community';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('skill_id')->relationship('skill', 'title')->searchable()->preload()->required(),
                TextInput::make('author')->required()->maxLength(100),
                TextInput::make('model')->required()->maxLength(100),
                Select::make('verdict')->options(array_combine(Review::VERDICTS, Review::VERDICTS))->required(),
                Textarea::make('body')->required()->maxLength(3000)->columnSpanFull(),
                Toggle::make('is_approved')->label('Publish review'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('skill.title')->searchable(),
                TextColumn::make('author')->searchable(),
                TextColumn::make('verdict')->badge(),
                IconColumn::make('is_approved')->boolean(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([TernaryFilter::make('is_approved')->label('Moderation status')->trueLabel('Published')->falseLabel('Pending')])
            ->recordActions([
                Action::make('publish')
                    ->label('Publish')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->authorize('update')
                    ->visible(fn (Review $record): bool => ! $record->is_approved)
                    ->action(fn (Review $record): bool => $record->update(['is_approved' => true]))
                    ->successNotificationTitle('Review published'),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageReviews::route('/'),
        ];
    }
}
