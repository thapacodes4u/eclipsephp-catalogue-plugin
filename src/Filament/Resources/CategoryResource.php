<?php

namespace Eclipse\Catalogue\Filament\Resources;

use Eclipse\Catalogue\Filament\Resources\CategoryResource\Pages\CreateCategory;
use Eclipse\Catalogue\Filament\Resources\CategoryResource\Pages\EditCategory;
use Eclipse\Catalogue\Filament\Resources\CategoryResource\Pages\ListCategories;
use Eclipse\Catalogue\Filament\Resources\CategoryResource\Pages\SortingCategory;
use Eclipse\Catalogue\Models\Category;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class CategoryResource extends Resource
{
    use Translatable;

    protected static ?string $model = Category::class;

    protected static ?string $slug = 'catalogue/categories';

    protected static string|\UnitEnum|null $navigationGroup = 'Catalogue';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $tenantOwnershipRelationshipName = 'site';

    public static function getNavigationGroup(): ?string
    {
        return __('eclipse-catalogue::categories.navigation_group');
    }

    public static function getPluralModelLabel(): string
    {
        return __('eclipse-catalogue::categories.title');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('eclipse-catalogue::categories.form.sections.basic_information'))
                    ->columns(2)
                    ->schema([
                        Select::make('parent_id')
                            ->label(__('eclipse-catalogue::categories.form.fields.parent_id'))
                            ->options(Category::getHierarchicalOptions())
                            ->afterStateHydrated(function ($state, $context, Set $set): void {
                                if ($context === 'edit' && $state == -1) {
                                    $set('parent_id', null);
                                }
                            })
                            ->searchable()
                            ->placeholder(__('eclipse-catalogue::categories.form.fields.parent_id_placeholder'))
                            ->rules([
                                fn (?Category $record): callable => function (string $attribute, $value, $fail) use ($record): void {
                                    if (empty($value) || ! $record) {
                                        return;
                                    }

                                    $current = Category::find($value);
                                    $visited = [];

                                    while ($current && ! in_array($current->id, $visited)) {
                                        if ($current->id === $record->id) {
                                            $fail(__('eclipse-catalogue::categories.form.errors.parent_id'));

                                            return;
                                        }

                                        $visited[] = $current->id;
                                        $current = $current->parent_id ? Category::find($current->parent_id) : null;

                                        if (count($visited) > 50) {
                                            break;
                                        }
                                    }
                                },
                            ]),
                        TextInput::make('name')
                            ->label(__('eclipse-catalogue::categories.form.fields.name'))
                            ->required()
                            ->maxLength(255)
                            ->placeholder(__('eclipse-catalogue::categories.form.fields.name_placeholder'))
                            ->helperText(fn ($record) => $record?->getFullPath())
                            ->live(debounce: 300)
                            ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                $sefKey = Str::slug($state);
                                $set('sef_key', $sefKey);
                            }),
                        TextInput::make('code')
                            ->label(__('eclipse-catalogue::categories.form.fields.code'))
                            ->maxLength(255)
                            ->placeholder(__('eclipse-catalogue::categories.form.fields.code_placeholder')),
                        TextInput::make('sef_key')
                            ->label(__('eclipse-catalogue::categories.form.fields.sef_key'))
                            ->maxLength(255)
                            ->placeholder(__('eclipse-catalogue::categories.form.fields.sef_key_placeholder'))
                            ->helperText(__('eclipse-catalogue::categories.form.fields.sef_key_helper'))
                            ->rules([
                                fn (Get $get, ?Model $record): callable => function (string $attribute, $value, $fail) use ($record): void {
                                    if (empty($value)) {
                                        return;
                                    }

                                    $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');
                                    $siteId = Filament::getTenant()?->id;
                                    $currentLocale = app()->getLocale();

                                    $query = Category::query();

                                    if ($tenantFK && $siteId) {
                                        $query->where($tenantFK, $siteId);
                                    }

                                    $query->where(function ($q) use ($value, $currentLocale): void {
                                        $q->whereJsonContains('sef_key', $value)
                                            ->orWhereJsonContains("sef_key->{$currentLocale}", $value);
                                    });

                                    if ($record && $record->exists) {
                                        $query->where('id', '!=', $record->id);
                                    }

                                    if ($query->exists()) {
                                        $fail(__('eclipse-catalogue::categories.form.errors.sef_key'));
                                    }
                                },
                            ]),
                    ]),

                Section::make(__('eclipse-catalogue::categories.form.sections.content'))
                    ->compact()
                    ->schema([
                        Textarea::make('short_desc')
                            ->label(__('eclipse-catalogue::categories.form.fields.short_desc'))
                            ->rows(3)
                            ->placeholder(__('eclipse-catalogue::categories.form.fields.short_desc_placeholder')),

                        RichEditor::make('description')
                            ->label(__('eclipse-catalogue::categories.form.fields.description'))
                            ->placeholder(__('eclipse-catalogue::categories.form.fields.description_placeholder'))
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'bulletList',
                                'orderedList',
                                'link',
                                'undo',
                                'redo',
                            ]),
                    ]),

                Section::make(__('eclipse-catalogue::categories.form.sections.media_settings'))
                    ->compact()
                    ->schema([
                        FileUpload::make('image')
                            ->columnSpanFull()
                            ->label(__('eclipse-catalogue::categories.form.fields.image'))
                            ->image()
                            ->imageEditor()
                            ->directory('categories')
                            ->visibility('public'),

                        Grid::make(2)
                            ->schema([
                                Toggle::make('is_active')
                                    ->label(__('eclipse-catalogue::categories.form.fields.is_active'))
                                    ->default(true)
                                    ->helperText(__('eclipse-catalogue::categories.form.fields.is_active_helper')),

                                Toggle::make('recursive_browsing')
                                    ->label(__('eclipse-catalogue::categories.form.fields.recursive_browsing'))
                                    ->default(false)
                                    ->helperText(__('eclipse-catalogue::categories.form.fields.recursive_browsing_helper')),
                            ]),
                    ]),

                Section::make(__('eclipse-catalogue::categories.form.sections.system_information'))
                    ->compact()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Placeholder::make('created_at')
                                    ->label(__('eclipse-catalogue::categories.form.fields.created_at'))
                                    ->content(fn (?Category $record): string => $record?->created_at?->diffForHumans() ?? 'Not yet saved'),

                                Placeholder::make('updated_at')
                                    ->label(__('eclipse-catalogue::categories.form.fields.updated_at'))
                                    ->content(fn (?Category $record): string => $record?->updated_at?->diffForHumans() ?? 'Not yet saved'),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                $selectArray = Category::selectArray();

                unset($selectArray[Category::defaultParentKey()]);
                $orderedIds = array_keys($selectArray);

                if (! empty($orderedIds)) {
                    $idsString = implode(',', $orderedIds);

                    return $query->orderByRaw("FIELD(id, {$idsString})");
                }

                return $query->orderBy('sort');
            })
            ->columns([
                ImageColumn::make('image')
                    ->label(__('eclipse-catalogue::categories.table.columns.image'))
                    ->size(40)
                    ->circular()
                    ->sortable(false)
                    ->defaultImageUrl(fn (Model $record): string => 'https://ui-avatars.com/api/?name='.urlencode($record->name).'&color=7F9CF5&background=EBF4FF'),

                TextColumn::make('name')
                    ->label(__('eclipse-catalogue::categories.table.columns.name'))
                    ->searchable()
                    ->sortable(false)
                    ->lineClamp(1)
                    ->formatStateUsing(fn (Model $record): HtmlString => new HtmlString($record->getTreeFormattedName()))
                    ->tooltip(fn ($record) => $record->getFullPath()),

                TextColumn::make('sef_key')
                    ->label(__('eclipse-catalogue::categories.table.columns.sef_key'))
                    ->label('SEF Key')
                    ->searchable()
                    ->fontFamily('mono')
                    ->copyable()
                    ->size('sm')
                    ->sortable(false)
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->label(__('eclipse-catalogue::categories.table.columns.is_active'))
                    ->label('Active')
                    ->boolean()
                    ->sortable(false)
                    ->alignCenter(),

                TextColumn::make('code')
                    ->label(__('eclipse-catalogue::categories.table.columns.code'))
                    ->searchable()
                    ->fontFamily('mono')
                    ->placeholder('—')
                    ->sortable(false)
                    ->copyable(),

                IconColumn::make('recursive_browsing')
                    ->label(__('eclipse-catalogue::categories.table.columns.recursive_browsing'))
                    ->boolean()
                    ->alignCenter()
                    ->sortable(false)
                    ->tooltip(__('eclipse-catalogue::categories.table.tooltips.recursive_browsing')),

                IconColumn::make('description')
                    ->label(__('eclipse-catalogue::categories.table.columns.description'))
                    ->alignCenter()
                    ->sortable(false)
                    ->getStateUsing(fn ($record) => ! empty($record->description))
                    ->icon(fn ($state) => $state ? 'heroicon-o-document-text' : 'heroicon-o-document')
                    ->color(fn ($state) => $state ? 'success' : 'gray')
                    ->tooltip(fn ($record) => ! empty($record->description) ? __('eclipse-catalogue::categories.table.tooltips.has_description') : __('eclipse-catalogue::categories.table.tooltips.no_description')),

                TextColumn::make('short_desc')
                    ->sortable(false)
                    ->label(__('eclipse-catalogue::categories.table.columns.short_desc'))
                    ->lineClamp(2),
            ])

            ->paginated([10, 25, 50, 100])
            ->searchable()
            ->filters([
                TrashedFilter::make(),

                SelectFilter::make('parent_id')
                    ->label(__('eclipse-catalogue::categories.filters.parent_category'))
                    ->multiple()
                    ->options(Category::getHierarchicalOptions())
                    ->placeholder(__('eclipse-catalogue::categories.filters.category_placeholder')),

                SelectFilter::make('is_active')
                    ->label(__('eclipse-catalogue::categories.filters.is_active'))
                    ->options([
                        1 => __('eclipse-catalogue::categories.filters.active'),
                        0 => __('eclipse-catalogue::categories.filters.inactive'),
                    ])
                    ->placeholder('All Statuses'),

                Filter::make('has_description')
                    ->label(__('eclipse-catalogue::categories.filters.has_description'))
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('description'))
                    ->toggle(),

            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->label(__('eclipse-catalogue::categories.actions.edit')),
                    DeleteAction::make()
                        ->label(__('eclipse-catalogue::categories.actions.delete')),
                    RestoreAction::make()
                        ->label(__('eclipse-catalogue::categories.actions.restore')),
                    ForceDeleteAction::make()
                        ->label(__('eclipse-catalogue::categories.actions.force_delete')),
                ])
                    ->hiddenLabel()
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->size('sm')
                    ->color('gray')
                    ->button(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label(__('eclipse-catalogue::categories.actions.delete')),
                    RestoreBulkAction::make()
                        ->label(__('eclipse-catalogue::categories.actions.restore')),
                    ForceDeleteBulkAction::make()
                        ->label(__('eclipse-catalogue::categories.actions.force_delete')),
                ])->label(__('eclipse-catalogue::categories.actions.bulk_actions')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'sorting' => SortingCategory::route('/sorting'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'short_desc', 'description', 'sef_key', 'code'];
    }
}
