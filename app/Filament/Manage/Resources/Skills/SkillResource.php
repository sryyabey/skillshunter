<?php

namespace App\Filament\Manage\Resources\Skills;

use App\Filament\Manage\Resources\Skills\Pages\ManageSkills;
use App\Models\Skill;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
                Textarea::make('instructions')->requiredWithout('source_markdown')->dehydrateStateUsing(fn (?string $state): string => $state ?? '')->rows(10)->columnSpanFull(),
                Textarea::make('source_markdown')->label('Original SKILL.md')->rows(12)->maxLength(100000)->columnSpanFull(),
                TextInput::make('source_url')->url()->maxLength(2048)->columnSpanFull(),
                TextInput::make('source_commit')->label('GitHub source commit')->maxLength(40)->regex('/\A[0-9a-f]{40}\z/')->required(fn (?Skill $record): bool => filled($record?->source_commit)),
                DatePicker::make('ai_reviewed_at')->label('AI reviewed on')->disabled(),
                TextInput::make('approved_by')->label('Approving editor ID')->disabled(),
                TextInput::make('license')->maxLength(255),
                TextInput::make('version')->maxLength(32),
                DatePicker::make('verified_at')->label('Listing approval date'),
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
                IconColumn::make('ai_reviewed')->label('AI reviewed')->boolean()
                    ->state(fn (Skill $record): bool => $record->ai_reviewed_at !== null),
                TextColumn::make('ai_reviewed_at')->label('AI reviewed on')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('verified_at')->label('Listing approved on')->date()->sortable(),
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
                TernaryFilter::make('ai_reviewed_at')->label('AI review')->nullable()
                    ->trueLabel('Reviewed')->falseLabel('Pending review'),
                TernaryFilter::make('verified_at')
                    ->label('Listing approval')
                    ->nullable()
                    ->trueLabel('Approved')
                    ->falseLabel('Pending approval'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('aiReviewDetails')
                    ->label('AI review details')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->authorize('viewAny')
                    ->visible(fn (Skill $record): bool => $record->ai_reviewed_at !== null)
                    ->modalHeading('AI review details')
                    ->modalWidth(Width::FiveExtraLarge)
                    ->modalSubmitAction(false)
                    ->schema([
                        TextEntry::make('ai_reviewed_at')->label('Reviewed on')->dateTime(),
                        TextEntry::make('previous_publication_status')->label('Before review')
                            ->state(fn (Skill $record): ?string => isset($record->ai_review_details['was_published'])
                                ? ($record->ai_review_details['was_published'] ? 'Published' : 'Draft') : null)
                            ->placeholder('Unavailable'),
                        TextEntry::make('review_source_url')->label('Source used')
                            ->state(fn (Skill $record): ?string => $record->ai_review_details['source_url'] ?? null)
                            ->url(fn (?string $state): ?string => Str::isUrl($state ?? '', ['https', 'http']) ? $state : null)
                            ->extraAttributes(['class' => 'break-all'])->columnSpanFull()->placeholder('Unavailable'),
                        TextEntry::make('review_source_commit')->label('Source commit')
                            ->state(fn (Skill $record): ?string => $record->ai_review_details['source_commit'] ?? null)
                            ->placeholder('Unavailable'),
                        TextEntry::make('review_change_status')
                            ->hiddenLabel()
                            ->state(fn (Skill $record): string => $record->ai_review_details === null
                                ? 'This review was recorded before change details were available.'
                                : 'The review recorded no editorial changes.')
                            ->visible(fn (Skill $record): bool => empty($record->ai_review_details['changes']))
                            ->columnSpanFull(),
                        RepeatableEntry::make('review_changes')->label('Changes')
                            ->state(fn (Skill $record): array => $record->ai_review_details['changes'] ?? [])
                            ->visible(fn (Skill $record): bool => filled($record->ai_review_details['changes'] ?? []))
                            ->schema([
                                TextEntry::make('field')->hiddenLabel()->weight('bold')->columnSpanFull()
                                    ->formatStateUsing(fn (string $state): string => Str::headline($state)),
                                TextEntry::make('before')->label('Before')->listWithLineBreaks()->placeholder('Empty')
                                    ->extraAttributes(['class' => 'whitespace-pre-wrap break-all']),
                                TextEntry::make('after')->label('After')->listWithLineBreaks()->placeholder('Empty')
                                    ->extraAttributes(['class' => 'whitespace-pre-wrap break-all']),
                            ])->columns(['default' => 1, 'md' => 2])->columnSpanFull(),
                    ]),
                Action::make('approveAndPublish')
                    ->label('Approve and publish')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->authorize('update')
                    ->visible(fn (Skill $record): bool => ! $record->is_published && filled($record->source_commit))
                    ->requiresConfirmation()
                    ->action(function (Skill $record, Action $action): void {
                        try {
                            $record->forceFill([
                                'verified_at' => today(),
                                'approved_by' => Filament::auth()->id(),
                                'is_published' => true,
                            ])->save();
                        } catch (ValidationException $exception) {
                            Notification::make()->danger()->title('Skill could not be published')
                                ->body($exception->validator->errors()->first())->send();
                            $action->halt();
                        }
                    })
                    ->successNotificationTitle('Skill published'),
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
                        ->action(function (Collection $records, array $data, BulkAction $action): void {
                            foreach ($records as $skill) {
                                try {
                                    $saved = $skill->update(['is_published' => $data['is_published'] === 'published']);
                                    if (! $saved) {
                                        $action->reportBulkProcessingFailure();
                                    }
                                } catch (ValidationException $exception) {
                                    $reason = $exception->validator->errors()->first();
                                    $action->reportBulkProcessingFailure($reason, fn (int $count): string => "{$count} skipped. {$reason}");
                                }
                            }
                        })
                        ->successNotificationTitle('Publication status updated')
                        ->failureNotificationTitle(fn (int $successCount, int $failureCount): string => "{$successCount} updated, {$failureCount} skipped")
                        ->missingBulkAuthorizationFailureNotificationMessage(fn (int $failureCount): string => "{$failureCount} skipped due to permissions.")
                        ->missingBulkProcessingFailureNotificationMessage(fn (int $failureCount): string => "{$failureCount} could not be saved.")
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('setVerificationDate')
                        ->label('Set listing approval date')
                        ->authorizeIndividualRecords('update')
                        ->schema([
                            DatePicker::make('verified_at')
                                ->label('Listing approval date')
                                ->default(fn (): string => today()->toDateString())
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            DB::transaction(function () use ($records, $data): void {
                                foreach ($records as $skill) {
                                    $skill->verified_at = $data['verified_at'];
                                    if ($skill->source_commit && ! $skill->approved_by) {
                                        $skill->approved_by = Filament::auth()->id();
                                    }
                                    $skill->save();
                                }
                            });
                        })
                        ->successNotificationTitle('Listing approval date updated')
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
