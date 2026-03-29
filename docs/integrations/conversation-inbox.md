# Conversation Inbox Đa Kênh

Tài liệu này là source of truth cho tính năng `Inbox hội thoại` đang gom `Zalo OA` và `Facebook Messenger` về cùng một queue vận hành trong CRM.

## Audience

- Dev / reviewer cần hiểu boundary webhook, normalize, lead binding, queue gửi tin
- Ops / admin cần biết cấu hình runtime, permission, rollout và retry behavior
- QA cần checklist smoke test và các trạng thái cần quan sát

## Mục tiêu

- Nhận tin nhắn inbound từ `Zalo OA` hoặc `Facebook Messenger`
- Normalize về `conversation + conversation_message`
- Hiển thị realtime trong CRM bằng `Livewire polling`
- Cho CSKH reply ngay trên CRM
- Khi đủ tín hiệu, tạo lead từ conversation và bind ngược lại
- Mọi tin nhắn đến sau đó tiếp tục chảy vào đúng conversation/lead cũ

## Scope v1

- Supported inbound:
  - `Zalo OA` text message
  - `Facebook Messenger` text message
- Supported outbound:
  - text reply từ CRM
- Chưa support:
  - attachment
  - reaction
  - read/delivery receipt
  - comment, Instagram DM, Lead Ads
- Realtime dùng `polling`, không dùng websocket ở v1

## Entry points

- Filament page: `/admin/conversation-inbox`
- Webhook:
  - `/api/v1/integrations/zalo/webhook`
  - `/api/v1/integrations/facebook/webhook`

## Runtime settings

### Zalo OA

- `zalo.enabled`
- `zalo.oa_id`
- `zalo.app_id`
- `zalo.app_secret`
- `zalo.access_token`
- `zalo.webhook_token`
- `zalo.send_endpoint`
- `zalo.inbox_default_branch_code`
- `zalo.inbox_polling_seconds`

### Facebook Messenger

- `facebook.enabled`
- `facebook.page_id`
- `facebook.app_id`
- `facebook.app_secret`
- `facebook.webhook_verify_token`
- `facebook.page_access_token`
- `facebook.send_endpoint`
- `facebook.inbox_default_branch_code`

## Cách lấy token và kết nối provider

### Zalo OA: lấy `Access Token` và `Webhook Verify Token`

#### Điều kiện trước khi lấy token

- OA đã được tạo và có quyền quản trị
- OA đã được xác thực và đã bật lane tích hợp/OpenAPI phù hợp
- Có endpoint public HTTPS cho webhook CRM

#### Những gì lấy từ Zalo

- `OA ID`
- `App ID`
- `App Secret`
- `Access Token`

#### Những gì CRM tự định nghĩa

- `Webhook Verify Token`

Token này không phải Zalo cấp sẵn. Đây là chuỗi bí mật do team mình tự tạo, rồi nhập cùng một giá trị ở cả CRM và màn hình cấu hình webhook phía Zalo.

#### Quy trình gợi ý

1. Vào màn hình quản trị `Zalo Official Account`.
2. Kiểm tra OA đã xác thực và đã mở quyền `OpenAPI / tính năng mở rộng`.
3. Tạo hoặc chọn app đang dùng để tích hợp OA.
4. Lấy `OA ID`, `App ID`, `App Secret`.
5. Tạo hoặc copy `Access Token` dùng cho OA OpenAPI.
6. Tự sinh một chuỗi ngẫu nhiên dài để làm `Webhook Verify Token`.
7. Khai báo callback URL webhook của CRM tại Zalo và dùng đúng chuỗi token ở bước 6.
8. Dán các giá trị này vào `Integration Settings` trong CRM.

#### Map vào CRM

- `zalo.oa_id` → `OA ID`
- `zalo.app_id` → `App ID`
- `zalo.app_secret` → `App Secret`
- `zalo.access_token` → `Access Token`
- `zalo.webhook_token` → chuỗi verify token do CRM tự định nghĩa
- `zalo.send_endpoint` → giữ mặc định nếu không có yêu cầu đặc biệt
- `zalo.inbox_default_branch_code` → mã chi nhánh sẽ nhận hội thoại mới từ OA này
- `zalo.inbox_polling_seconds` → chu kỳ polling hiển thị thread trên CRM

#### Dấu hiệu cấu hình đúng

- Verify webhook thành công
- Gửi 1 tin từ người dùng vào OA thì `conversation` mới xuất hiện trong CRM
- Reply từ CRM đi được và message chuyển sang `Đã gửi`

### Facebook Messenger: lấy `Page Access Token` và `Webhook Verify Token`

#### Điều kiện trước khi lấy token

- Có `Meta App`
- App đã thêm product `Messenger`
- App đã connect với đúng `Facebook Page`
- Có endpoint public HTTPS cho webhook CRM

#### Những gì lấy từ Meta

- `Page ID`
- `App ID`
- `App Secret`
- `Page Access Token`

#### Những gì CRM tự định nghĩa

- `Webhook Verify Token`

Giống Zalo, verify token là chuỗi bí mật do team mình tự tạo. Meta sẽ gửi challenge verify về webhook và CRM phải trả lời bằng đúng token đã khai báo.

#### Quy trình gợi ý

1. Vào `Meta for Developers` và mở app tích hợp Messenger.
2. Kiểm tra app đã add product `Messenger`.
3. Chọn đúng `Facebook Page` sẽ dùng để nhận/gửi tin.
4. Trong phần Messenger/Page setup, generate hoặc copy `Page Access Token`.
5. Tại `Basic Settings`, lấy `App ID` và `App Secret`.
6. Tự sinh một chuỗi ngẫu nhiên dài để làm `Webhook Verify Token`.
7. Ở màn hình webhook của Meta, nhập callback URL của CRM và đúng chuỗi token ở bước 6.
8. Subscribe ít nhất event `messages` cho Page đang kết nối.
9. Dán toàn bộ giá trị vào `Integration Settings` trong CRM.

#### Map vào CRM

- `facebook.page_id` → `Page ID`
- `facebook.app_id` → `App ID`
- `facebook.app_secret` → `App Secret`
- `facebook.page_access_token` → `Page Access Token`
- `facebook.webhook_verify_token` → chuỗi verify token do CRM tự định nghĩa
- `facebook.send_endpoint` → mặc định là `https://graph.facebook.com/v23.0/me/messages`
- `facebook.inbox_default_branch_code` → mã chi nhánh sẽ nhận hội thoại mới từ Page này

#### Lưu ý khi test và go-live

- App ở chế độ test/development thường chỉ cho tài khoản có role trên app hoặc page test nhắn thử
- Khi go-live production, cần kiểm tra lại chế độ app, quyền page, và các ràng buộc review của Meta nếu có

#### Dấu hiệu cấu hình đúng

- Verify webhook thành công
- Tin nhắn mới từ Page vào được CRM
- Reply outbound từ CRM đi được và Meta trả về `message_id`

## Checklist nhập vào `Integration Settings`

### Zalo OA

- Bật `zalo.enabled`
- Nhập `OA ID`
- Nhập `App ID`
- Nhập `App Secret`
- Nhập `Access Token`
- Nhập `Webhook Verify Token`
- Kiểm tra `Zalo OA send endpoint`
- Chọn `Chi nhánh mặc định cho inbox Zalo`
- Chọn `Polling seconds` phù hợp

### Facebook Messenger

- Bật `facebook.enabled`
- Nhập `Page ID`
- Nhập `App ID`
- Nhập `App Secret`
- Nhập `Page Access Token`
- Nhập `Webhook Verify Token`
- Kiểm tra `Messenger send endpoint`
- Chọn `Chi nhánh mặc định cho inbox Facebook`

## Lỗi setup thường gặp

- Verify webhook fail:
  - callback URL không public HTTPS
  - token nhập ở CRM và provider không giống nhau
  - provider chưa bật trong `Integration Settings`
- Inbound không vào CRM:
  - chưa subscribe webhook event đúng
  - token/app secret/page token hết hạn hoặc sai
  - default branch code không hợp lệ
- Outbound fail:
  - access token/page token không còn hiệu lực
  - send endpoint bị sửa sai
  - page/OA không đủ quyền gửi tin theo policy provider

## Data model

### `conversations`

Giữ state canonical của một luồng chat.

Field quan trọng:

- `provider`
- `channel_key`
- `external_conversation_key`
- `external_user_id`
- `external_display_name`
- `branch_id`
- `customer_id`
- `assigned_to`
- `unread_count`
- `latest_message_preview`
- `last_message_at`
- `handoff_priority`
- `handoff_status`
- `handoff_summary`
- `handoff_next_action_at`
- `handoff_version`

### `conversation_messages`

Giữ từng tin nhắn inbound/outbound.

Field quan trọng:

- `conversation_id`
- `direction`
- `message_type`
- `provider_message_id`
- `source_event_fingerprint`
- `body`
- `status`
- `sent_by_user_id`
- `attempts`
- `next_retry_at`
- `last_error`
- `message_at`

### Webhook event logs

- `zalo_webhook_events`
- `facebook_webhook_events`

Hai bảng này giữ raw event + normalize trace để điều tra duplicate, ignored payload, và lỗi normalize.

## Luồng hệ thống

### Inbound

1. Provider đẩy webhook về controller tương ứng.
2. Controller verify runtime gate + signature/token.
3. Raw event được lưu vào bảng webhook event log.
4. Normalizer chạy inline trong request.
5. Hệ thống tìm hoặc tạo `conversation`.
6. Hệ thống dedupe message theo `conversation_id + provider_message_id`, fallback qua `conversation_id + source_event_fingerprint`.
7. Message hợp lệ được lưu thành `conversation_message`.
8. `conversation` cập nhật preview, unread count, timestamps.
9. Filament page polling sẽ nhìn thấy thread mới sau chu kỳ polling kế tiếp.

### Outbound

1. CSKH nhập reply trên `Conversation Inbox`.
2. CRM tạo `conversation_message` với trạng thái `pending`.
3. Job `SendConversationMessage` dispatch `afterCommit`.
4. `ConversationProviderManager` chọn đúng provider client theo `conversation.provider`.
5. Provider client gửi request ra `Zalo OA` hoặc `Facebook Messenger`.
6. Message chuyển sang `sent` hoặc `failed`.
7. Nếu `failed`, hệ thống giữ error để CSKH retry tay và command scheduler có thể retry tự động khi tới hạn.

### Lead binding

1. CSKH bấm `Tạo lead`.
2. Form prefill dữ liệu từ conversation.
3. CRM tạo `Customer` với `status=lead`.
4. `conversation.customer_id` được bind vào lead mới.
5. Các tin nhắn mới vẫn match conversation trước, nên tự chảy vào đúng lead đã bind.

## Semantics vận hành

### Queue filters

- `Tất cả`: toàn bộ queue theo scope chi nhánh
- `Ưu tiên`: chỉ hội thoại `high` hoặc `urgent`
- `Đến hạn`: hội thoại có `handoff_next_action_at <= now()`
- `Chưa gắn lead`: hội thoại chưa bind `customer_id`
- `Của tôi`: hội thoại có `assigned_to = current user`

### Handoff note

Handoff note là state nội bộ của team CSKH, không đẩy ra provider.

Gồm:

- `priority`
- `status`
- `next action at`
- `summary`

Status hiện có:

- `Mới vào`
- `Đang tư vấn`
- `Đã báo giá`
- `Chờ khách phản hồi`
- `Cần follow-up`

### Claim / phụ trách

- `Claim tôi`: gán hội thoại cho chính người đang thao tác
- `Lưu phụ trách`: đổi assignee ngay trên header thread
- `Nhả claim`: trả hội thoại về queue chung
- Khi CSKH gửi outbound lần đầu, nếu chưa có assignee thì hệ thống auto-claim cho người gửi

### Optimistic lock cho note bàn giao

- `handoff_version` tăng mỗi lần lưu note
- Nếu hai CSKH cùng sửa một thread, lượt lưu cũ hơn sẽ không ghi đè dữ liệu mới
- UI sẽ reload nội dung mới nhất và cảnh báo rằng note đã được người khác cập nhật trước

## Hiệu năng và UX

- Thread chỉ load `30` tin gần nhất ở lần đầu
- Có nút `Xem tin cũ hơn` để mở rộng lịch sử theo batch
- Composer được giữ ở cuối pane chat để tránh phải scroll lại đáy thread
- List pane hiển thị ngay `provider + status + priority + next follow-up`

## Permission và scope

- Permission page: `View:ConversationInbox`
- Roles được backfill permission:
  - `Admin`
  - `Manager`
  - `CSKH`
- Dữ liệu luôn scope theo `BranchAccess`

## Command và automation liên quan

- Retry tự động outbound fail:
  - `php artisan conversations:retry-failed-messages`

## Smoke test nên chạy sau deploy

1. `php artisan migrate`
2. Mở `/admin/conversation-inbox` bằng user `CSKH`
3. Gửi 1 webhook test từ Zalo
4. Xác nhận thread xuất hiện sau polling
5. Gửi reply từ CRM
6. Tạo lead từ thread
7. Gửi thêm 1 webhook follow-up cùng external user
8. Xác nhận follow-up vẫn vào đúng conversation cũ
9. Lặp lại với Facebook Messenger

## Test coverage hiện có

- `tests/Feature/ConversationInboxPageTest.php`
- `tests/Feature/ConversationInboxWebhookFlowTest.php`
- `tests/Feature/FacebookConversationInboxWebhookFlowTest.php`
- `tests/Feature/ConversationInboxCrossProviderDedupingTest.php`
- `tests/Feature/ConversationMessageDeliveryTest.php`
- `tests/Feature/ConversationMessageRetryCommandTest.php`
- `tests/Browser/ConversationInboxBrowserTest.php`

## Code map

- Page:
  - `app/Filament/Pages/ConversationInbox.php`
  - `resources/views/filament/pages/conversation-inbox.blade.php`
- Read / workflow:
  - `app/Services/ConversationInboxReadModelService.php`
  - `app/Services/ConversationLeadBindingService.php`
  - `app/Jobs/SendConversationMessage.php`
  - `app/Services/ConversationProviderManager.php`
- Providers:
  - `app/Services/ZaloInboundMessageNormalizer.php`
  - `app/Services/ZaloOaMessageClient.php`
  - `app/Services/FacebookMessengerInboundMessageNormalizer.php`
  - `app/Services/FacebookMessengerMessageClient.php`
- Webhooks:
  - `app/Http/Controllers/Api/ZaloWebhookController.php`
  - `app/Http/Controllers/Api/FacebookWebhookController.php`

## Related docs

- [Integrations index](README.md)
- [Hướng dẫn CSKH cho Conversation Inbox](../user-guides/conversation-inbox.md)
- [Hướng dẫn Lễ tân / CSKH](../user-guides/frontdesk-cskh.md)
- [Conversation Inbox token rotation runbook](../operations/conversation-inbox-token-rotation.md)

## Official references

- Zalo OA OpenAPI overview:
  - [https://oa.zalo.me/home/function/extension](https://oa.zalo.me/home/function/extension)
- Zalo OA setup / webinar materials:
  - [https://oa.zalo.me/home/resources/news/tai-lieu-webinar-huong-dan-thiet-lap-va-xay-dung-tai-khoan-zalo-official-account-hieu-qua_3280273972853294558](https://oa.zalo.me/home/resources/news/tai-lieu-webinar-huong-dan-thiet-lap-va-xay-dung-tai-khoan-zalo-official-account-hieu-qua_3280273972853294558)
- Meta Messenger app setup:
  - [https://developers.facebook.com/docs/messenger-platform/getting-started/app-setup](https://developers.facebook.com/docs/messenger-platform/getting-started/app-setup)
- Meta Messenger webhook setup:
  - [https://developers.facebook.com/docs/messenger-platform/getting-started/webhook-setup](https://developers.facebook.com/docs/messenger-platform/getting-started/webhook-setup)
- Meta page access tokens:
  - [https://developers.facebook.com/docs/facebook-login/access-tokens/#pagetokens](https://developers.facebook.com/docs/facebook-login/access-tokens/#pagetokens)
