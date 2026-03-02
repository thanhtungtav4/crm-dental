<x-filament::page>
    @if (request()->boolean('mfa_required'))
        <div class="mb-4 rounded-xl border border-warning-300/80 bg-warning-50 p-4 text-warning-900 dark:border-warning-500/60 dark:bg-warning-500/10 dark:text-warning-100">
            <h3 class="text-sm font-semibold">
                {{ __('filament-breezy::default.profile.mfa_required_notice.title') }}
            </h3>

            <p class="mt-2 text-sm">
                {{ __('filament-breezy::default.profile.mfa_required_notice.description') }}
            </p>

            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                <li>{{ __('filament-breezy::default.profile.mfa_required_notice.step_2fa') }}</li>
                <li>{{ __('filament-breezy::default.profile.mfa_required_notice.step_passkey') }}</li>
                <li>{{ __('filament-breezy::default.profile.mfa_required_notice.done') }}</li>
            </ul>
        </div>
    @endif

    <div class="divide-y divide-gray-900/10 dark:divide-white/10 [&>*:not(:first-child)]:pt-6 [&>*:not(:last-child)]:pb-6">
        @foreach ($this->getRegisteredMyProfileComponents() as $component)
            @unless(is_null($component))
                @livewire($component)
            @endunless
        @endforeach
    </div>
</x-filament::page>

