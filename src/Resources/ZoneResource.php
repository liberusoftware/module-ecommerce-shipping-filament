<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Filament\Resources;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Liberu\Ecommerce\Shipping\Data\TerritoryDefinition;
use Liberu\Ecommerce\Shipping\Data\ZoneDefinition;
use Liberu\Ecommerce\Shipping\Filament\Resources\ZoneResource\Pages\CreateZone;
use Liberu\Ecommerce\Shipping\Filament\Resources\ZoneResource\Pages\EditZone;
use Liberu\Ecommerce\Shipping\Filament\Resources\ZoneResource\Pages\ListZones;
use Liberu\Ecommerce\Shipping\Filament\Resources\ZoneResource\Pages\SavesZone;
use Liberu\Ecommerce\Shipping\Filament\Support\Tenant;
use Liberu\Ecommerce\Shipping\Models\Zone;
use UnitEnum;

/**
 * Zones are authored here, so the write-time overlap refusal is this package's
 * to surface. See {@see SavesZone}.
 */
class ZoneResource extends Resource
{
    protected static ?string $model = Zone::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-globe-europe-africa';

    protected static string|UnitEnum|null $navigationGroup = 'Shipping';

    protected static ?string $recordTitleAttribute = 'code';

    protected static ?int $navigationSort = 10;

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
            TextInput::make('code')
                ->required()
                ->maxLength(64)
                ->helperText('Stable identifier. A recorded price snapshots it, so it outlives the zone.'),
            TextInput::make('name')
                ->required()
                ->maxLength(120),
            TextInput::make('precedence')
                ->integer()
                ->required()
                ->default(0)
                ->helperText('Higher wins. Two active zones that could match the same destination at the same precedence are refused when the second is saved — ordering resolved at read time is ordering nobody can audit.'),
            Toggle::make('is_active')
                ->default(true),
            Repeater::make('territories')
                ->label('Territories')
                ->helperText('A zone is a set of destination predicates, never a radius: this module computes no distance and geocodes nothing.')
                ->minItems(1)
                ->default([['country_code' => null, 'subdivision_code' => null, 'postcode_prefix' => null]])
                ->schema([
                    TextInput::make('country_code')
                        ->label('Country')
                        ->required()
                        ->length(2)
                        ->helperText('ISO 3166-1 alpha-2.'),
                    TextInput::make('subdivision_code')
                        ->label('Subdivision')
                        ->maxLength(8),
                    TextInput::make('postcode_prefix')
                        ->label('Postcode prefix')
                        ->maxLength(12),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('precedence')->sortable(),
                TextColumn::make('territories_count')
                    ->counts('territories')
                    ->label('Territories'),
                TextColumn::make('rates_count')
                    ->counts('rates')
                    ->label('Rates')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => $state === 0 ? 'No rates configured' : (string) $state)
                    ->color(fn (int $state): string => $state === 0 ? 'danger' : 'success')
                    ->description(fn (int $state): ?string => $state === 0 ? 'A destination this zone covers has nothing priced in it. Add a rate.' : null),
                IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                Filter::make('unpriced')
                    ->label('Zones with no rate')
                    ->query(fn (Builder $query): Builder => $query->doesntHave('rates')),
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
            ->defaultSort('precedence', 'desc');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListZones::route('/'),
            'create' => CreateZone::route('/create'),
            'edit' => EditZone::route('/{record}/edit'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function definitionFrom(array $data): ZoneDefinition
    {
        $territories = [];

        foreach ($data['territories'] ?? [] as $territory) {
            $territories[] = new TerritoryDefinition(
                (string) ($territory['country_code'] ?? ''),
                self::nullIfBlank($territory['subdivision_code'] ?? null),
                self::nullIfBlank($territory['postcode_prefix'] ?? null),
            );
        }

        return new ZoneDefinition(
            (string) $data['code'],
            (string) $data['name'],
            (int) $data['precedence'],
            $territories,
            (bool) ($data['is_active'] ?? true),
        );
    }

    private static function nullIfBlank(int|string|null $value): ?string
    {
        return $value === null || trim((string) $value) === '' ? null : (string) $value;
    }
}
