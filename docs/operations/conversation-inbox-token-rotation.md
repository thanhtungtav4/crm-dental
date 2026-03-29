# Conversation Inbox Token Rotation Runbook

Cap nhat: 2026-03-29

Tai lieu nay huong dan rotate token cho `Zalo OA` va `Facebook Messenger` dang duoc dung boi `Conversation Inbox`.

## 1. Muc tieu

- Doi token chu dong theo cadence bao mat
- Giam downtime inbound/outbound khi rotate
- Co rollback nhanh neu verify webhook hoac gui tin that bai

## 2. Scope

Runbook nay cover:

- `zalo.access_token`
- `zalo.webhook_token`
- `facebook.page_access_token`
- `facebook.webhook_verify_token`

Khong cover:

- ZNS token
- EMR / Google Calendar token
- app secret rotation toan he thong

## 3. Nguyen tac van hanh

- Luon rotate tren tung provider mot, khong rotate dong thoi Zalo va Facebook neu khong can thiet.
- Neu rotate `verify token`, phai cap nhat `CRM` va `provider webhook config` trong cung mot cua so thao tac.
- Neu rotate `access token` / `page access token`, uu tien cap nhat token moi trong CRM truoc khi xoa token cu ben provider.
- Sau moi lan rotate, phai chay smoke test inbound va outbound.
- Neu smoke test fail, rollback ngay ve token cu neu token cu con hieu luc.

## 4. Kiem tra truoc khi rotate

1. Xac nhan ai la nguoi duoc phep thao tac voi OA / Meta App / Facebook Page.
2. Xac nhan co quyen vao `Integration Settings` trong CRM.
3. Xac nhan callback URL webhook hien tai dang dung.
4. Chup lai hoac luu secure note cac gia tri hien tai:
   - token dang dung
   - verify token dang dung
   - page/OA dang gan
   - chi nhanh mac dinh cua kenh
5. Chon cua so thoi gian it tai de neu co gap verify fail thi blast radius nho hon.

## 5. Rotate Zalo OA

### 5.1 Rotate `Access Token`

1. Vao Zalo OA / OpenAPI console.
2. Tao hoac lay `Access Token` moi.
3. Vao CRM `Integration Settings`.
4. Cap nhat `zalo.access_token`.
5. Luu settings.
6. Giu nguyen `zalo.send_endpoint`, `zalo.oa_id`, `zalo.app_id`, `zalo.app_secret`, `zalo.inbox_default_branch_code`.
7. Chay smoke test Zalo inbound + outbound.

### 5.2 Rotate `Webhook Verify Token`

1. Tao chuoi random moi, dai va kho doan.
2. Vao CRM `Integration Settings`.
3. Cap nhat `zalo.webhook_token`, nhung chua dong man hinh provider.
4. Vao man hinh webhook cua Zalo OA.
5. Cap nhat cung callback URL cu va token moi.
6. Xac nhan verify webhook thanh cong.
7. Luu settings neu ban dang thao tac song song tren CRM.
8. Chay smoke test inbound Zalo.

### 5.3 Dinh nghia thanh cong

- Verify webhook pass
- Webhook moi vao duoc `Conversation Inbox`
- Reply tu CRM di duoc va message chuyen `Đã gửi`

## 6. Rotate Facebook Messenger

### 6.1 Rotate `Page Access Token`

1. Vao `Meta for Developers`.
2. Mo app dang gan voi `Facebook Page`.
3. Generate hoac copy `Page Access Token` moi.
4. Vao CRM `Integration Settings`.
5. Cap nhat `facebook.page_access_token`.
6. Luu settings.
7. Giu nguyen `facebook.page_id`, `facebook.app_id`, `facebook.app_secret`, `facebook.send_endpoint`, `facebook.inbox_default_branch_code`.
8. Chay smoke test Facebook inbound + outbound.

### 6.2 Rotate `Webhook Verify Token`

1. Tao chuoi random moi.
2. Vao CRM `Integration Settings`.
3. Cap nhat `facebook.webhook_verify_token`.
4. Vao `Meta Webhooks` cho Messenger/Page.
5. Cap nhat verify token moi voi cung callback URL hien tai.
6. Xac nhan challenge verify thanh cong.
7. Subscribe event `messages` van con duoc gan cho Page.
8. Chay smoke test inbound Facebook.

### 6.3 Dinh nghia thanh cong

- Verify webhook pass
- Tin nhan moi tu Page vao duoc CRM
- Reply tu CRM di duoc va provider tra `message_id`

## 7. Luu y runtime cua CRM

- `ClinicSetting` duoc doc theo request/job va chi cache trong pham vi request hien tai.
- Vi vay:
  - webhook request moi se doc token moi
  - job gui tin moi se doc token moi
- Mac dinh khong can `queue:restart` chi vi rotate token cho Conversation Inbox.
- Chi can restart queue neu ban dang co mot operational reason khac khien worker phai nap lai process state.

## 8. Smoke test sau rotate

### 8.1 Zalo

1. Gui 1 tin test vao OA.
2. Xac nhan thread xuat hien trong `/admin/conversation-inbox`.
3. Reply tu CRM.
4. Xac nhan message chuyen `Đã gửi`.

### 8.2 Facebook

1. Gui 1 tin test vao Page Messenger.
2. Xac nhan thread xuat hien trong `/admin/conversation-inbox`.
3. Reply tu CRM.
4. Xac nhan message chuyen `Đã gửi`.

### 8.3 Kiem tra them neu co loi

- xem `last_error` tren bubble outbound fail
- xem bang `zalo_webhook_events` hoac `facebook_webhook_events`
- xac nhan provider van dang `enabled`
- xac nhan chi nhanh mac dinh cua kenh van hop le

## 9. Rollback

### 9.1 Khi nao rollback ngay

- verify webhook fail va khong fix duoc trong cua so thao tac
- inbound khong vao CRM sau rotate
- outbound fail lien tuc do `unauthorized`, `invalid token`, `signature mismatch`

### 9.2 Cach rollback

1. Dat lai token cu trong `Integration Settings`.
2. Neu da doi `verify token`, dat lai cung gia tri token cu ben provider webhook config.
3. Luu settings.
4. Chay lai smoke test inbound/outbound.
5. Ghi nhan incident va nguyen nhan de rotate lai sau.

## 10. Audit note sau thao tac

Sau moi lan rotate, nen ghi lai:

- thoi diem rotate
- nguoi thao tac
- provider nao da rotate
- rotate token nao
- smoke test da pass hay fail
- rollback co xay ra hay khong

## 11. Link lien quan

- [Conversation Inbox Integration](../integrations/conversation-inbox.md)
- [Production Operations Runbook](production-operations-runbook.md)
