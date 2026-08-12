<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Filament\Resources;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Liberu\Ecommerce\Shipping\Filament\Resources\ServiceLevelResource\Pages\CreateServiceLevel;
use Liberu\Ecommerce\Shipping\Filament\Resources\ServiceLevelResource\Pages\EditServiceLevel;
use Liberu\Ecommerce\Shipping\Filament\Resources\ServiceLevelResource\Pages\ListServiceLevels;
use Liberu\Ecommerce\Shipping\Filament\Support\Tenant;
use Liberu\Ecommerce\Shipping\Models\ServiceLevel;
use UnitEnum;

/** What the host called a "shipping method". A rate prices one of these in one zone. */
class ServiceLevelResource extends Resource
{
    protected static ?string $model = ServiceLevel::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|UnitEnum|null $navigationGroup = 'Shipping';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 20;

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
            TextInput::make('code')->required()->maxLength(64),
            TextInput::make('name')->required()->maxLength(120),
            TextInput::make('description')->maxLength(255),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('rates_count')->counts('rates')->label('Rates'),
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
            ->defaultSort('code');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListServiceLevels::route('/'),
            'create' => CreateServiceLevel::route('/create'),
            'edit' => EditServiceLevel::route('/{record}/edit'),
        ];
    }
}
