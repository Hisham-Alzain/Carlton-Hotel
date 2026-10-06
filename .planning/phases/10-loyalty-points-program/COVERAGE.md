# API Coverage — Firebase Cloud Messaging (via NotificationService)

> Full coverage by default. Opt-outs are explicit, reasoned decisions.

The api-coverage detector fired on first-party wording ("Settings API" in the ROADMAP plan list), not on a new external integration. Phase 10 integrates no new external API. The only third-party service on a Phase 10 path is FCM, reached through the existing Phase 4 `NotificationService::pushToGuest` from `NotifyExpiringLoyaltyPointsAction` (10-13; 10-17 only narrows which guests are warned).

| capability | decision | reason |
|---|---|---|
| push_to_guest_device_tokens | INTEGRATE | |
| localized_title_and_body | INTEGRATE | |
| data_payload | INTEGRATE | |
| topic_or_broadcast_push | OPT-OUT | not needed: loyalty expiry warnings are per guest |
| provider_scheduled_send | OPT-OUT | not needed: scheduling is the Laravel scheduler in routes/console.php |
| delivery_receipts | OPT-OUT | explicitly out of scope: at-least-once with a per-batch marker; real-device delivery is a human verification item |
