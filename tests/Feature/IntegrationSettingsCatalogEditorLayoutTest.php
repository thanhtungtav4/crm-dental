<?php

use App\Filament\Pages\IntegrationSettings;
use Illuminate\Support\Facades\File;

it('renders user-friendly catalog editor controls instead of raw json editing', function (): void {
    $blade = File::get(resource_path('views/filament/pages/integration-settings.blade.php'));

    expect($blade)
        ->toContain("wire:click=\"addCatalogRow('{{ \$field['state'] }}')\"")
        ->toContain("wire:click=\"removeCatalogRow('{{ \$field['state'] }}', {{ \$index }})\"")
        ->toContain("wire:click=\"restoreCatalogDefaults('{{ \$field['state'] }}')\"")
        ->toContain("wire:model.live=\"catalogEditors.{{ \$field['state'] }}.{{ \$index }}.enabled\"")
        ->toContain("wire:model.live=\"catalogEditors.{{ \$field['state'] }}.{{ \$index }}.key\"")
        ->toContain("wire:model.live=\"catalogEditors.{{ \$field['state'] }}.{{ \$index }}.label\"")
        ->toContain("wire:blur=\"syncCatalogRowFromLabel('{{ \$field['state'] }}', {{ \$index }})\"")
        ->toContain('Không cần sửa JSON thủ công')
        ->toContain('Thêm dòng')
        ->toContain('Khôi phục mặc định')
        ->toContain('Mã tự sinh')
        ->toContain('Bật')
        ->toContain('Nhãn hiển thị')
        ->toContain('readonly')
        ->not->toContain('catalog_exam_indication_ext_enabled')
        ->not->toContain('catalog_exam_indication_int_enabled')
        ->not->toContain('toggle ở trên chỉ dùng cho upload ảnh <code>ext/int</code>');
});

it('uses readable catalog provider description in integration settings', function (): void {
    $page = app(IntegrationSettings::class);
    $providers = collect($page->getProviders());
    $catalog = $providers->firstWhere('group', 'catalog');

    expect($catalog)->not->toBeNull()
        ->and((string) ($catalog['description'] ?? ''))->toContain('Mã → Nhãn')
        ->and((string) ($catalog['description'] ?? ''))->not->toContain('\\"key\\"');
});
