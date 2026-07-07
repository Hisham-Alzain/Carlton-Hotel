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
        'otp_expired'            => 'The verification code has expired.',
        'otp_invalid'            => 'The verification code is invalid.',
        'otp_locked'             => 'Too many failed attempts. Please request a new code.',
        'credentials_invalid'    => 'The provided credentials are incorrect.',
        'account_inactive'       => 'This account has been deactivated.',
        'identity_required'      => 'Please provide a phone number or email address.',
        'booking_link_failed'    => 'The booking details could not be verified.',
    ],
    'auth'       => [
        'otp_sent'       => 'A verification code has been sent.',
        'logged_in'      => 'Logged in successfully.',
        'logged_out'     => 'Logged out successfully.',
        'otp_verified'   => 'Verification successful.',
        'booking_linked' => 'Booking linked successfully.',
    ],
    'health'     => ['ok' => 'Service healthy.'],
    'validation' => [
        'required'      => 'The :attribute field is required.',
        'string'        => 'The :attribute must be a string.',
        'email'         => 'The :attribute must be a valid email address.',
        'max'           => 'The :attribute may not be greater than :max characters.',
        'phone_invalid' => 'The phone number is not valid.',
        'in'            => 'The selected :attribute is invalid.',
    ],
];
