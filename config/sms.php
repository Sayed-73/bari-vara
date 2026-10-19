<?php

return [

    // log = only writes to storage/logs/laravel.log (safe for development)
    // bulksmsbd = real SMS via bulksmsbd.net
    'driver' => env('SMS_DRIVER', 'log'),

    // en = English text (works everywhere), bn = Bangla (check Unicode support with your SMS provider first)
    'language' => env('SMS_LANGUAGE', 'en'),

    'drivers' => [
        'bulksmsbd' => [
            'base_url' => env('BULKSMSBD_BASE_URL', 'http://bulksmsbd.net'),
            'api_key' => env('BULKSMSBD_API_KEY'),
            'sender_id' => env('BULKSMSBD_SENDER_ID'),
        ],
    ],

    // Days relative to the due date: negative = before, 0 = due day, positive = days overdue.
    'reminder_days' => [-2, 0, 3, 7],

    'types' => [
        'rent' => 'rent',
        'gas' => 'gas bill',
        'water' => 'water bill',
        'electricity' => 'electricity bill',
        'service' => 'service charge',
        'other' => 'bill',
    ],

    'templates' => [
        'en' => [
            'upcoming' => 'Dear {name}, {type} BDT {amount} for {month} is due on {date}. Details/pay: {link} - {landlord}',
            'due' => 'Dear {name}, {type} BDT {amount} for {month} is due today. Details/pay: {link} - {landlord}',
            'overdue' => 'Dear {name}, {type} BDT {amount} for {month} was due on {date}. Please pay soon: {link} - {landlord}',
            'receipt' => 'Received BDT {amount} for {type} {month}. Receipt: {link} - {landlord}',
        ],
        'bn' => [
            'upcoming' => 'প্রিয় {name}, {month} মাসের {type} ৳{amount} {date} তারিখে বাকি। পেমেন্ট/বিস্তারিত: {link} - {landlord}',
            'due' => 'প্রিয় {name}, {month} মাসের {type} ৳{amount} আজ বাকি। পেমেন্ট/বিস্তারিত: {link} - {landlord}',
            'overdue' => 'প্রিয় {name}, {month} মাসের {type} ৳{amount} {date} তারিখে বাকি ছিল। দ্রুত পরিশোধ করুন: {link} - {landlord}',
            'receipt' => '৳{amount} পেয়েছি ({month} {type})। রসিদ: {link} - {landlord}',
        ],
    ],
];
