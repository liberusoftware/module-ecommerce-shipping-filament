<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Filament\Resources;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
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
use Liberu\Ecommerce\Shipping\Data\BandDefinition;
use Liberu\Ecommerce\Shipping\Data\RateDefinition;
use Liberu\Ecommerce\Shipping\Data\TransitEstimate;
use Liberu\Ecommerce\Shipping\Enums\BandAxis;
use Liberu\Ecommerce\Shipping\Enums\RateType;
use Liberu\Ecommerce\Shipping\Enums\TransitBasis;
use Liberu\Ecommerce\Shipping\Filament\Resources\RateResource\Pages\CreateRate;
use Liberu\Ecommerce\Shipping\Filament\Resources\RateResource\Pages\EditRate;
use Liberu\Ecommerce\Shipping\Filament\Resources\RateResource\Pages\ListRates;
use Liberu\Ecommerce\Shipping\Filament\Support\MinorUnits;
use Liberu\Ecommerce\Shipping\Filament\Support\Tenant;
use Liberu\Ecommerce\Shipping\Models\Rate;
use Liberu\Ecommerce\Shipping\Models\ServiceLevel;
use Liberu\Ecommerce\Shipping\Models\Zone;
use UnitEnum;

/**
 * Rates and their bands are authored here, so the band-tiling refusal is this
 * package's to surface as form validation rather than a 500.
 */
class RateResource extends Resource
{
    protected static ?string $model = Rate::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'Shipping';

    protected static ?int $navigationSort = 30;

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
            Select::make('zone_id')
                ->label('Zone')
                ->required()
                ->options(fn (): array => Zone::query()->where('tenant_id', Tenant::current())->pluck('name', 'id')->all()),
            Select::make('service_level_id')
                ->label('Service level')
                ->required()
                ->options(fn (): array => ServiceLevel::query()->where('tenant_id', Tenant::current())->pluck('name', 'id')->all()),
            Select::make('rate_type')
                ->required()
                ->live()
                ->default(RateType::Flat->value)
                ->options([
                    RateType::Flat->value => 'Flat',
                    RateType::Table->value => 'Table (banded)',
                ]),
            TextInput::make('currency')
                ->required()
                ->length(3)
                ->default('GBP')
                ->helperText('ISO 4217. Amounts below are decimal strings and are stored as integer minor units.'),
            TextInput::make('amount')
                ->label('Flat amount')
                ->helperText('For example 4.95. Never a float: the conversion to minor units is string arithmetic.')
                ->visible(fn (callable $get): bool => $get('rate_type') === RateType::Flat->value),
            Select::make('band_axis')
                ->label('Band axis')
                ->options([
                    BandAxis::WeightGrams->value => 'Weight (grams)',
                    BandAxis::SubtotalMinor->value => 'Subtotal (minor units)',
                    BandAxis::ItemCount->value => 'Item count',
                ])
                ->visible(fn (callable $get): bool => $get('rate_type') === RateType::Table->value),
            Repeater::make('bands')
                ->label('Bands')
                ->helperText('Half-open [lower, upper). The set must tile its axis from zero with exactly one explicitly unbounded top band. A gap, an overlap or a missing top band is refused when you save.')
                ->visible(fn (callable $get): bool => $get('rate_type') === RateType::Table->value)
                ->schema([
                    TextInput::make('lower_bound')->integer()->required()->default(0),
                    TextInput::make('upper_bound')->integer(),
                    Toggle::make('is_unbounded')->label('Unbounded top band'),
                    TextInput::make('amount')->required(),
                ]),
            TextInput::make('free_above_subtotal')
                ->label('Free above subtotal')
                ->helperText('Free shipping above an order subtotal is a rate rule and lives here. Free shipping from a coupon is ecommerce-promotions and does not.'),
            TextInput::make('transit_min_days')->integer()->required()->default(1),
            TextInput::make('transit_max_days')->integer()->required()->default(3),
            Select::make('transit_basis')
                ->required()
                ->default(TransitBasis::BusinessDays->value)
                ->options([
                    TransitBasis::BusinessDays->value => 'Business days',
                    TransitBasis::CalendarDays->value => 'Calendar days',
                ])
                ->helperText('An estimate is an integer transit-day range plus its basis. This module never computes a delivery date.'),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('zone.code')->label('Zone')->sortable(),
                TextColumn::make('serviceLevel.code')->label('Service level')->sortable(),
                TextColumn::make('rate_type')->badge(),
                TextColumn::make('amount_minor')
                    ->label('Amount')
                    ->formatStateUsing(fn (?int $state, Rate $record): string => MinorUnits::format($state, $record->currency)),
                TextColumn::make('bands_count')->counts('bands')->label('Bands'),
                TextColumn::make('free_above_subtotal_minor')
                    ->label('Free above')
                    ->formatStateUsing(fn (?int $state, Rate $record): string => MinorUnits::format($state, $record->currency)),
                TextColumn::make('transit')
                    ->label('Transit')
                    ->state(fn (Rate $record): string => $record->estimate()->describe()),
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
            'index' => ListRates::route('/'),
            'create' => CreateRate::route('/create'),
            'edit' => EditRate::route('/{record}/edit'),
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function definitionFrom(array $data): RateDefinition
    {
        $currency = strtoupper((string) $data['currency']);
        $rateType = RateType::from((string) $data['rate_type']);
        $bands = [];

        if ($rateType === RateType::Table) {
            foreach ($data['bands'] ?? [] as $band) {
                $unbounded = (bool) ($band['is_unbounded'] ?? false);

                $bands[] = new BandDefinition(
                    (int) $band['lower_bound'],
                    $unbounded ? null : self::intOrNull($band['upper_bound'] ?? null),
                    (int) MinorUnits::toMinor($band['amount'] ?? null, $currency),
                    $unbounded,
                );
            }
        }

        $axis = $data['band_axis'] ?? null;

        return new RateDefinition(
            zoneId: (int) $data['zone_id'],
            serviceLevelId: (int) $data['service_level_id'],
            rateType: $rateType,
            currency: $currency,
            estimate: new TransitEstimate(
                (int) $data['transit_min_days'],
                (int) $data['transit_max_days'],
                TransitBasis::from((string) $data['transit_basis']),
            ),
            amountMinor: $rateType === RateType::Flat ? MinorUnits::toMinor($data['amount'] ?? null, $currency) : null,
            bandAxis: $rateType === RateType::Table && is_string($axis) && $axis !== '' ? BandAxis::from($axis) : null,
            bands: $bands,
            freeAboveSubtotalMinor: MinorUnits::toMinor($data['free_above_subtotal'] ?? null, $currency),
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
