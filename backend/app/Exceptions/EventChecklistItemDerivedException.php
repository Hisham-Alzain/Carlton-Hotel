<?php

namespace App\Exceptions;

/** Phase 8 (D-04, D-28): the `deposit` checklist item follows the deposit; context `item`. */
class EventChecklistItemDerivedException extends DomainException
{
    public function errorCode(): string { return 'event_checklist_item_derived'; }
    public function statusCode(): int   { return 422; }
}
