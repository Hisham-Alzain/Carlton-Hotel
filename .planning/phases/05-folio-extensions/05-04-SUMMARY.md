---
phase: 05-folio-extensions
plan: 04
subsystem: credits + floors
requires: [05-03]
provides: [credit-path, item-floor, balance-floor, uuid-mapping, prohibited_unless-mapping]
key-files:
  created:
    - backend/app/Exceptions/FolioCreditExceedsItemException.php
    - backend/app/Exceptions/FolioCreditExceedsBalanceException.php
  modified:
    - backend/app/Http/Requests/Folio/PostFolioItemRequest.php
    - backend/app/Actions/Folio/PostFolioItemAction.php
    - backend/app/Base/BaseRequest.php
completed: 2026-09-26
---

# 05-04 — Credits and floors (FOLIO-02)

## Shipped
- `PostFolioItemRequest`: `kind` in:charge,credit; `reason` required_if:kind,credit; `reverses_item_uuid` nullable|uuid|prohibited_unless:kind,credit|`Rule::exists('folio_items','uuid')->where('folio_id', $this->route('folio')?->id)`.
- `PostFolioItemAction` credit path: amount = `bcsub('0', lineTotal)`, source_type credit, `reverses_item_id` stored; replay comparison includes the reversed item uuid. Inside the guarded write, after the settled guard: item floor first (`FolioCreditExceedsItemException`, context item_uuid, remaining_usd, amount_usd), then balance floor (`FolioCreditExceedsBalanceException`, context balance_due_usd, amount_usd). Reversing a credit row always fails the item floor (its amount is negative).
- `BaseRequest::messages()['uuid']`, `['prohibited_unless']`.

## Deviations from PLAN.md
- none (FolioLineItemTest credit cases are QA's).

## Flagged assumptions
- FA-5.04-1 confirmed (context shapes; amounts positive 2dp strings, remaining_usd may be negative when reversing a credit). FA-5.04-2 confirmed. FA-5.04-3 confirmed.

## Carry-forwards
- Real Laravel messages checked with tinker: uuid `The x field must be a valid UUID.`; prohibited_unless `The r field is prohibited unless kind is in credit.`
- Engineer smoke: remaining 9.00 refuses 9.01 and accepts 9.00; goodwill 280.01 over a 280.00 balance refused with balance 280.00.
