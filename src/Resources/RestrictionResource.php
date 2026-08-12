<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Filament\Resources;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Liberu\Ecommerce\Shipping\Data\RestrictionDefinition;
use Liberu\Ecommerce\Shipping\Enums\RestrictionType;
use Liberu\Ecommerce\Shipping\Filament\Resources\RestrictionResource\Pages\CreateRestriction;
use Liberu\Ecommerce\Shipping\Filament\Resources\RestrictionResource\Pages\EditRestriction;
use Liberu\Ecommerce\Shipping\Filament\Resources\RestrictionResource\Pages\ListRestrictions;
use Liberu\Ecommerce\Shipping\Filament\Support\MinorUnits;
use Liberu\Ecommerce\Shipping\Filament\Support\Tenant;
use Liberu\Ecommerce\Shipping\Models\Restriction;
use Liberu\Ecommerce\Shipping\Models\ServiceLevel;
use Liberu\Ecommerce\Shipping\Models\Zone;
use UnitEnum;

/**
 * A restriction refuses with a recorded reason; it never silently filters.
 *
 * The reason authored here is what a buyer is shown when a service level is
 * excluded, which is why it is a required field and not an afterthought.
 */
class RestrictionResource extends Resource
{
    protected static ?string $model = Restriction::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-no-symbol';

    protected static string|UnitEnum|null $navigationGroup = 'Shipping';

    protected static ?int $navigationSort = 40;

    public static function isScopedToTenant(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tenant_id', Tenant::current());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('restriction_type')
                ->required()
                ->live()
                ->default(RestrictionType::MaxWeightGrams->value)
                ->options([
                    RestrictionType::MaxWeightGrams->value => 'Maximum weight (grams)',
                    RestrictionType::MaxDimensionMm->value => 'Maximum dimension (millimetres)',
                    RestrictionType::MinSubtotalMinor->value => 'Minimum subtotal (minor units)',
                    RestrictionType::DestinationExcluded->value => 'Destination excluded',
                ]),
            TextInput::make('threshold')
                ->integer()
                ->helperText('Weight in integer grams, dimensions in integer millimetres, money in integer minor units. There is no unit column in this module.')
                ->visible(fn (callable $get): bool => $get('restriction_type') !== RestrictionType::DestinationExcluded->value),
            TextInput::make('reason_code')
                ->required()
                ->maxLength(64)
                ->helperText('A stable code a consuming surface can branch on.'),
            TextInput::make('reason')
                ->required()
                ->maxLength(255)
                ->helperText('Shown to the buyer in place of the excluded service level.'),
            Select::make('zone_id')
                ->label('Zone')
                ->helperText('Leave empty to apply everywhere.')
                ->options(fn (): array => Zone::query()->where('tenant_id', Tenant::current())->pluck('name', 'id')->all()),
            Select::make('service_level_id')
                ->label('Service level')
                ->helperText('Leave empty to apply to every service level.')
                ->options(fn (): array => ServiceLevel::query()->where('tenant_id', Tenant::current())->pluck('name', 'id')->all()),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('restriction_type')->badge()->label('Type'),
                TextColumn::make('threshold'),
                TextColumn::make('reason_code')->label('Reason code'),
                TextColumn::make('reason')->wrap(),
                TextColumn::make('zone_id')->label('Zone')->placeholder('Everywhere'),
                TextColumn::make('service_level_id')->label('Service level')->placeholder('All'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListRestrictions::route('/'),
            'create' => CreateRestriction::route('/create'),
            'edit' => EditRestriction::route('/{record}/edit'),
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function definitionFrom(array $data): RestrictionDefinition
    {
        $threshold = $data['threshold'] ?? null;

        return new RestrictionDefinition(
            restrictionType: RestrictionType::from((string) $data['restriction_type']),
            reasonCode: (string) $data['reason_code'],
            reason: (string) $data['reason'],
            zoneId: self::intOrNull($data['zone_id'] ?? null),
            serviceLevelId: self::intOrNull($data['service_level_id'] ?? null),
            threshold: self::intOrNull($threshold),
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }

    /**
     * A Filament `->integer()` input hands back an int **or a float**, so the
     * union has to admit one. Money never comes through here: those fields are
     * plain text inputs and stay decimal strings all the way into
     * {@see MinorUnits}, whose
     * signature refuses a float outright.
     */
    private static function intOrNull(int|float|string|null $value): ?int
    {
        return $value === null || trim((string) $value) === '' ? null : (int) $value;
    }
}
