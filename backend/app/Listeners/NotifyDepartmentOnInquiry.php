<?php

namespace App\Listeners;

use App\Events\InquirySubmitted;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyDepartmentOnInquiry implements ShouldQueue
{
    public function handle(InquirySubmitted $event): void
    {
        // Wired in P9 — push/email notification dispatched to the assigned department queue.
    }
}
