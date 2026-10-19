<?php

/*
 | Feature catalog. Add a new feature here and it automatically appears
 | in the admin panel (plan checkboxes + per-landlord On/Off override).
 | In code, gate a feature with: auth()->user()->hasFeature('sms_reminders')
 */
return [

    'catalog' => [
        'multiple_properties' => 'একাধিক বাড়ি (Multiple properties)',
        'utility_bills' => 'গ্যাস/পানি/বিদ্যুৎ বিল (Utility bills)',
        'expense_tracking' => 'খরচের হিসাব (Expenses)',
        'receipt_pdf' => 'রসিদ PDF (Receipts)',
        'reports' => 'মাসিক রিপোর্ট (Reports)',
        'sms_reminders' => 'SMS reminder',
        'bkash_collection' => 'অনলাইন পেমেন্ট: bKash/Nagad/কার্ড/ব্যাংক',
    ],

    // Features for a landlord who has no plan assigned yet.
    'defaults' => ['utility_bills'],

    // Plan given automatically to every new landlord (self-registered or admin-created).
    'default_plan' => 'Free',

    // Unit limit for a landlord with no plan and no custom limit.
    'no_plan_unit_limit' => 2,
];
