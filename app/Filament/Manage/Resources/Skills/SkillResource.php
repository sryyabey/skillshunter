<?php

namespace App\Filament\Manage\Resources\Skills;

use App\Filament\Manage\Resources\Skills\Pages\ManageSkills;
use App\Models\Skill;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class SkillResource extends Resource
{
    protected static ?string $model = Skill::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('category_id')->relationship('category', 'name')->searchable()->preload()->required(),
                TextInput::make('title')->required()->maxLength(255),
                TextInput::make('slug')->required()->maxLength(160)->alphaDash()->unique(ignoreRecord: true),
                Textarea::make('summary')->required()->columnSpanFull(),
                TextInput::make('seo_title')->label('SEO title')->maxLength(70)->columnSpanFull(),
                Textarea::make('seo_description')->label('SEO description')->maxLength(170)->columnSpanFull(),
                TagsInput::make('seo_keywords')->label('SEO keywords')->rules(['max:12'])->nestedRecursiveRules(['string', 'max:100', 'distinct:ignore_case'])->columnSpanFull(),
                Textarea::make('editor_note')->columnSpanFull(),
                Textarea::make('instructions')->required()->rows(10)->columnSpanFull(),
                TextInput::make('source_url')->url()->maxLength(2048)->columnSpanFull(),
                TextInput::make('license')->maxLength(255),
                TextInput::make('version')->maxLength(32),
                DatePicker::make('verified_at'),
                Toggle::make('is_published'),
                TagsInput::make('use_cases')->columnSpanFull(),
                TagsInput::make('limitations')->columnSpanFull(),
                Repeater::make('compatibility')->schema([
                    TextInput::make('model')->required(),
                    TextInput::make('success')->numeric()->minValue(0)->maxValue(100)->required(),
                ])->columns(2)->columnSpanFull(),
                Textarea::make('setup_tip')->columnSpanFull(),
                Textarea::make('sample_input')->columnSpanFull(),
                Textarea::make('sample_output')->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('category.name')->sortable(),
                IconColumn::make('is_published')->boolean(),
                TextColumn::make('verified_at')->date()->sortable(),
                TextColumn::make('reviews_count')->counts('reviews')->label('Reviews'),
            ])
            ->filters([
                TernaryFilter::make('is_published')
                    ->label('Publication status')
                    ->trueLabel('Published')
                    ->falseLabel('Draft'),
                SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('verified_at')
                    ->label('Verification status')
                    ->nullable()
                    ->trueLabel('Verified')
                    ->falseLabel('Unverified'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('setPublicationStatus')
                        ->label('Set publication status')
                        ->authorizeIndividualRecords('update')
                        ->schema([
                            Select::make('is_published')
                                ->label('Publication status')
                                ->options(['published' => 'Published', 'draft' => 'Draft'])
                                ->in(['published', 'draft'])
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            DB::transaction(function () use ($records, $data): void {
                                foreach ($records as $skill) {
                                    $skill->update(['is_published' => $data['is_published'] === 'published']);
                                }
                            });
                        })
                        ->successNotificationTitle('Publication status updated')
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('setVerificationDate')
                        ->label('Set verification date')
                        ->authorizeIndividualRecords('update')
                        ->schema([
                            DatePicker::make('verified_at')
                                ->label('Verification date')
                                ->default(fn (): string => today()->toDateString())
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            DB::transaction(function () use ($records, $data): void {
                                foreach ($records as $skill) {
                                    $skill->update(['verified_at' => $data['verified_at']]);
                                }
                            });
                        })
                        ->successNotificationTitle('Verification date updated')
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSkills::route('/'),
        ];
    }
}
