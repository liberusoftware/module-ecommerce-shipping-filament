<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Filament\Resources\ShippingPriceResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Liberu\Ecommerce\Shipping\Filament\Support\MinorUnits;
use Liberu\Ecommerce\Shipping\Models\PriceAdjustment;

/**
 * A surcharge is its own recorded line with its own reason, and the charge is a
 * fold over these rows. Nothing here is editable.
 *
 * `isReadOnly()` is honoured on a relation manager — this is the class where
 * declaring it means something — but it only covers the actions Filament builds
 * for you. `associate` and `dissociate` are live on a `hasMany` and default
 * open, so the ability funnel is closed as well, and `PriceAdjustmentPolicy`
 * answers every ability by name underneath both.
 */
class AdjustmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'adjustments';

    protected static ?string $title = 'Adjustments';

    /** Abilities that read. Everything else is denied by name, whatever the gate says. */
    public const READABLE = ['viewAny', 'view'];

    public function isReadOnly(): bool
    {
        return true;
    }

    public function getAuthorizationResponse(string $action, ?Model $record = null): Response
    {
        if (! in_array($action, self::READABLE, true)) {
            return Response::deny('An adjustment line is evidence: it is recorded once, with its reason, and never rewritten.');
        }

        return parent::getAuthorizationResponse($action, $record);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reason_code')->label('Reason code'),
                TextColumn::make('reason')->wrap(),
                TextColumn::make('amount_minor')
                    ->label('Amount')
                    ->formatStateUsing(fn (int $state, PriceAdjustment $record): string => MinorUnits::format($state, $record->currency)),
                TextColumn::make('basis_points')
                    ->label('Basis points')
                    ->placeholder('—')
                    ->description('A percentage is integer basis points: intdiv(base × bps, 10000).'),
                TextColumn::make('created_at')->dateTime(),
            ])
            ->defaultSort('id');
    }
}
