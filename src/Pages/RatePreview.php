<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Liberu\Ecommerce\Shipping\Actions\QuoteShippingOptions;
use Liberu\Ecommerce\Shipping\Data\Destination;
use Liberu\Ecommerce\Shipping\Data\Parcel;
use Liberu\Ecommerce\Shipping\Data\ParcelSet;
use Liberu\Ecommerce\Shipping\Data\ShippingOptions;
use Liberu\Ecommerce\Shipping\Exceptions\ShippingException;
use Liberu\Ecommerce\Shipping\Filament\Support\MinorUnits;
use Liberu\Ecommerce\Shipping\Filament\Support\OutcomeCopy;
use Liberu\Ecommerce\Shipping\Filament\Support\Tenant;
use UnitEnum;

/**
 * Answers "what would this destination be offered, and why", and records nothing.
 *
 * Quoting is a write — an offered price row per option, with its parcels — so
 * the whole call runs inside a transaction that is deliberately aborted. An
 * operator checking their own rules must not leave priced offers behind for a
 * sweeper to tidy up.
 *
 * The weight typed here is a diagnostic, not a shopper input. A shopper surface
 * is told its parcels through `ResolvesParcels` and never names a weight; this
 * page has no shopper on it, and the operator using it authored the rates
 * anyway.
 *
 * @property-read Schema $form
 */
class RatePreview extends Page
{
    protected string $view = 'ecommerce-shipping-filament::pages.rate-preview';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-magnifying-glass';

    protected static string|UnitEnum|null $navigationGroup = 'Shipping';

    protected static ?string $title = 'Rate preview';

    protected static ?int $navigationSort = 60;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    protected ?ShippingOptions $options = null;

    protected ?string $failure = null;

    public function mount(): void
    {
        $this->form->fill([
            'country_code' => 'GB',
            'currency' => 'GBP',
            'weight_grams' => 1000,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('country_code')
                    ->label('Country')
                    ->required()
                    ->length(2),
                TextInput::make('subdivision_code')->label('Subdivision')->maxLength(8),
                TextInput::make('postcode')->label('Postcode')->maxLength(16),
                TextInput::make('currency')->label('Currency')->required()->length(3),
                TextInput::make('weight_grams')
                    ->label('Parcel weight (grams)')
                    ->integer()
                    ->required()
                    ->helperText('Integer grams. There is no unit selector anywhere in this module: three disagreeing units is what the host had.'),
                TextInput::make('length_mm')->label('Length (mm)')->integer(),
                TextInput::make('width_mm')->label('Width (mm)')->integer(),
                TextInput::make('height_mm')->label('Height (mm)')->integer(),
                TextInput::make('subtotal')->label('Order subtotal')->helperText('A decimal string, for example 45.00. Needed by a free-shipping threshold or a subtotal-banded rate.'),
                TextInput::make('item_count')->label('Item count')->integer(),
            ])
            ->statePath('data');
    }

    public function preview(): void
    {
        $this->options = null;
        $this->failure = null;

        $data = $this->form->getState();
        $currency = strtoupper((string) $data['currency']);

        try {
            $destination = new Destination(
                (string) $data['country_code'],
                self::stringOrNull($data['subdivision_code'] ?? null),
                self::stringOrNull($data['postcode'] ?? null),
            );

            $parcels = new ParcelSet(new Parcel(
                (int) $data['weight_grams'],
                self::intOrNull($data['length_mm'] ?? null),
                self::intOrNull($data['width_mm'] ?? null),
                self::intOrNull($data['height_mm'] ?? null),
            ));

            $this->options = $this->quoteWithoutRecording($destination, $parcels, $currency, $data);
        } catch (ShippingException $refusal) {
            $this->failure = $refusal->getMessage();
        }
    }

    /** @param  array<string, mixed>  $data */
    protected function quoteWithoutRecording(Destination $destination, ParcelSet $parcels, string $currency, array $data): ?ShippingOptions
    {
        $options = null;

        // A long closure with `use (&$options)`: an arrow function captures by
        // value at definition, so it would hand back the null it started with.
        try {
            DB::transaction(function () use (&$options, $destination, $parcels, $currency, $data): void {
                $options = App::make(QuoteShippingOptions::class)(
                    Tenant::current(),
                    $destination,
                    $parcels,
                    $currency,
                    MinorUnits::toMinor($data['subtotal'] ?? null, $currency),
                    self::intOrNull($data['item_count'] ?? null),
                );

                throw new PreviewIsNotAQuote();
            });
        } catch (PreviewIsNotAQuote) {
            // Rolled back on purpose. A preview is a question, not an offer.
        }

        return $options;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            'options' => $this->options,
            'failure' => $this->failure,
            'quoteCopy' => $this->options instanceof ShippingOptions
                ? OutcomeCopy::forQuote($this->options->outcome, $this->options->zoneCode)
                : null,
            'carrierCopy' => $this->options instanceof ShippingOptions
                ? OutcomeCopy::forCarrier($this->options->carrierOutcome)
                : null,
        ];
    }

    private static function stringOrNull(int|string|null $value): ?string
    {
        return $value === null || trim((string) $value) === '' ? null : (string) $value;
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
