<div class="mt-4 rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
    <div class="mb-2 text-sm font-semibold">Nguyên tắc popup nội bộ</div>
    <ul class="list-disc space-y-1 pl-5 text-xs md:text-sm">
        <li>Popup nhận theo nhóm quyền + chi nhánh, không phát ngẫu nhiên toàn hệ thống.</li>
        <li>Mỗi popup chỉ hiển thị 1 lần cho mỗi user (sau khi xác nhận/đóng sẽ không lặp).</li>
        <li>Role được phép gửi popup toàn hệ thống được cấu hình ở mục này.</li>
        <li>Không dùng websocket. UI poll theo chu kỳ giây đã cấu hình.</li>
    </ul>
</div>
