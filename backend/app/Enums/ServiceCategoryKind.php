<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * How the mobile client renders a service category, and what tapping it does.
 *
 * Keeping this in data rather than in client code means a new category never
 * needs an app release: the catalog payload tells the app which of the four
 * behaviors to use.
 */
enum ServiceCategoryKind: string
{
    use HasValues;

    /** Has selectable microservices — show the list, then request one. */
    case CATALOG = 'catalog';

    /** No visible microservices — request the hidden default item immediately. */
    case DIRECT = 'direct';

    /** Deep-links into a richer existing module (dining, transfers). No request row. */
    case LINK = 'link';

    /** Guest-controlled state on the stay rather than work for staff (do-not-disturb). */
    case TOGGLE = 'toggle';
}
