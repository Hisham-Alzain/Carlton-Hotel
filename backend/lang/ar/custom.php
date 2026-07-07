<?php

return [
    'messages'   => ['success' => 'تمت العملية بنجاح.', 'created' => 'تم الإنشاء بنجاح.', 'deleted' => 'تم الحذف بنجاح.'],
    'errors'     => [
        'server_error'           => 'حدث خطأ ما.',
        'not_found'              => 'المورد غير موجود.',
        'unauthorized'           => 'غير مصادق عليه.',
        'forbidden'              => 'ليس لديك صلاحية لتنفيذ هذا الإجراء.',
        'validation_failed'      => 'البيانات المدخلة غير صالحة.',
        'too_many_requests'      => 'طلبات كثيرة جداً. يرجى المحاولة لاحقاً.',
        'external_service_error' => 'حدث خطأ في خدمة خارجية.',
        'otp_expired'            => 'انتهت صلاحية رمز التحقق.',
        'otp_invalid'            => 'رمز التحقق غير صحيح.',
        'otp_locked'             => 'محاولات خاطئة كثيرة. يرجى طلب رمز جديد.',
        'credentials_invalid'    => 'بيانات الاعتماد المقدمة غير صحيحة.',
        'account_inactive'       => 'تم تعطيل هذا الحساب.',
        'identity_required'      => 'يرجى تقديم رقم هاتف أو بريد إلكتروني.',
        'booking_link_failed'    => 'تعذر التحقق من تفاصيل الحجز.',
    ],
    'auth'       => [
        'otp_sent'       => 'تم إرسال رمز التحقق.',
        'logged_in'      => 'تم تسجيل الدخول بنجاح.',
        'logged_out'     => 'تم تسجيل الخروج بنجاح.',
        'otp_verified'   => 'تم التحقق بنجاح.',
        'booking_linked' => 'تم ربط الحجز بنجاح.',
    ],
    'health'     => ['ok' => 'الخدمة تعمل بشكل سليم.'],
    'validation' => [
        'required'      => 'حقل :attribute مطلوب.',
        'string'        => 'يجب أن يكون :attribute نصاً.',
        'email'         => 'يجب أن يكون :attribute بريداً إلكترونياً صالحاً.',
        'max'           => 'يجب ألا يتجاوز :attribute :max حرفاً.',
        'phone_invalid' => 'رقم الهاتف غير صالح.',
        'in'            => 'القيمة المحددة لـ :attribute غير صالحة.',
    ],
];
