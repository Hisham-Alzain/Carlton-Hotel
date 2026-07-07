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
    ],
    'health'     => ['ok' => 'الخدمة تعمل بشكل سليم.'],
    'validation' => [
        'required' => 'حقل :attribute مطلوب.',
        'string'   => 'يجب أن يكون :attribute نصاً.',
        'email'    => 'يجب أن يكون :attribute بريداً إلكترونياً صالحاً.',
        'max'      => 'يجب ألا يتجاوز :attribute :max حرفاً.',
    ],
];
