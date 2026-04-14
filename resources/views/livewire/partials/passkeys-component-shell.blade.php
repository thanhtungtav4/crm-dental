@props([
    'viewState',
])

<x-filament::section
    :aside="true"
    :heading="$viewState['heading']"
    :description="$viewState['description']"
>
    <div x-data="{
        supported: false,
        checking: true,
        secureContext: false,
        hasWebAuthnApi: false,
        origin: '',
    }"
    x-init="
        checking = true;
        origin = window.location.origin;
        secureContext = window.isSecureContext === true;
        hasWebAuthnApi = window.PublicKeyCredential !== undefined
            && navigator.credentials !== undefined
            && navigator.credentials.create !== undefined;
        supported = secureContext && hasWebAuthnApi;
        checking = false;
    ">
        <div x-show="!checking && !supported" x-cloak>
            <x-filament::section>
                <div class="rounded-lg bg-warning-50 p-4 dark:bg-warning-900/20">
                    <div class="flex">
                        <div class="shrink-0">
                            <svg class="h-5 w-5 text-warning-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-warning-800 dark:text-warning-200">
                                {{ $viewState['unsupported_panel']['title'] }}
                            </h3>
                            <div class="mt-2 text-sm text-warning-700 dark:text-warning-300">
                                <p x-show="!secureContext">
                                    {{ $viewState['unsupported_panel']['insecure_context_message'] }} (<span class="font-semibold" x-text="origin"></span>).
                                    Passkey yêu cầu HTTPS hoặc localhost.
                                </p>
                                <p x-show="secureContext && !hasWebAuthnApi">
                                    {{ $viewState['unsupported_panel']['unsupported_api_message'] }}
                                </p>
                                <ul class="mt-2 list-inside list-disc space-y-1">
                                    @foreach ($viewState['unsupported_panel']['requirements'] as $requirement)
                                        <li>{{ $requirement }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </x-filament::section>
        </div>

        <div x-show="!checking && supported">
            @livewire(\MarcelWeidum\Passkeys\Livewire\Passkeys::class)
        </div>

        <div x-show="checking">
            <div class="flex items-center justify-center p-4">
                <x-filament::loading-indicator class="h-5 w-5" />
                <span class="ml-2 text-sm text-gray-500">{{ $viewState['checking_label'] }}</span>
            </div>
        </div>
    </div>
</x-filament::section>
