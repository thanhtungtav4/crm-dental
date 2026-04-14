<?php

use App\Filament\Pages\CustomerCare;
use App\Filament\Pages\FrontdeskControlCenter;
use App\Filament\Pages\SystemSettings;
use App\Filament\Resources\Appointments\Pages\CalendarAppointments;
use App\Filament\Resources\Patients\PatientResource;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\LocalDemoDataSeeder;
use Illuminate\Support\Facades\File;
use Jeffgreco13\FilamentBreezy\Middleware\MustTwoFactor;

use function Pest\Laravel\seed;

beforeEach(function (): void {
    $this->withoutMiddleware(MustTwoFactor::class);
});

it('redirects guests from the root route to the admin login page', function (): void {
    $this->get('/')
        ->assertRedirect('/admin/login');
});

it('redirects authenticated staff from the root route to the admin dashboard', function (): void {
    seed(LocalDemoDataSeeder::class);

    $staff = User::query()
        ->where('email', 'cskh.q1@demo.ident.test')
        ->firstOrFail();

    $this->actingAs($staff)
        ->get('/')
        ->assertRedirect('/admin');
});

it('renders the frontdesk subheading only once', function (): void {
    seed(LocalDemoDataSeeder::class);

    $staff = User::query()
        ->where('email', 'cskh.q1@demo.ident.test')
        ->firstOrFail();

    $response = $this->actingAs($staff)
        ->get(FrontdeskControlCenter::getUrl())
        ->assertOk();

    expect(substr_count(
        $response->getContent(),
        'Nhìn nhanh lead đang mở, lịch hẹn sắp tới và queue CSKH để điều phối trong ngày mà không cần nhảy qua nhiều module.',
    ))->toBe(1);
});

it('renders accessible tab semantics on customer care and patient workspace pages', function (): void {
    seed(LocalDemoDataSeeder::class);

    $staff = User::query()
        ->where('email', 'cskh.q1@demo.ident.test')
        ->firstOrFail();

    $patient = Patient::query()
        ->where('first_branch_id', $staff->branch_id)
        ->orderBy('id')
        ->firstOrFail();

    $this->actingAs($staff)
        ->get(CustomerCare::getUrl())
        ->assertOk()
        ->assertSee('role="tablist"', false)
        ->assertSee('role="tab"', false)
        ->assertSee('aria-selected="true"', false)
        ->assertSee('aria-controls=', false);

    $this->actingAs($staff)
        ->get("/admin/patients/{$patient->id}?tab=basic-info")
        ->assertOk()
        ->assertSee('role="tablist"', false)
        ->assertSee('role="tab"', false)
        ->assertSee('aria-selected="true"', false)
        ->assertSee('aria-controls=', false)
        ->assertSee('aria-label="Chọn khu vực làm việc hồ sơ bệnh nhân"', false);
});

it('uses a panel modal for calendar rescheduling instead of browser dialogs', function (): void {
    $view = File::get(resource_path('views/filament/appointments/calendar.blade.php'));
    $calendarPageShell = File::get(resource_path('views/filament/appointments/partials/calendar-page-shell.blade.php'));
    $calendarFiltersPanel = File::get(resource_path('views/filament/appointments/partials/calendar-filters-panel.blade.php'));
    $calendarRescheduleModal = File::get(resource_path('views/filament/appointments/partials/calendar-reschedule-modal.blade.php'));
    $calendarShellState = File::get(resource_path('views/filament/appointments/partials/calendar-shell-state.blade.php'));
    $panelProvider = File::get(app_path('Providers/Filament/AdminPanelProvider.php'));

    expect($view)
        ->toContain("@include('filament.appointments.partials.calendar-page-shell'")
        ->toContain("'viewState' => \$this->calendarViewState()")
        ->not->toContain('@php($viewState = $this->calendarViewState())')
        ->not->toContain('Branch::query()')
        ->not->toContain("->role('Doctor')")
        ->not->toContain('function calendar()')
        ->not->toContain('cdn.jsdelivr.net/npm/fullcalendar');

    expect($calendarPageShell)
        ->toContain("x-data=\"@include('filament.appointments.partials.calendar-shell-state', ['panel' => \$viewState['shell_panel']])\"")
        ->toContain("x-init=\"init(@js(\$viewState['status_colors']))\"")
        ->toContain("@include('filament.appointments.partials.calendar-filters-panel'")
        ->toContain("@include('filament.appointments.partials.calendar-reschedule-modal', [")
        ->toContain("'panel' => \$viewState['reschedule_modal_panel']");

    expect($calendarFiltersPanel)
        ->toContain("\$panel['status_options']")
        ->toContain("\$panel['branch_options']")
        ->toContain("\$panel['doctor_options']");

    expect($calendarRescheduleModal)
        ->toContain("@props([\n    'panel',\n])")
        ->toContain('<x-filament::modal')
        ->toContain(":id=\"\$panel['id']\"")
        ->toContain(":heading=\"\$panel['heading']\"")
        ->toContain(":description=\"\$panel['description']\"")
        ->not->toContain('window.prompt')
        ->not->toContain('window.alert')
        ->not->toContain('window.confirm');

    expect($panelProvider)
        ->toContain('use Filament\Support\Assets\Js;')
        ->toContain('->assets([')
        ->toContain("Js::make('fullcalendar-global', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js')");

    expect($calendarShellState)
        ->toContain("@props([\n    'panel',\n])")
        ->toContain('new FullCalendar.Calendar(el, {')
        ->toContain('callFetchEvents(startAtIso, endAtIso)')
        ->toContain('submitReschedule()')
        ->toContain("const baseUrl = @js(\$panel['create_url'])")
        ->toContain("@js(\$panel['connection_error_message'])")
        ->toContain("message.includes(@js(\$panel['conflict_keyword']))");
});

it('renders the registered fullcalendar asset on the calendar page', function (): void {
    seed(LocalDemoDataSeeder::class);

    $staff = User::query()
        ->where('email', 'cskh.q1@demo.ident.test')
        ->firstOrFail();

    $this->actingAs($staff)
        ->get(CalendarAppointments::getUrl())
        ->assertOk()
        ->assertSee('https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js', false);
});

it('renders the patient intake form as grouped sections instead of a flat wall of fields', function (): void {
    $branch = Branch::factory()->create();

    $admin = User::factory()->create([
        'branch_id' => $branch->id,
    ]);
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->get(PatientResource::getUrl('create'))
        ->assertOk()
        ->assertSee('Định danh hồ sơ')
        ->assertSee('Liên hệ & tiếp nhận')
        ->assertSee('Phân nhóm & phụ trách')
        ->assertSee('Ghi chú lâm sàng');
});

it('uses shell partials for system settings and placeholder pages', function (): void {
    $systemSettingsClass = File::get(app_path('Filament/Pages/SystemSettings.php'));
    $systemSettingsView = File::get(resource_path('views/filament/pages/system-settings.blade.php'));
    $systemSettingsShell = File::get(resource_path('views/filament/pages/partials/system-settings-page-shell.blade.php'));
    $placeholderPageClass = File::get(app_path('Filament/Pages/PlaceholderPage.php'));
    $placeholderView = File::get(resource_path('views/filament/pages/placeholder.blade.php'));
    $placeholderShell = File::get(resource_path('views/filament/pages/partials/placeholder-page-shell.blade.php'));

    expect($systemSettingsClass)
        ->toContain('public function pageViewState(): array');

    expect($systemSettingsView)
        ->toContain("@include('filament.pages.partials.system-settings-page-shell'")
        ->toContain("'viewState' => \$this->pageViewState()");

    expect($systemSettingsShell)
        ->toContain("@include('filament.pages.partials.system-settings-section-panel'")
        ->not->toContain('$this->getSettingSections()');

    expect($placeholderPageClass)
        ->toContain('public function pageViewState(): array');

    expect($placeholderView)
        ->toContain("@include('filament.pages.partials.placeholder-page-shell'")
        ->toContain("'viewState' => \$this->pageViewState()")
        ->not->toContain('@php($bullets = $this->getBullets())');

    expect($placeholderShell)
        ->toContain("\$viewState['badge_label']")
        ->toContain("\$viewState['status_text']")
        ->toContain("\$viewState['bullets']");
});

it('renders the system settings page through the shell partial', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $admin->givePermissionTo('View:SystemSettings');

    $this->actingAs($admin)
        ->get(SystemSettings::getUrl())
        ->assertOk()
        ->assertSee('Danh mục khám & điều trị')
        ->assertSee('Cài đặt tích hợp')
        ->assertSee('Cấu hình vận hành');
});

it('renders the passkeys profile surface through a view-state shell', function (): void {
    $component = File::get(app_path('Livewire/PasskeysComponent.php'));
    $view = File::get(resource_path('views/livewire/passkeys-component.blade.php'));
    $shell = File::get(resource_path('views/livewire/partials/passkeys-component-shell.blade.php'));

    expect($component)
        ->toContain('public function viewState(): array')
        ->toContain("'viewState' => \$this->viewState()")
        ->and($view)
        ->toContain("@include('livewire.partials.passkeys-component-shell'")
        ->not->toContain('heading="Khóa truy cập (Passkey)"')
        ->and($shell)
        ->toContain(":heading=\"\$viewState['heading']\"")
        ->toContain(":description=\"\$viewState['description']\"")
        ->toContain("\$viewState['unsupported_panel']['title']")
        ->toContain("@foreach (\$viewState['unsupported_panel']['requirements'] as \$requirement)")
        ->toContain("\$viewState['checking_label']");
});
