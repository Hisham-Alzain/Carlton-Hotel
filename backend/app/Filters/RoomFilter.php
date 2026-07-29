<?php

namespace App\Filters;

/**
 * Rooms carry no translatable text and no `sort_order` — the list is keyed by
 * the physical room number.
 */
class RoomFilter extends CmsContentFilter
{
    protected array $safeParms = [
        'status' => ['eq', 'in'],
        'floor'  => ['eq'],
    ];

    protected array $searchable = ['number'];

    protected array $sortable = ['number', 'floor', 'created_at', 'updated_at'];
}
