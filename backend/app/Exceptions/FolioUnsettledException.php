<?php

namespace App\Exceptions;

class FolioUnsettledException extends DomainException
{
    public function errorCode(): string { return 'folio_unsettled'; }
    public function statusCode(): int   { return 422; }
}
