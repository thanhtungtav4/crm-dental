<?php

use App\Filament\Pages\CustomerCare;
use App\Filament\Pages\FrontdeskControlCenter;
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
    $calendarShellState = File::get(resource_path('views/filament/appointments/partials/calendar-shell-state.blade.php'));
    $panelProvider = File::get(app_path('Providers/Filament/AdminPanelProvider.php'));

    expect($view)
        ->toContain('@php($viewState = $this->calendarViewState())')
        ->toContain("x-data=\"@include('filament.appointments.partials.calendar-shell-state')\"")
        ->toContain('<x-filament::modal')
        ->toContain('appointment-calendar-reschedule-modal')
        ->toContain("x-init=\"init(@js(\$viewState['status_colors']))\"")
        ->not->toContain('Branch::query()')
        ->not->toContain("->role('Doctor')")
        ->not->toContain('function calendar()')
        ->not->toContain('cdn.jsdelivr.net/npm/fullcalendar')
        ->toContain('heading="Dời lịch hẹn"')
        ->not->toContain('window.prompt')
        ->not->toContain('window.alert')
        ->not->toContain('window.confirm');

    expect($panelProvider)
        ->toContain('use Filament\Support\Assets\Js;')
        ->toContain('->assets([')
        ->toContain("Js::make('fullcalendar-global', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js')");

    expect($calendarShellState)
        ->toContain('new FullCalendar.Calendar(el, {')
        ->toContain('callFetchEvents(startAtIso, endAtIso)')
        ->toContain('submitReschedule()')
        ->toContain('message.includes(\'trùng lịch\')');
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
