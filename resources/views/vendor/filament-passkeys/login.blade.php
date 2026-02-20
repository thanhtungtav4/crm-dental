<x-authenticate-passkey redirect="{{ filament()->getCurrentOrDefaultPanel()->getUrl() }}">
    <x-filament::button icon="heroicon-o-key" color="gray" class="w-full">
        {{ __('passkeys::passkeys.authenticate_using_passkey') }}
    </x-filament::button>
</x-authenticate-passkey>
