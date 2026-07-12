<?php

namespace App\Http\Requests\Service;

use App\Base\BaseRequest;

class SubmitDocumentsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'documents'          => ['required', 'array', 'min:1'],
            'documents.*.type'   => ['required', 'string', 'max:255'],
            'documents.*.file'   => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ];
    }
}
