@props([
    'panel',
])

@if($panel['is_visible'])
    <div class="crm-modal-backdrop crm-modal-z-50" wire:click="closePlanModal">
        <div class="crm-modal-card crm-modal-card-lg dark:bg-gray-900" wire:click.stop>
            <div class="crm-modal-header">
                <h3 class="text-lg font-semibold text-gray-900">Thêm kế hoạch điều trị</h3>
                <button type="button" wire:click="closePlanModal" class="crm-modal-close-btn">✕</button>
            </div>

            <div class="crm-modal-body">
                <div class="mb-4 flex items-center justify-between">
                    <div class="text-sm font-semibold text-gray-900">Thủ thuật thực hiện *</div>
                    <button type="button" wire:click="openProcedureModal" class="crm-btn crm-btn-outline px-3 py-2">
                        Thêm thủ thuật
                    </button>
                </div>

                <div class="crm-plan-editor-table-wrap">
                    <table class="crm-plan-editor-table">
                        <thead>
                            <tr>
                                <th class="is-center">STT</th>
                                <th>Răng số</th>
                                <th>Tình trạng răng</th>
                                <th>Tên thủ thuật</th>
                                <th class="is-center">KH đồng ý</th>
                                <th>Lý do từ chối</th>
                                <th class="is-center">S.L</th>
                                <th class="is-right">Đơn giá</th>
                                <th class="is-right">Thành tiền</th>
                                <th class="is-right">Giảm giá (%)</th>
                                <th class="is-right">Tiền giảm giá</th>
                                <th class="is-right">Tổng chi phí</th>
                                <th>Ghi chú</th>
                                <th class="is-center">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($panel['draft_rows'] as $row)
                                <tr wire:key="draft-item-{{ $row['index'] }}">
                                    <td class="is-center">{{ $row['index'] + 1 }}</td>
                                    <td>
                                        <input type="text" readonly value="{{ $row['tooth_display'] }}"
                                            class="crm-plan-editor-input">
                                    </td>
                                    <td>
                                        <select multiple wire:model="draftItems.{{ $row['index'] }}.diagnosis_ids"
                                            class="crm-plan-editor-select">
                                            @foreach($panel['diagnosis_options'] as $id => $label)
                                                <option value="{{ $id }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>{{ $row['service_name'] }}</td>
                                    <td class="is-center">
                                        <select wire:model="draftItems.{{ $row['index'] }}.approval_status" class="crm-plan-editor-select">
                                            <option value="draft">Nháp</option>
                                            <option value="proposed">Đề xuất</option>
                                            <option value="approved">Đồng ý</option>
                                            <option value="declined">Từ chối</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text"
                                            wire:model.lazy="draftItems.{{ $row['index'] }}.approval_decline_reason"
                                            @disabled($row['approval_status'] !== 'declined')
                                            placeholder="Nhập lý do nếu từ chối"
                                            class="crm-plan-editor-input">
                                    </td>
                                    <td class="is-center">
                                        <input type="number" min="1" wire:model.lazy="draftItems.{{ $row['index'] }}.quantity"
                                            class="crm-plan-editor-input is-qty">
                                    </td>
                                    <td class="is-right">
                                        <input type="number" min="0" wire:model.lazy="draftItems.{{ $row['index'] }}.price"
                                            class="crm-plan-editor-input is-price">
                                    </td>
                                    <td class="is-right">{{ $row['line_amount_text'] }}</td>
                                    <td class="is-right">
                                        <input type="number" min="0" max="100" wire:model.lazy="draftItems.{{ $row['index'] }}.discount_percent"
                                            class="crm-plan-editor-input is-discount">
                                    </td>
                                    <td class="is-right">{{ $row['discount_amount_text'] }}</td>
                                    <td class="is-right">{{ $row['total_amount_text'] }}</td>
                                    <td>
                                        <input type="text" wire:model.lazy="draftItems.{{ $row['index'] }}.notes"
                                            class="crm-plan-editor-input">
                                    </td>
                                    <td class="is-center">
                                        <button type="button" wire:click="removeDraftItem({{ $row['index'] }})" class="crm-plan-editor-remove-btn">Xóa</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="14" class="crm-plan-editor-empty">
                                        Chưa có thủ thuật được chọn.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="mt-2 text-xs text-gray-500">
                    Răng số được đồng bộ tự động theo các chẩn đoán đã chọn.
                </p>
            </div>

            <div class="crm-modal-footer">
                <button type="button" wire:click="closePlanModal" class="crm-btn crm-btn-outline px-4 py-2">
                    Hủy bỏ
                </button>
                <button type="button" wire:click="savePlanItems" class="crm-btn crm-btn-primary px-4 py-2">
                    Lưu thông tin
                </button>
            </div>
        </div>
    </div>
@endif
