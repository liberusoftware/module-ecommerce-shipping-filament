<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Liberu\Ecommerce\Shipping\Filament\Policies\AuthoringPolicy;
use Liberu\Ecommerce\Shipping\Filament\Policies\ReadOnlyPolicy;
use Liberu\Ecommerce\Shipping\Filament\ShippingFilamentServiceProvider;
use Liberu\Ecommerce\Shipping\Models\Zone;
use Liberu\PackageTestbench\TestUser;

/**
 * Every ability a Filament resource or relation manager asks about. A policy
 * that exists but lacks one of these is *exposed*, not safe: Filament's
 * `get_authorization_response()` returns allow in exactly that case.
 */
const SHIPPING_ABILITIES = [
    'viewAny', 'view', 'create', 'update', 'delete', 'deleteAny',
    'restore', 'restoreAny', 'forceDelete', 'forceDeleteAny',
    'replicate', 'reorder',
    'associate', 'attach', 'detach', 'detachAny', 'dissociate', 'dissociateAny',
];

it('maps every shipping model to a policy, because discovery looks in App\\Policies and a package cannot live there', function (): void {
    foreach (ShippingFilamentServiceProvider::POLICIES as $model => $policy) {
        expect(Gate::getPolicyFor($model))->toBeInstanceOf($policy);
    }
});

it('answers every ability by name on every policy', function (): void {
    foreach (ShippingFilamentServiceProvider::POLICIES as $model => $policy) {
        foreach (SHIPPING_ABILITIES as $ability) {
            expect(method_exists($policy, $ability))->toBeTrue("[{$policy}] does not answer [{$ability}] for [{$model}].");
        }
    }
});

it('denies association and dissociation by default, which a hasMany leaves open', function (): void {
    $user = TestUser::factory()->create();

    foreach (ShippingFilamentServiceProvider::POLICIES as $policy) {
        $instance = new $policy();

        expect($instance->associate($user))->toBeFalse()
            ->and($instance->attach($user))->toBeFalse()
            ->and($instance->detachAny($user))->toBeFalse()
            ->and($instance->dissociateAny($user))->toBeFalse();
    }
});

it('keeps the read-only policies read-only and lets the authoring ones author', function (): void {
    $user = TestUser::factory()->create();

    foreach (ShippingFilamentServiceProvider::POLICIES as $policy) {
        $instance = new $policy();
        $authors = is_subclass_of($policy, AuthoringPolicy::class);

        expect($instance->viewAny($user))->toBeTrue()
            ->and($instance->create($user))->toBe($authors)
            ->and($instance->deleteAny($user))->toBe($authors)
            ->and($instance->reorder($user))->toBeFalse()
            ->and($instance->restoreAny($user))->toBeFalse()
            ->and($instance->forceDeleteAny($user))->toBeFalse();
    }
});

it('denies the record-bearing mutations too, not just the ones without a record', function (): void {
    $user = TestUser::factory()->create();
    $record = new Zone();

    foreach (ShippingFilamentServiceProvider::POLICIES as $policy) {
        $instance = new $policy();
        $authors = is_subclass_of($policy, AuthoringPolicy::class);

        expect($instance->view($user, $record))->toBeTrue()
            ->and($instance->update($user, $record))->toBe($authors)
            ->and($instance->delete($user, $record))->toBe($authors)
            ->and($instance->restore($user, $record))->toBeFalse()
            ->and($instance->forceDelete($user, $record))->toBeFalse()
            ->and($instance->replicate($user, $record))->toBeFalse()
            ->and($instance->detach($user, $record))->toBeFalse()
            ->and($instance->dissociate($user, $record))->toBeFalse();
    }
});

it('bases every policy on the enumerated one, so a new model cannot arrive unanswered', function (): void {
    foreach (ShippingFilamentServiceProvider::POLICIES as $policy) {
        expect(is_subclass_of($policy, ReadOnlyPolicy::class))->toBeTrue();
    }
});
