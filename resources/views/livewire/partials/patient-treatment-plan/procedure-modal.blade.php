@props([
    'isVisible',
    'selectedCategoryId',
    'categories',
    'viewState',
])

@if($isVisible)
    <div class="crm-modal-backdrop crm-modal-z-60" wire:click="closeProcedureModal">
        <div class="crm-modal-card crm-modal-card-lg dark:bg-gray-900" wire:click.stop>
            <div class="crm-modal-header">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Chọn thủ thuật điều trị</h3>
                    <a href="{{ url('/setting/trick') }}" class="text-xs text-primary-600">Đi tới thiết lập thủ thuật</a>
                </div>
                <button type="button" wire:click="closeProcedureModal" class="crm-modal-close-btn">✕</button>
            </div>

            <div class="crm-modal-body">
                <div class="mb-4">
                    <input type="text" wire:model.debounce.300ms="procedureSearch" placeholder="Tìm theo tên thủ thuật"
                        class="crm-procedure-search-input">
                </div>

                <div class="crm-procedure-layout">
                    <div class="crm-procedure-category-panel">
                        <button type="button"
                            wire:click="selectCategory(null)"
                            class="crm-procedure-category-btn {{ $selectedCategoryId ? '' : 'is-active' }}">
                            Tất cả nhóm thủ thuật
                        </button>
                        @foreach($categories as $category)
                            <button type="button"
                                wire:click="selectCategory({{ $category->id }})"
                                class="crm-procedure-category-btn {{ $selectedCategoryId === $category->id ? 'is-active' : '' }}">
                                {{ $category->name }}
                            </button>
                        @endforeach
                    </div>

                    <div class="crm-procedure-services-wrap">
                        <table class="crm-procedure-services-table">
                            <thead>
                                <tr>
                                    <th class="is-center">Chọn</th>
                                    <th>Tên thủ thuật</th>
                                    <th class="is-right">Đơn giá</th>
                                    <th>Quy trình thủ thuật</th>
                                    <th>Ghi chú</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($viewState['service_rows'] as $service)
                                    <tr wire:key="service-{{ $service['id'] }}">
                                        <td class="is-center">
                                            <input type="checkbox" value="{{ $service['id'] }}" wire:model="selectedServiceIds" class="crm-check-sm">
                                        </td>
                                        <td>{{ $service['name'] }}</td>
                                        <td class="is-right">{{ $service['price_text'] }}</td>
                                        <td>{{ $service['description'] }}</td>
                                        <td>-</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="crm-plan-editor-empty">
                                            Không có thủ thuật phù hợp.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="crm-modal-footer">
                <button type="button" wire:click="closeProcedureModal" class="crm-btn crm-btn-outline px-4 py-2">
                    Hủy bỏ
                </button>
                <button type="button" wire:click="addSelectedServices" class="crm-btn crm-btn-primary px-4 py-2">
                    Chọn thủ thuật
                </button>
            </div>
        </div>
    </div>
@endif
