<x-filament::section 
    :aside="true" 
    heading="Passkeys" 
    description="Passkeys cho phép bạn đăng nhập mà không cần mật khẩu. Thay vì mật khẩu, bạn có thể tạo passkey sẽ được lưu trữ trong 1Password, ứng dụng mật khẩu của MacOS, hoặc các ứng dụng tương tự trên hệ điều hành của bạn."
>
    {{-- Check WebAuthn support --}}
    <div x-data="{ 
        supported: false,
        checking: true 
    }" 
    x-init="
        checking = true;
        supported = window.PublicKeyCredential !== undefined && 
                   navigator.credentials !== undefined &&
                   navigator.credentials.create !== undefined;
        checking = false;
    ">
        {{-- Error message if not supported --}}
        <div x-show="!checking && !supported" x-cloak>
            <x-filament::section>
                <div class="rounded-lg bg-warning-50 dark:bg-warning-900/20 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-warning-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-warning-800 dark:text-warning-200">
                                WebAuthn không được hỗ trợ
                            </h3>
                            <div class="mt-2 text-sm text-warning-700 dark:text-warning-300">
                                <p>Trình duyệt của bạn không hỗ trợ WebAuthn/Passkeys. Để sử dụng tính năng này, vui lòng:</p>
                                <ul class="list-disc list-inside mt-2 space-y-1">
                                    <li>Sử dụng trình duyệt hiện đại (Chrome 109+, Safari 16+, Firefox 119+)</li>
                                    <li>Truy cập qua HTTPS hoặc localhost</li>
                                    <li>Đảm bảo trình duyệt được cập nhật lên phiên bản mới nhất</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </x-filament::section>
        </div>

        {{-- Passkeys component if supported --}}
        <div x-show="!checking && supported">
            @livewire(\MarcelWeidum\Passkeys\Livewire\Passkeys::class)
        </div>

        {{-- Loading state --}}
        <div x-show="checking">
            <div class="flex items-center justify-center p-4">
                <x-filament::loading-indicator class="h-5 w-5" />
                <span class="ml-2 text-sm text-gray-500">Đang kiểm tra hỗ trợ WebAuthn...</span>
            </div>
        </div>
    </div>
</x-filament::section>
