<?php

/* Shown in the app (Help & Feedback page), the 403 page, legal pages and the public landing page. */
return [
    'company' => env('COMPANY_NAME', 'Bari Bhara Manager'),
    'address' => env('COMPANY_ADDRESS'),
    'phone' => env('SUPPORT_PHONE'),          // 01XXXXXXXXX
    'whatsapp' => env('SUPPORT_WHATSAPP'),    // 01XXXXXXXXX
    'email' => env('SUPPORT_EMAIL'),          // new feedback is also mailed here
    'hours' => env('SUPPORT_HOURS', 'সকাল ১০টা – রাত ৮টা'),
    'legal_updated' => env('LEGAL_UPDATED', '২১ অক্টোবর ২০২৬'),
];
