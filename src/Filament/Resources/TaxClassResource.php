<?php

namespace Eclipse\Catalogue\Filament\Resources;

use Eclipse\Catalogue\Filament\Resources\TaxClassResource\Pages\CreateTaxClass;
use Eclipse\Catalogue\Filament\Resources\TaxClassResource\Pages\EditTaxClass;
use Eclipse\Catalogue\Filament\Resources\TaxClassResource\Pages\ListTaxClasses;
use Eclipse\Catalogue\Models\TaxClass;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TaxClassResource extends Resource
{
    protected static ?string $model = TaxClass::class;

    protected static ?string $slug = 'tax-classes';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static string|\UnitEnum|null $navigationGroup = 'Catalogue';

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isScopedToTenant = true;

    protected static ?string $tenantOwnershipRelationshipName = 'tenant';

    public static function getModelLabel(): string
    {
        return __('eclipse-catalogue::tax-class.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('eclipse-catalogue::tax-class.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('eclipse-catalogue::tax-class.fields.name'))
                    ->required()
                    ->maxLength(255)
                    ->unique(
                        table: 'pim_tax_classes',
                        column: 'name',
                        ignoreRecord: true,
                        modifyRuleUsing: function ($rule) {
                            // Add tenant scope to unique validation
                            $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');
                            $tenantId = Filament::getTenant()?->id;
                            if ($tenantFK && $tenantId) {
                                $rule->where($tenantFK, $tenantId);
                            }

                            return $rule;
                        }
                    ),

                Textarea::make('description')
                    ->label(__('eclipse-catalogue::tax-class.fields.description'))
                    ->rows(3)
                    ->maxLength(65535),

                TextInput::make('rate')
                    ->label(__('eclipse-catalogue::tax-class.fields.rate'))
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->step(0.01)
                    ->suffix('%'),

                Toggle::make('is_default')
                    ->label(__('eclipse-catalogue::tax-class.fields.is_default'))
                    ->helperText(__('eclipse-catalogue::tax-class.messages.default_class_help')),

                Placeholder::make('created_at')
                    ->label('Created Date')
                    ->content(fn (?TaxClass $record): string => $record?->created_at?->diffForHumans() ?? '-'),

                Placeholder::make('updated_at')
                    ->label('Last Modified Date')
                    ->content(fn (?TaxClass $record): string => $record?->updated_at?->diffForHumans() ?? '-'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable(),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('description')
                    ->searchable()
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('rate')
                    ->suffix('%')
                    ->sortable(),

                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTaxClasses::route('/'),
            'create' => CreateTaxClass::route('/create'),
            'edit' => EditTaxClass::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
