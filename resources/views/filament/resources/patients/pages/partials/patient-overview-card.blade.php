@props([
    'overviewCard',
    'identityHeader',
    'basicInfoGrid',
])

<div class="crm-patient-overview-card">
    <div class="crm-patient-overview-header">
        <div class="crm-patient-overview-header-inner">
            <div class="crm-patient-avatar">
                {{ $identityHeader['avatar_initials'] }}
            </div>

            <div class="crm-patient-identity">
                <div class="crm-patient-identity-row">
                    <h2 class="crm-patient-name">
                        {{ $identityHeader['full_name'] }}
                    </h2>
                    @if($identityHeader['gender_label'])
                        <span class="crm-patient-gender-badge {{ $identityHeader['gender_badge_class'] }}">{{ $identityHeader['gender_label'] }}</span>
                    @endif
                </div>
                <div class="crm-copy-inline-row">
                    <p class="crm-patient-code">{{ $identityHeader['patient_code'] }}</p>
                    @if($identityHeader['patient_code'])
                        @include('filament.resources.patients.pages.partials.copy-button', [
                            'buttonClass' => 'crm-copy-icon-btn is-light',
                            'copyValue' => $identityHeader['patient_code'],
                            'copyLabel' => $identityHeader['patient_code_copy_label'],
                            'actionLabel' => $identityHeader['patient_code_copy_action_label'],
                        ])
                    @endif
                </div>
                @if($identityHeader['phone'])
                    <div class="crm-copy-inline-row">
                        <a href="{{ $identityHeader['phone_href'] }}" class="crm-patient-phone-chip" style="color: #ffffff;">
                            <svg class="crm-patient-phone-chip-icon" fill="currentColor" viewBox="0 0 20 20">
                                <path
                                    d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z" />
                            </svg>
                            <span class="crm-patient-phone-chip-text" style="color: #ffffff;">{{ $identityHeader['phone'] }}</span>
                        </a>
                        @include('filament.resources.patients.pages.partials.copy-button', [
                            'buttonClass' => 'crm-copy-icon-btn is-light',
                            'copyValue' => $identityHeader['phone'],
                            'copyLabel' => $identityHeader['phone_copy_label'],
                            'actionLabel' => $identityHeader['phone_copy_action_label'],
                        ])
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="crm-patient-overview-body">
        <div class="crm-patient-info-grid">
            @foreach($basicInfoGrid['cards'] as $card)
                @include('filament.resources.patients.pages.partials.info-card', ['card' => $card])
            @endforeach
        </div>
        @if($basicInfoGrid['address_card'])
            <div class="crm-patient-address-card is-address">
                <div class="crm-patient-info-card-head">
                    <div class="crm-patient-info-icon">
                        <x-filament::icon :icon="$basicInfoGrid['address_card']['icon']" class="crm-patient-info-icon-svg" />
                    </div>
                    <span class="crm-patient-info-label">{{ $basicInfoGrid['address_card']['label'] }}</span>
                </div>
                <p class="crm-patient-address-value">
                    {{ $basicInfoGrid['address_card']['value'] }}
                </p>
            </div>
        @endif
    </div>
</div>
