<?php

namespace App\Filters;

/** Sliders have no `name`/`title`; the editor-facing label is `header_text`. */
class HomeSliderFilter extends CmsContentFilter
{
    protected array $translatable = ['header_text', 'location'];
}
