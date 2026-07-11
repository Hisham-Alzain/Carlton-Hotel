<?php

namespace App\Events;

use App\Models\EventInquiry;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InquirySubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly EventInquiry $inquiry) {}
}
