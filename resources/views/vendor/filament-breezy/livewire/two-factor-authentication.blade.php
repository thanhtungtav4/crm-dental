@php /** @var \Jeffgreco13\FilamentBreezy\Livewire\TwoFactorAuthentication $this */ @endphp
<x-filament::section class="breezy-2fa" :aside="true" :heading="__('filament-breezy::default.profile.2fa.title')" :description="__('filament-breezy::default.profile.2fa.description')">
    @if($this->showRequiresTwoFactorAlert())
        <div class="mb-2 rounded bg-danger-50 p-4 ring-1 ring-danger-100 dark:bg-danger-400/10 dark:ring-danger-500/70">
            <div class="flex">
                <div class="flex-shrink-0">
                    @svg('heroicon-s-shield-exclamation', 'h-5 w-5 text-danger-400')
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-danger-800 dark:text-white">
                        {{ __('filament-breezy::default.profile.2fa.must_enable') }}
                    </p>
                </div>
            </div>
        </div>
    @endif

    @unless ($user->hasEnabledTwoFactor())
        <h3 class="flex items-center gap-2 text-lg font-medium">
            @svg('heroicon-o-exclamation-circle', 'w-6')
            {{ __('filament-breezy::default.profile.2fa.not_enabled.title') }}
        </h3>
        <p class="text-sm">{{ __('filament-breezy::default.profile.2fa.not_enabled.description') }}</p>

        <div class="breezy-actions mt-3">
            {{ $this->enableAction }}
        </div>
    @else
        @if ($user->hasConfirmedTwoFactor())
            <h3 class="flex items-center gap-2 text-lg font-medium">
                @svg('heroicon-o-shield-check', 'w-6')
                {{ __('filament-breezy::default.profile.2fa.enabled.title') }}
            </h3>
            <p class="text-sm">{{ __('filament-breezy::default.profile.2fa.enabled.description') }}</p>
            @if ($showRecoveryCodes)
                <div class="px-4 space-y-3">
                    <p class="text-xs">{{ __('filament-breezy::default.profile.2fa.enabled.store_codes') }}</p>
                    <div class="breezy-2fa-codes">
                        @foreach ($this->recoveryCodes->toArray() as $code)
                            <span class="breezy-2fa-code">{{ $code }}</span>
                        @endforeach
                    </div>
                    <div class="inline-block text-xs">
                        <x-filament-breezy::clipboard-link :data="$this->recoveryCodes->join(',')" />
                    </div>
                </div>
            @endif
            <div class="breezy-actions mt-3">
                {{ $this->regenerateCodesAction }}
                {{ $this->disableAction }}
            </div>
        @else
            <h3 class="flex items-center gap-2 text-lg font-medium">
                @svg('heroicon-o-question-mark-circle', 'w-6')
                {{ __('filament-breezy::default.profile.2fa.finish_enabling.title') }}
            </h3>
            <p class="text-sm">{{ __('filament-breezy::default.profile.2fa.finish_enabling.description') }}</p>
            <div class="breezy-2fa-grid mt-3">
                <div class="breezy-2fa-qr">
                    {!! $this->getTwoFactorQrCode() !!}
                    <p class="pt-2 text-sm">
                        {{ __('filament-breezy::default.profile.2fa.setup_key') }}
                        {{ $this->two_factor_secret }}
                    </p>
                </div>
                <div class="px-4 space-y-3">
                    <p class="text-xs">{{ __('filament-breezy::default.profile.2fa.enabled.store_codes') }}</p>
                    <div class="breezy-2fa-codes">
                        @foreach ($this->recoveryCodes->toArray() as $code)
                            <span class="breezy-2fa-code">{{ $code }}</span>
                        @endforeach
                    </div>
                    <div class="inline-block text-xs">
                        <x-filament-breezy::clipboard-link :data="$this->recoveryCodes->join(',')" />
                    </div>
                </div>
            </div>

            <div class="breezy-actions mt-3">
                {{ $this->confirmAction }}
                {{ $this->disableAction }}
            </div>
        @endif
    @endunless

    <x-filament-actions::modals />
</x-filament::section>
