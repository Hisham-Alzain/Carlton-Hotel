<?php

return [
    'messages'   => ['success' => 'Success.', 'created' => 'Created successfully.', 'deleted' => 'Deleted successfully.'],
    'errors'     => [
        'server_error'           => 'Something went wrong.',
        'not_found'              => 'Resource not found.',
        'unauthorized'           => 'Unauthenticated.',
        'forbidden'              => 'You do not have permission to perform this action.',
        'validation_failed'      => 'The given data was invalid.',
        'too_many_requests'      => 'Too many requests. Please try again later.',
        'external_service_error' => 'An external service error occurred.',
    ],
    'health'     => ['ok' => 'Service healthy.'],
    'validation' => [
        'required' => 'The :attribute field is required.',
        'string'   => 'The :attribute must be a string.',
        'email'    => 'The :attribute must be a valid email address.',
        'max'      => 'The :attribute may not be greater than :max characters.',
    ],
];
