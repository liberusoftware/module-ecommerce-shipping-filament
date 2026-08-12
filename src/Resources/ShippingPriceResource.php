<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Filament\Resources;

use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Liberu\Ecommerce\Shipping\Enums\PriceKind;
use Liberu\Ecommerce\Shipping\Enums\PriceStatus;
use Liberu\Ecommerce\Shipping\Filament\Resources\ShippingPriceResource\Pages\ListShippingPrices;
use Liberu\Ecommerce\Shipping\Filament\Resources\ShippingPriceResource\Pages\ViewShippingPrice;
use Liberu\Ecommerce\Shipping\Filament\Resources\ShippingPriceResource\RelationManagers\AdjustmentsRelationManager;
use Liberu\Ecommerce\Shipping\Filament\Support\MinorUnits;
use Liberu\Ecommerce\Shipping\Filament\Support\Tenant;
use Liberu\Ecommerce\Shipping\Models\ShippingPrice;
use Liberu\Ecommerce\Shipping\Queries\TotalShippingCharge;
use UnitEnum;

/**
 * A recorded price is evidence, and evidence is read-only.
 *
 * `isReadOnly()` is a `RelationManager` **instance** method: declaring it on a
 * `Resource` enforces nothing at all and is a comment. The enforcement here is
 * an override of {@see getAuthorizationResponse()}, the single funnel every
 * `can*()` and `authorize*()` on a resource passes through, so a permissive
 * gate — an unanswered ability, a `Gate::before` that says yes to everything —
 * cannot reopen a mutation.
 *
 * A quoted price is irreproducible: nothing this module records will ever let
 * it recompute one. That is why editing is not merely discouraged here.
 */
class ShippingPriceResource extends Resource
{
    protected static ?string $model = ShippingPrice::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static string|UnitEnum|null $navigationGroup = 'Shipping';

    protected static ?string $recordTitleAttribute = 'reference';

    protected static ?int $navigationSort = 50;

    /** Abilities that read. Everything else is denied by name, whatever the gate says. */
    public const READABLE = ['viewAny', 'view'];

    public static function isScopedToTenant(): bool
    {
        return false;
    }

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        if (! in_array(self::abilityName($action), self::READABLE, true)) {
            return Response::deny('A recorded shipping price is evidence: it is never edited, never adjusted in place and never pruned once selected.');
        }

        return parent::getAuthorizationResponse($action, $record);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tenant_id', Tenant::current());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')->searchable()->label('Reference'),
                TextColumn::make('kind')
                    ->badge()
                    ->color(fn (PriceKind $state): string => $state === PriceKind::Quoted ? 'warning' : 'info')
                    ->tooltip(fn (PriceKind $state): string => $state === PriceKind::Quoted
                        ? 'A third party answered at an instant. Irreproducible, so stored verbatim.'
                        : 'Computed from rules this module holds, and reproducible from them.'),
                TextColumn::make('status')->badge(),
                TextColumn::make('service_level_name')->label('Service'),
                TextColumn::make('amount_minor')
                    ->label('Amount')
                    ->formatStateUsing(fn (int $state, ShippingPrice $record): string => MinorUnits::format($state, $record->currency)),
                TextColumn::make('total')
                    ->label('Total charge')
                    ->state(fn (ShippingPrice $record): string => self::total($record))
                    ->description('Price line plus adjustment lines. There is no stored total.'),
                TextColumn::make('destination_country')->label('To'),
                TextColumn::make('expires_at')->dateTime(),
                TextColumn::make('selected_at')->dateTime()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('kind')->options([
                    PriceKind::Derived->value => 'Derived',
                    PriceKind::Quoted->value => 'Quoted',
                ]),
                SelectFilter::make('status')->options([
                    PriceStatus::Offered->value => 'Offered',
                    PriceStatus::Selected->value => 'Selected',
                ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('reference'),
            TextEntry::make('kind')->badge(),
            TextEntry::make('status')->badge(),
            TextEntry::make('provenance')
                ->label('Provenance')
                ->state(fn (ShippingPrice $record): string => self::provenance($record))
                ->helperText('Which rules, or which carrier at which instant. A quoted price depends on nothing this module can change its mind about.'),
            TextEntry::make('service_level_name')->label('Service'),
            TextEntry::make('amount_minor')
                ->label('Price line')
                ->state(fn (ShippingPrice $record): string => MinorUnits::format($record->amount_minor, $record->currency)),
            TextEntry::make('total')
                ->label('Total charge')
                ->state(fn (ShippingPrice $record): string => self::total($record)),
            TextEntry::make('estimate')
                ->label('Transit estimate')
                ->state(fn (ShippingPrice $record): string => $record->estimate()?->describe() ?? 'No estimate given')
                ->helperText('An integer transit-day range and its basis. This module never computes a delivery date: that needs a ship date, a cut-off and a holiday calendar it does not own.'),
            TextEntry::make('destination')
                ->label('Destination')
                ->state(fn (ShippingPrice $record): string => $record->destination()->describe()),
            TextEntry::make('parcels')
                ->label('Parcels')
                ->state(fn (ShippingPrice $record): string => self::parcels($record)),
            TextEntry::make('expires_at')->dateTime(),
            TextEntry::make('selected_at')->dateTime()->placeholder('Not selected'),
        ]);
    }

    /** @return array<int, class-string> */
    public static function getRelations(): array
    {
        return [AdjustmentsRelationManager::class];
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListShippingPrices::route('/'),
            'view' => ViewShippingPrice::route('/{record}'),
        ];
    }

    public static function total(ShippingPrice $record): string
    {
        $total = App::make(TotalShippingCharge::class)((string) $record->tenant_id, (string) $record->reference);

        return $total->currency.' '.$total->decimal();
    }

    public static function provenance(ShippingPrice $record): string
    {
        if ($record->kind === PriceKind::Quoted) {
            return 'carrier '.$record->carrier_code
                .', service '.$record->carrier_service_code
                .', quoted at '.($record->quoted_at?->toIso8601String() ?? 'an unrecorded instant');
        }

        return 'zone '.$record->zone_code
            .', rate '.$record->rate_id
            .', rule '.($record->applied_rule?->value ?? 'unrecorded');
    }

    private static function parcels(ShippingPrice $record): string
    {
        $lines = [];

        foreach ($record->parcels as $parcel) {
            $line = $parcel->weight_grams.' g';

            if ($parcel->length_mm !== null) {
                $line .= ' ('.$parcel->length_mm.'×'.$parcel->width_mm.'×'.$parcel->height_mm.' mm)';
            }

            $lines[] = $line;
        }

        return $lines === [] ? 'None recorded' : implode(', ', $lines);
    }

    private static function abilityName(string|UnitEnum $action): string
    {
        return match (true) {
            $action instanceof BackedEnum => (string) $action->value,
            $action instanceof UnitEnum => $action->name,
            default => $action,
        };
    }
}
