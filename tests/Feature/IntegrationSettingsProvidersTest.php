<?php

use App\Filament\Pages\IntegrationSettings;
use App\Models\ClinicSetting;
use App\Models\ClinicSettingLog;
use App\Models\User;
use App\Services\IntegrationSettingsAuditReadModelService;
use App\Support\ClinicRuntimeSettings;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

it('does not expose vnpay provider or fields in integration settings', function () {
    $page = app(IntegrationSettings::class);
    $providers = collect($page->getProviders());
    $fieldKeys = $providers
        ->flatMap(fn (array $provider) => collect($provider['fields'] ?? [])->pluck('key'))
        ->values();

    expect($providers->pluck('group'))
        ->not->toContain('vnpay')
        ->and($fieldKeys->contains(fn ($key) => str_starts_with((string) $key, 'vnpay.')))
        ->toBeFalse()
        ->and($page->getSubheading())
        ->not->toContain('VNPay');
});

it('loads integration settings state without colliding with livewire hydrate hooks', function () {
    $page = app(IntegrationSettings::class);

    $page->mount();

    expect(method_exists($page, 'hydrateSettings'))->toBeFalse()
        ->and($page->settings)->toBeArray()
        ->and($page->settings)->not->toBeEmpty();
});

it('shows web lead api integration guide in settings page', function () {
    actingAsIntegrationSettingsProvidersAdmin();

    $blade = File::get(resource_path('views/filament/pages/integration-settings.blade.php'));
    $guidePartial = File::get(resource_path('views/filament/pages/partials/web-lead-api-guide.blade.php'));
    $actionButtonsPartial = File::get(resource_path('views/filament/pages/partials/provider-action-buttons.blade.php'));
    $inputFieldPartial = File::get(resource_path('views/filament/pages/partials/integration-setting-input-field.blade.php'));
    $selectFieldPartial = File::get(resource_path('views/filament/pages/partials/integration-setting-select-field.blade.php'));
    $revisionNoticePartial = File::get(resource_path('views/filament/pages/partials/integration-settings-revision-notice.blade.php'));
    $submitBarPartial = File::get(resource_path('views/filament/pages/partials/integration-settings-submit-bar.blade.php'));
    $pageShellPartial = File::get(resource_path('views/filament/pages/partials/integration-settings-page-shell.blade.php'));
    $sectionListPartial = File::get(resource_path('views/filament/pages/partials/control-plane-section-list.blade.php'));
    $partialListPartial = File::get(resource_path('views/filament/pages/partials/control-plane-partial-list.blade.php'));
    $page = app(IntegrationSettings::class);
    $page->mount();
    $providerPanels = collect(integrationSettingsProviderPanels($page));
    $webLeadPanel = $providerPanels->firstWhere('group', 'web_lead');

    expect($blade)
        ->not->toContain('@php')
        ->toContain("@include('filament.pages.partials.integration-settings-page-shell', [")
        ->toContain("'viewState' => \$this->pageViewState,")
        ->and($pageShellPartial)
        ->toContain("@foreach(\$viewState['form_panel']['notice_panels'] as \$notice)")
        ->toContain("'sections' => \$viewState['form_panel']['pre_sections']")
        ->toContain("'notice' => \$viewState['form_panel']['revision_conflict_notice']")
        ->toContain("'sections' => \$viewState['form_panel']['provider_sections']")
        ->toContain("'action' => \$viewState['form_panel']['submit_action']")
        ->toContain("'sections' => \$viewState['post_form_sections']")
        ->and($sectionListPartial)
        ->toContain('@foreach($sections as $section)')
        ->toContain("@include('filament.pages.partials.control-plane-section', ['section' => \$section])")
        ->and($guidePartial)
        ->toContain('Hướng dẫn tích hợp Web Lead API')
        ->toContain("route('api.v1.web-leads.store')")
        ->toContain('X-Idempotency-Key')
        ->toContain('Payload tối thiểu')
        ->toContain('curl -X POST')
        ->and($actionButtonsPartial)
        ->toContain("wire:click=\"{{ \$action['wire_click'] }}\"")
        ->toContain("{{ \$action['label'] }}")
        ->and($inputFieldPartial)
        ->toContain('showWebLeadToken')
        ->toContain('x-ref="webLeadApiTokenInput"')
        ->toContain("x-bind:type=\"showWebLeadToken ? 'text' : 'password'\"")
        ->toContain('x-on:click="navigator.clipboard?.writeText($refs.webLeadApiTokenInput?.value ?? \'\')"')
        ->and($selectFieldPartial)
        ->toContain('<x-filament::input.select wire:model.blur="{{ $statePath }}">')
        ->and(File::get(resource_path('views/filament/pages/partials/integration-settings-provider-panel.blade.php')))
        ->not->toContain('<x-filament::section')
        ->toContain("@foreach(\$provider['rendered_fields'] as \$renderedField)")
        ->toContain("@include(\$renderedField['partial'], [")
        ->toContain("@include('filament.pages.partials.control-plane-partial-list', [")
        ->toContain("'items' => \$provider['support_sections']")
        ->and($partialListPartial)
        ->toContain('@foreach($items as $item)')
        ->toContain("@include(\$item['partial'], \$item['include_data'] ?? [])")
        ->and($revisionNoticePartial)
        ->toContain("@if(\$notice['is_visible'])")
        ->toContain("{{ \$notice['message'] }}")
        ->and($submitBarPartial)
        ->toContain("@if(\$action['is_visible'])")
        ->toContain("{{ \$action['label'] }}")
        ->and(collect($webLeadPanel['support_sections'][0]['include_data']['actions'])->contains(
            fn (array $action): bool => $action['wire_click'] === 'generateWebLeadApiToken'
                && $action['label'] === 'Tạo API Token',
        ))->toBeTrue();
});

it('maps provider field types to shared blade partials', function (): void {
    actingAsIntegrationSettingsProvidersAdmin();

    $page = app(IntegrationSettings::class);
    $page->mount();
    $providerPanels = collect(integrationSettingsProviderPanels($page));
    $providerFieldPartials = $providerPanels
        ->flatMap(fn (array $provider) => collect($provider['rendered_fields'] ?? []))
        ->pluck('partial', 'field.type');

    expect($providerFieldPartials['boolean'])
        ->toBe('filament.pages.partials.integration-setting-boolean-field')
        ->and($providerFieldPartials['select'])
        ->toBe('filament.pages.partials.integration-setting-select-field')
        ->and($providerFieldPartials['roles'])
        ->toBe('filament.pages.partials.integration-setting-roles-field')
        ->and($providerFieldPartials['textarea'])
        ->toBe('filament.pages.partials.integration-setting-textarea-field')
        ->and($providerFieldPartials['json'])
        ->toBe('filament.pages.partials.integration-setting-json-field')
        ->and($providerFieldPartials['text'])
        ->toBe('filament.pages.partials.integration-setting-input-field');
});

it('builds provider panels from provider definitions and support state', function (): void {
    actingAsIntegrationSettingsProvidersAdmin();

    $page = app(IntegrationSettings::class);
    $page->mount();

    $providerPanels = collect(integrationSettingsProviderPanels($page));
    $webLeadPanel = $providerPanels->firstWhere('group', 'web_lead');
    $popupPanel = $providerPanels->firstWhere('group', 'popup');
    $webLeadRenderedField = collect($webLeadPanel['rendered_fields'] ?? [])->firstWhere('field.state', 'web_lead_api_token');

    expect($webLeadPanel)->not->toBeNull()
        ->and($webLeadPanel)->toMatchArray([
            'group' => 'web_lead',
            'title' => 'Web Lead API',
        ])
        ->and($webLeadRenderedField)->not->toBeNull()
        ->and($webLeadRenderedField['state_path'])->toBe('settings.web_lead_api_token')
        ->and($webLeadRenderedField['partial'])->toBe('filament.pages.partials.integration-setting-input-field')
        ->and($webLeadPanel['support_sections'])->toHaveCount(2)
        ->and($webLeadPanel['support_sections'][0]['partial'])->toBe('filament.pages.partials.provider-action-buttons')
        ->and($webLeadPanel['support_sections'][1]['partial'])->toBe('filament.pages.partials.web-lead-api-guide')
        ->and($popupPanel)->not->toBeNull()
        ->and($popupPanel['support_sections'])->toHaveCount(1)
        ->and($popupPanel['support_sections'][0]['partial'])->toBe('filament.pages.partials.popup-announcement-guide');
});

it('builds provider sections from provider panels', function (): void {
    actingAsIntegrationSettingsProvidersAdmin();

    $page = app(IntegrationSettings::class);
    $page->mount();
    $providerPanels = integrationSettingsProviderPanels($page);
    $providerSections = $page->pageViewState()['form_panel']['provider_sections'];

    expect($providerSections)->toHaveCount(count($providerPanels))
        ->and($providerSections[0])->toHaveKeys([
            'heading',
            'description',
            'partial',
            'include_data',
        ])
        ->and($providerSections[0]['partial'])->toBe('filament.pages.partials.integration-settings-provider-panel')
        ->and($providerSections[0]['include_data']['provider'])->toBe($providerPanels[0]);
});

it('builds integration settings page view state from notices, control plane, and providers', function (): void {
    actingAsIntegrationSettingsProvidersAdmin();

    $page = app(IntegrationSettings::class);
    $page->mount();

    expect($page->pageViewState())->toHaveKeys([
        'form_panel',
        'post_form_sections',
    ])
        ->and($page->pageViewState()['form_panel'])->toHaveKeys([
            'notice_panels',
            'revision_conflict_notice',
            'pre_sections',
            'provider_sections',
            'submit_action',
        ])
        ->and($page->pageViewState()['form_panel']['provider_sections'])->toHaveCount(count(integrationSettingsProviderPanels($page)))
        ->and($page->pageViewState()['form_panel']['revision_conflict_notice'])->toMatchArray([
            'is_visible' => false,
        ])
        ->and($page->pageViewState()['form_panel']['pre_sections'])->toHaveCount(1)
        ->and($page->pageViewState()['form_panel']['submit_action'])->toMatchArray([
            'is_visible' => true,
            'label' => 'Lưu cài đặt tích hợp',
            'icon' => 'heroicon-o-check-circle',
        ])
        ->and($page->pageViewState()['post_form_sections'])->toHaveCount(1);
});

it('renders concrete input html for integration text fields', function () {
    actingAsIntegrationSettingsProvidersAdmin();

    $html = Livewire::test(IntegrationSettings::class)->html();

    expect($html)
        ->not->toContain('<x-filament::input')
        ->toContain('Provider health snapshot')
        ->toContain('Runtime disabled')
        ->toContain('DICOM / PACS')
        ->toContain('Web Lead API')
        ->toContain('wire:click="testDicomReadiness"')
        ->toContain('wire:click="testWebLeadReadiness"')
        ->toContain('class="fi-input"')
        ->toContain('wire:model.blur="settings.google_calendar_client_id"')
        ->toContain('wire:model.blur="settings.emr_provider"')
        ->toContain('wire:model.blur="settings.branding_clinic_name"')
        ->toContain('wire:model.blur="settings.branding_logo_url"');
});

it('renders provider health snapshot from shared presentation payload', function (): void {
    $blade = File::get(resource_path('views/filament/pages/integration-settings.blade.php'));
    $panelPartial = File::get(resource_path('views/filament/pages/partials/provider-health-panel.blade.php'));
    $partial = File::get(resource_path('views/filament/pages/partials/provider-health-card.blade.php'));

    expect($blade)
        ->not->toContain('@php')
        ->toContain("@include('filament.pages.partials.integration-settings-page-shell', [")
        ->toContain("'viewState' => \$this->pageViewState,")
        ->not->toContain('$toneClasses = match')
        ->not->toContain("{{ \$provider['status'] }}")
        ->not->toContain("{{ \$provider['issues'][0] }}")
        ->and($panelPartial)
        ->toContain("{{ \$panel['heading'] }}")
        ->toContain("{{ \$panel['description'] }}")
        ->toContain("@include('filament.pages.partials.provider-health-card'")
        ->and($partial)
        ->toContain("{{ \$provider['status_badge']['label'] }}")
        ->toContain("{{ \$provider['summary_badge']['label'] }}")
        ->toContain("{{ \$provider['status_message'] }}");
});

it('renders grace rotations and recent logs from shared presentation payloads', function (): void {
    $blade = File::get(resource_path('views/filament/pages/integration-settings.blade.php'));
    $controlPlaneSectionPartial = File::get(resource_path('views/filament/pages/partials/control-plane-section.blade.php'));
    $secretRotationListPartial = File::get(resource_path('views/filament/pages/partials/integration-settings-secret-rotation-list.blade.php'));
    $secretRotationPartial = File::get(resource_path('views/filament/pages/partials/secret-rotation-card.blade.php'));
    $auditLogPartial = File::get(resource_path('views/filament/pages/partials/integration-settings-audit-log-table.blade.php'));
    $noticePartial = File::get(resource_path('views/filament/pages/partials/integration-settings-notice.blade.php'));

    expect($blade)
        ->toContain("@include('filament.pages.partials.integration-settings-page-shell', [")
        ->toContain("'viewState' => \$this->pageViewState,")
        ->not->toContain("\\Illuminate\\Support\\Carbon::parse(\$rotation['grace_expires_at'])")
        ->not->toContain("optional(\$log->changed_at)->format('d/m/Y H:i:s')")
        ->and($controlPlaneSectionPartial)
        ->toContain(":heading=\"\$section['heading'] ?? null\"")
        ->toContain("@include(\$section['partial'], \$section['include_data'] ?? [])")
        ->and($secretRotationListPartial)
        ->toContain("@foreach(\$panel['items'] as \$rotation)")
        ->toContain("@include('filament.pages.partials.secret-rotation-card', ['rotation' => \$rotation])")
        ->and($secretRotationPartial)
        ->toContain("{{ \$rotation['grace_expires_at_label'] }}")
        ->toContain("{{ \$rotation['remaining_minutes_label'] }}")
        ->and($noticePartial)
        ->toContain('{{ $message }}')
        ->and($auditLogPartial)
        ->toContain("@forelse(\$auditLog['items'] as \$log)")
        ->toContain("{{ \$log['changed_at_label'] }}")
        ->toContain("{{ \$log['changed_by_name'] }}")
        ->toContain("{{ \$log['grace_expires_at_label'] }}");
});

it('renders provider guide blocks from shared partials', function (): void {
    $providerPanel = File::get(resource_path('views/filament/pages/partials/integration-settings-provider-panel.blade.php'));
    $partialList = File::get(resource_path('views/filament/pages/partials/control-plane-partial-list.blade.php'));
    $zaloGuide = File::get(resource_path('views/filament/pages/partials/zalo-oa-guide.blade.php'));
    $popupGuide = File::get(resource_path('views/filament/pages/partials/popup-announcement-guide.blade.php'));

    expect($providerPanel)
        ->toContain("@include('filament.pages.partials.control-plane-partial-list', [")
        ->toContain("'items' => \$provider['support_sections']")
        ->and($partialList)
        ->toContain('@foreach($items as $item)')
        ->toContain("@include(\$item['partial'], \$item['include_data'] ?? [])")
        ->and($zaloGuide)
        ->toContain('Checklist triển khai Zalo OA')
        ->toContain("route('api.v1.integrations.zalo.webhook')")
        ->and($popupGuide)
        ->toContain('Nguyên tắc popup nội bộ')
        ->toContain('Không dùng websocket. UI poll theo chu kỳ giây đã cấu hình.');
});

it('sends shared notification payload and stores account email when testing google calendar connection', function (): void {
    actingAsIntegrationSettingsProvidersAdmin();

    ClinicSetting::setValue('google_calendar.enabled', true, [
        'group' => 'google_calendar',
        'value_type' => 'boolean',
        'is_active' => true,
    ]);
    ClinicSetting::setValue('google_calendar.client_id', 'gcal-client-id', [
        'group' => 'google_calendar',
        'value_type' => 'text',
        'is_active' => true,
    ]);
    ClinicSetting::setValue('google_calendar.client_secret', 'gcal-client-secret', [
        'group' => 'google_calendar',
        'value_type' => 'text',
        'is_secret' => true,
        'is_active' => true,
    ]);
    ClinicSetting::setValue('google_calendar.refresh_token', 'gcal-refresh-token', [
        'group' => 'google_calendar',
        'value_type' => 'text',
        'is_secret' => true,
        'is_active' => true,
    ]);
    ClinicSetting::setValue('google_calendar.calendar_id', 'crm-calendar@example.com', [
        'group' => 'google_calendar',
        'value_type' => 'text',
        'is_active' => true,
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'google-access-token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ], 200),
        'https://www.googleapis.com/calendar/v3/calendars/*' => Http::response([
            'id' => 'crm-calendar@example.com',
        ], 200),
    ]);

    Livewire::test(IntegrationSettings::class)
        ->call('testGoogleCalendarConnection')
        ->assertSet('settings.google_calendar_account_email', 'crm-calendar@example.com')
        ->assertNotified('Kết nối Google Calendar thành công');
});

it('sends shared readiness notification payload for zalo oa checks', function (): void {
    actingAsIntegrationSettingsProvidersAdmin();

    ClinicSetting::setValue('zalo.enabled', true, [
        'group' => 'zalo',
        'value_type' => 'boolean',
        'is_active' => true,
    ]);
    ClinicSetting::setValue('zalo.oa_id', 'oa-test-ready', [
        'group' => 'zalo',
        'value_type' => 'text',
        'is_active' => true,
    ]);
    ClinicSetting::setValue('zalo.app_id', 'app-test-ready', [
        'group' => 'zalo',
        'value_type' => 'text',
        'is_active' => true,
    ]);
    ClinicSetting::setValue('zalo.app_secret', 'secret-test-ready', [
        'group' => 'zalo',
        'value_type' => 'text',
        'is_secret' => true,
        'is_active' => true,
    ]);
    ClinicSetting::setValue('zalo.webhook_token', 'verify-token-test-ready-1234567890', [
        'group' => 'zalo',
        'value_type' => 'text',
        'is_secret' => true,
        'is_active' => true,
    ]);

    Livewire::test(IntegrationSettings::class)
        ->call('testZaloReadiness')
        ->assertNotified('Zalo OA sẵn sàng tốt');
});

it('exposes web lead realtime notification toggle and role selector fields', function (): void {
    $page = app(IntegrationSettings::class);
    $providers = collect($page->getProviders());
    $webLeadProvider = $providers->firstWhere('group', 'web_lead');

    expect($webLeadProvider)->not->toBeNull();

    $webLeadFields = collect($webLeadProvider['fields'] ?? [])
        ->keyBy('key');

    expect($webLeadFields->has('web_lead.realtime_notification_enabled'))->toBeTrue()
        ->and($webLeadFields->has('web_lead.realtime_notification_roles'))->toBeTrue()
        ->and($webLeadFields->get('web_lead.realtime_notification_roles')['type'] ?? null)->toBe('roles');

    actingAsIntegrationSettingsProvidersAdmin();

    $html = Livewire::test(IntegrationSettings::class)->html();

    expect($html)
        ->toContain('wire:model.live="settings.web_lead_realtime_notification_roles"')
        ->toContain('Nhóm quyền nhận thông báo realtime')
        ->toContain('Bật thông báo realtime khi có web lead mới');
});

it('exposes web lead internal email runtime fields in integration settings', function (): void {
    $page = app(IntegrationSettings::class);
    $providers = collect($page->getProviders());
    $webLeadProvider = $providers->firstWhere('group', 'web_lead');

    expect($webLeadProvider)->not->toBeNull();

    $webLeadFields = collect($webLeadProvider['fields'] ?? [])
        ->keyBy('key');

    expect($webLeadFields->get('web_lead.internal_email_enabled')['type'] ?? null)->toBe('boolean')
        ->and($webLeadFields->get('web_lead.internal_email_recipient_roles')['type'] ?? null)->toBe('roles')
        ->and($webLeadFields->get('web_lead.internal_email_recipient_emails')['type'] ?? null)->toBe('textarea')
        ->and($webLeadFields->get('web_lead.internal_email_smtp_scheme')['type'] ?? null)->toBe('select')
        ->and($webLeadFields->has('web_lead.internal_email_from_address'))->toBeTrue()
        ->and($webLeadFields->has('web_lead.internal_email_from_name'))->toBeTrue();

    actingAsIntegrationSettingsProvidersAdmin();

    $html = Livewire::test(IntegrationSettings::class)->html();

    expect($html)
        ->toContain('wire:model.live="settings.web_lead_internal_email_recipient_roles"')
        ->toContain('wire:model.blur="settings.web_lead_internal_email_recipient_emails"')
        ->toContain('Mailbox nhận nội bộ (mỗi dòng một email)')
        ->toContain('SMTP host')
        ->toContain('SMTP password')
        ->toContain('From address');
});

it('exposes secret rotation grace window fields for inbound integrations', function (): void {
    $page = app(IntegrationSettings::class);
    $providers = collect($page->getProviders());

    $zaloFields = collect($providers->firstWhere('group', 'zalo')['fields'] ?? [])
        ->pluck('key')
        ->values()
        ->all();
    $emrFields = collect($providers->firstWhere('group', 'emr')['fields'] ?? [])
        ->pluck('key')
        ->values()
        ->all();
    $webLeadFields = collect($providers->firstWhere('group', 'web_lead')['fields'] ?? [])
        ->pluck('key')
        ->values()
        ->all();

    expect($zaloFields)->toContain('zalo.webhook_token_grace_minutes')
        ->and($emrFields)->toContain('emr.api_key_grace_minutes')
        ->and($webLeadFields)->toContain('web_lead.api_token_grace_minutes');
});

it('exposes facebook messenger provider fields in integration settings', function (): void {
    $page = app(IntegrationSettings::class);
    $providers = collect($page->getProviders());

    $facebookProvider = $providers->firstWhere('group', 'facebook');

    expect($facebookProvider)->not->toBeNull();

    $facebookFields = collect($facebookProvider['fields'] ?? [])
        ->keyBy('key');

    expect($facebookFields->has('facebook.enabled'))->toBeTrue()
        ->and($facebookFields->has('facebook.page_id'))->toBeTrue()
        ->and($facebookFields->has('facebook.app_id'))->toBeTrue()
        ->and($facebookFields->has('facebook.app_secret'))->toBeTrue()
        ->and($facebookFields->has('facebook.webhook_verify_token'))->toBeTrue()
        ->and($facebookFields->has('facebook.page_access_token'))->toBeTrue()
        ->and($facebookFields->has('facebook.send_endpoint'))->toBeTrue()
        ->and($facebookFields->has('facebook.inbox_default_branch_code'))->toBeTrue();
});

it('can autogenerate web lead api token in form state', function () {
    actingAsIntegrationSettingsProvidersAdmin();

    $component = Livewire::test(IntegrationSettings::class)
        ->set('settings.web_lead_api_token', '')
        ->call('generateWebLeadApiToken')
        ->assertNotified('Đã tạo API token mới');

    $token = (string) $component->get('settings.web_lead_api_token');

    expect($token)
        ->toStartWith('wla_')
        ->and(strlen($token))
        ->toBe(52);
});

it('warns when emr config url cannot be opened', function (): void {
    actingAsIntegrationSettingsProvidersAdmin();

    Livewire::test(IntegrationSettings::class)
        ->call('openEmrConfigUrl')
        ->assertNotified('Không thể mở trang cấu hình EMR');
});

it('normalizes exam indication image alias keys to canonical keys in catalog editor', function (): void {
    actingAsIntegrationSettingsProvidersAdmin();

    $component = Livewire::test(IntegrationSettings::class)
        ->set('catalogEditors.catalog_exam_indications_json', [
            ['key' => 'image_ext', 'label' => 'Ảnh ngoài'],
            ['key' => 'image_int', 'label' => 'Ảnh trong'],
        ])
        ->call('normalizeCatalogRowKey', 'catalog_exam_indications_json', 0)
        ->call('normalizeCatalogRowKey', 'catalog_exam_indications_json', 1);

    expect($component->get('catalogEditors.catalog_exam_indications_json.0.key'))->toBe('ext')
        ->and($component->get('catalogEditors.catalog_exam_indications_json.1.key'))->toBe('int');
});

it('auto generates catalog key from label for new row', function (): void {
    actingAsIntegrationSettingsProvidersAdmin();

    $component = Livewire::test(IntegrationSettings::class)
        ->set('catalogEditors.catalog_customer_sources_json', [
            ['key' => '', 'label' => 'Tư vấn trực tiếp', 'enabled' => true],
        ])
        ->call('syncCatalogRowFromLabel', 'catalog_customer_sources_json', 0);

    expect($component->get('catalogEditors.catalog_customer_sources_json.0.key'))->toBe('tu_van_truc_tiep');
});

it('auto generates and persists key on save when key is empty', function (): void {
    actingAsIntegrationSettingsProvidersAdmin();

    Livewire::test(IntegrationSettings::class)
        ->set('catalogEditors.catalog_customer_sources_json', [
            ['key' => '', 'label' => 'Nguồn thử nghiệm', 'enabled' => true],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $saved = ClinicSetting::getValue('catalog.customer_sources', []);

    expect($saved)->toBe([
        'nguon_thu_nghiem' => 'Nguồn thử nghiệm',
    ]);
});

it('allows deleting exam indication rows including ext and int', function (): void {
    actingAsIntegrationSettingsProvidersAdmin();

    $component = Livewire::test(IntegrationSettings::class)
        ->set('catalogEditors.catalog_exam_indications_json', [
            ['key' => 'ext', 'label' => 'Ảnh (ext)'],
            ['key' => 'int', 'label' => 'Ảnh (int)'],
            ['key' => 'panorama', 'label' => 'Panorama'],
        ])
        ->call('removeCatalogRow', 'catalog_exam_indications_json', 0);

    $rows = $component->get('catalogEditors.catalog_exam_indications_json');
    $keys = collect($rows)->pluck('key')->values()->all();

    expect($rows)->toHaveCount(2)
        ->and($keys)->not->toContain('ext')
        ->and($keys)->toContain('int')
        ->and($keys)->toContain('panorama');
});

it('does not re-insert ext and int when saving exam indication catalog', function (): void {
    actingAsIntegrationSettingsProvidersAdmin();

    Livewire::test(IntegrationSettings::class)
        ->set('catalogEditors.catalog_exam_indications_json', [
            ['key' => 'panorama', 'label' => 'Panorama'],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $saved = ClinicSetting::getValue('catalog.exam_indications', []);

    expect($saved)
        ->toMatchArray([
            'panorama' => 'Panorama',
        ]);
});

it('does not persist disabled catalog rows', function (): void {
    actingAsIntegrationSettingsProvidersAdmin();

    Livewire::test(IntegrationSettings::class)
        ->set('catalogEditors.catalog_customer_sources_json', [
            ['key' => 'walkin', 'label' => 'Khách vãng lai', 'enabled' => true],
            ['key' => 'facebook', 'label' => 'Facebook', 'enabled' => false],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $saved = ClinicSetting::getValue('catalog.customer_sources', []);

    expect($saved)
        ->toBe(['walkin' => 'Khách vãng lai'])
        ->and(array_key_exists('facebook', $saved))->toBeFalse();
});

it('exposes branding provider fields in integration settings', function () {
    $page = app(IntegrationSettings::class);
    $providers = collect($page->getProviders());

    $brandingProvider = $providers->firstWhere('group', 'branding');

    expect($brandingProvider)->not->toBeNull();

    $brandingKeys = collect($brandingProvider['fields'] ?? [])
        ->pluck('key')
        ->values()
        ->all();

    expect($brandingKeys)
        ->toContain('branding.clinic_name')
        ->toContain('branding.logo_url')
        ->toContain('branding.address')
        ->toContain('branding.phone')
        ->toContain('branding.email')
        ->toContain('branding.button_bg_color')
        ->toContain('branding.button_bg_hover_color')
        ->toContain('branding.button_text_color');
});

it('exposes popup runtime provider fields in integration settings', function (): void {
    $page = app(IntegrationSettings::class);
    $providers = collect($page->getProviders());

    $popupProvider = $providers->firstWhere('group', 'popup');

    expect($popupProvider)->not->toBeNull();

    $popupKeys = collect($popupProvider['fields'] ?? [])
        ->pluck('key')
        ->values()
        ->all();

    expect($popupKeys)
        ->toContain('popup.enabled')
        ->toContain('popup.polling_seconds')
        ->toContain('popup.retention_days')
        ->toContain('popup.sender_roles');
});

it('builds provider action groups from shared control-plane state', function (): void {
    actingAsIntegrationSettingsProvidersAdmin();

    $page = app(IntegrationSettings::class);
    $page->mount();
    $providerPanels = collect(integrationSettingsProviderPanels($page))->keyBy('group');
    $zaloActions = $providerPanels['zalo']['support_sections'][0]['include_data']['actions'];
    $googleCalendarActions = $providerPanels['google_calendar']['support_sections'][0]['include_data']['actions'];
    $emrActions = $providerPanels['emr']['support_sections'][0]['include_data']['actions'];
    $webLeadActions = $providerPanels['web_lead']['support_sections'][0]['include_data']['actions'];

    expect($providerPanels->keys()->all())->toContain('zalo', 'zns', 'google_calendar', 'emr', 'web_lead', 'popup')
        ->and($zaloActions[0])->toMatchArray([
            'wire_click' => 'testZaloReadiness',
            'label' => 'Đánh giá sẵn sàng Zalo OA',
        ])
        ->and($googleCalendarActions[0])->toMatchArray([
            'wire_click' => 'testGoogleCalendarConnection',
            'label' => 'Test Google Calendar',
        ])
        ->and(collect($emrActions)->pluck('wire_click')->all())->toBe([
            'testDicomReadiness',
            'testEmrConnection',
            'openEmrConfigUrl',
        ])
        ->and(collect($webLeadActions)->pluck('wire_click')->all())->toBe([
            'testWebLeadReadiness',
            'generateWebLeadApiToken',
        ])
        ->and($providerPanels['zalo']['support_sections'][1]['partial'])->toBe('filament.pages.partials.zalo-oa-guide')
        ->and($providerPanels['web_lead']['support_sections'][1]['partial'])->toBe('filament.pages.partials.web-lead-api-guide')
        ->and($providerPanels['popup']['support_sections'][0]['partial'])->toBe('filament.pages.partials.popup-announcement-guide')
        ->and($providerPanels['emr']['support_sections'][0]['include_data']['actions'])->toBe($emrActions);
});

it('exposes only supported google calendar sync mode options in integration settings', function (): void {
    $page = app(IntegrationSettings::class);
    $providers = collect($page->getProviders());
    $googleCalendarProvider = $providers->firstWhere('group', 'google_calendar');

    expect($googleCalendarProvider)->not->toBeNull();

    $googleCalendarFields = collect($googleCalendarProvider['fields'] ?? [])
        ->keyBy('key');
    $modeField = $googleCalendarFields->get('google_calendar.sync_mode');

    expect($modeField)->toBeArray()
        ->and(array_keys($modeField['options'] ?? []))->toBe(['one_way_to_google'])
        ->and($modeField['default'] ?? null)->toBe('one_way_to_google')
        ->and(ClinicRuntimeSettings::googleCalendarSyncModeOptions())->toBe([
            'one_way_to_google' => 'Một chiều: CRM -> Google (đã hỗ trợ)',
        ]);
});

it('reads recent integration setting logs through the shared audit reader', function (): void {
    $admin = actingAsIntegrationSettingsProvidersAdmin();

    $olderLog = ClinicSettingLog::query()->create([
        'setting_group' => 'web_lead',
        'setting_key' => 'web_lead.api_token',
        'setting_label' => 'Web Lead API Token',
        'old_value' => 'old-token',
        'new_value' => 'new-token',
        'change_reason' => 'Older log',
        'context' => ['source' => 'test'],
        'is_secret' => true,
        'changed_by' => $admin->id,
        'changed_at' => now()->subMinutes(10),
    ]);

    $newerLog = ClinicSettingLog::query()->create([
        'setting_group' => 'zalo',
        'setting_key' => 'zalo.webhook_token',
        'setting_label' => 'Zalo Webhook Token',
        'old_value' => 'old-zalo-token',
        'new_value' => 'new-zalo-token',
        'change_reason' => 'Newest log',
        'context' => ['source' => 'test'],
        'is_secret' => true,
        'changed_by' => $admin->id,
        'changed_at' => now(),
    ]);

    $recentLogs = app(IntegrationSettingsAuditReadModelService::class)->recentLogs();

    expect($recentLogs)->toHaveCount(2)
        ->and($recentLogs->first()?->is($newerLog))->toBeTrue()
        ->and($recentLogs->last()?->is($olderLog))->toBeTrue()
        ->and($recentLogs->first()?->relationLoaded('changedBy'))->toBeTrue();
});

it('renders integration settings audit logs and grace rotations through shared readers', function (): void {
    $admin = actingAsIntegrationSettingsProvidersAdmin();

    ClinicSetting::setValue('web_lead.api_token', 'old-web-lead-token', [
        'group' => 'web_lead',
        'label' => 'Web Lead API Token',
        'value_type' => 'text',
        'is_secret' => true,
        'is_active' => true,
    ]);
    ClinicSetting::setValue('web_lead.api_token_grace_minutes', 15, [
        'group' => 'web_lead',
        'value_type' => 'integer',
        'is_active' => true,
    ]);

    app(\App\Services\IntegrationSecretRotationService::class)->rotate(
        settingKey: 'web_lead.api_token',
        newSecret: 'new-web-lead-token',
        actorId: $admin->id,
        reason: 'Shared rendered grace test.',
    );

    ClinicSettingLog::query()->create([
        'setting_group' => 'web_lead',
        'setting_key' => 'web_lead.api_token',
        'setting_label' => 'Web Lead API Token',
        'old_value' => 'masked-old',
        'new_value' => 'masked-new',
        'change_reason' => 'Shared rendered audit test',
        'context' => ['grace_expires_at' => now()->addMinutes(15)->toISOString()],
        'is_secret' => true,
        'changed_by' => $admin->id,
        'changed_at' => now(),
    ]);

    $page = app(IntegrationSettings::class);
    $page->mount();
    $pageViewState = $page->pageViewState();
    $preFormSections = collect($pageViewState['form_panel']['pre_sections']);
    $postFormSections = collect($pageViewState['post_form_sections']);
    $secretRotationSection = $preFormSections->firstWhere('partial', 'filament.pages.partials.integration-settings-secret-rotation-list');
    $providerHealthSection = $preFormSections->firstWhere('partial', 'filament.pages.partials.provider-health-panel');
    $auditLogSection = $postFormSections->firstWhere('partial', 'filament.pages.partials.integration-settings-audit-log-table');
    $auditLogItems = collect($auditLogSection['include_data']['auditLog']['items'] ?? []);

    expect($secretRotationSection)->not->toBeNull()
        ->and($secretRotationSection['include_data']['panel']['items'])->toHaveCount(1)
        ->and($secretRotationSection['include_data']['panel']['items']->first()['display_name'])->toBe('Web Lead API Token')
        ->and($secretRotationSection['include_data']['panel']['items']->first()['grace_expires_at_label'])->not->toBeEmpty()
        ->and($providerHealthSection)->not->toBeNull()
        ->and($providerHealthSection['include_data']['panel']['items'])->toBeArray()
        ->and($auditLogItems->contains(fn (array $log): bool => $log['change_reason'] === 'Shared rendered audit test'))->toBeTrue()
        ->and($auditLogItems->firstWhere('change_reason', 'Shared rendered audit test'))->toMatchArray([
            'changed_by_name' => $admin->name,
            'setting_label' => 'Web Lead API Token',
            'change_reason' => 'Shared rendered audit test',
        ]);
});

function actingAsIntegrationSettingsProvidersAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    test()->actingAs($admin);

    return $admin;
}

/**
 * @return array<int, array<string, mixed>>
 */
function integrationSettingsProviderPanels(IntegrationSettings $page): array
{
    return collect($page->pageViewState()['form_panel']['provider_sections'] ?? [])
        ->map(fn (array $section): array => $section['include_data']['provider'])
        ->values()
        ->all();
}
