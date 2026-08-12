<x-filament-panels::page>
    <form wire:submit="preview">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit">
                Preview rates
            </x-filament::button>
        </div>
    </form>

    <p class="fi-ta-text-item-label text-sm">
        A preview records nothing. It runs the same quote a shopper surface would and rolls it back, so no
        offered price is left behind.
    </p>

    @if ($failure)
        <x-filament::section>
            <x-slot name="heading">
                <h2>The preview was refused</h2>
            </x-slot>

            <p>{{ $failure }}</p>
        </x-filament::section>
    @endif

    @if ($options)
        <x-filament::section>
            <x-slot name="heading">
                <h2>{{ $quoteCopy['heading'] }}</h2>
            </x-slot>

            <x-filament::badge :color="$quoteCopy['tone']">
                {{ $options->outcome->value }}
            </x-filament::badge>

            <p class="mt-2">{{ $quoteCopy['body'] }}</p>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                <h2>{{ $carrierCopy['heading'] }}</h2>
            </x-slot>

            <x-filament::badge :color="$carrierCopy['tone']">
                {{ $options->carrierOutcome->code() }}
            </x-filament::badge>

            <p class="mt-2">{{ $carrierCopy['body'] }}</p>
        </x-filament::section>

        @if (count($options->available) > 0)
            <x-filament::section>
                <x-slot name="heading">
                    <h2>Available options</h2>
                </x-slot>

                <table class="w-full text-start">
                    <caption class="sr-only">Shipping options this destination would be offered</caption>
                    <thead>
                        <tr>
                            <th scope="col" class="text-start">Service</th>
                            <th scope="col" class="text-start">Amount</th>
                            <th scope="col" class="text-start">Transit</th>
                            <th scope="col" class="text-start">Kind</th>
                            <th scope="col" class="text-start">Rule</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($options->available as $option)
                            <tr>
                                <th scope="row" class="text-start font-normal">{{ $option->serviceLevelName }}</th>
                                <td>{{ $option->amount->currency }} {{ $option->amount->decimal() }}</td>
                                <td>{{ $option->estimate?->describe() ?? 'No estimate given' }}</td>
                                <td>{{ $option->kind->value }}</td>
                                <td>{{ $option->appliedRule->value }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-filament::section>
        @endif

        @if (count($options->excluded) > 0)
            <x-filament::section>
                <x-slot name="heading">
                    <h2>Excluded by a restriction</h2>
                </x-slot>

                <p>A restriction refuses with a recorded reason; it never silently filters. Each reason below is what a
                    buyer is shown in place of the service level.</p>

                <ul>
                    @foreach ($options->excluded as $excluded)
                        <li>
                            <strong>{{ $excluded->serviceLevelName }}</strong>
                            — {{ $excluded->reason }}
                            <span>({{ $excluded->restrictionType->value }}, {{ $excluded->reasonCode }})</span>
                        </li>
                    @endforeach
                </ul>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
