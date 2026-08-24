<?php

declare(strict_types=1);

return [
    'greeting' => 'Hello :name,',

    'welcome_subject' => 'Welcome to the platform',
    'welcome_title' => 'Welcome',
    'welcome_intro' => 'Your account has been created. These are your access credentials:',
    'email_label' => 'Email address',
    'password_label' => 'Temporary password',
    'must_change_password' => 'For security reasons you must change this password the first time you sign in.',
    'login_button' => 'Sign in',
    'login_url_label' => 'Or go directly to:',

    'test_subject' => 'Test email',
    'test_body' => 'This is a test email sent from the system settings. If you received it, mail delivery is working.',
    'test_sent_at' => 'Sent on :datetime.',

    'holiday_reminder_subject' => 'Pending holiday capture for :year',
    'holiday_reminder_title' => 'Holidays :year',
    'holiday_reminder_heading' => 'The :year holidays have not been captured yet.',
    'holiday_reminder_body' => 'The holiday calendar determines the next business day used to calculate the exchange rate. Without the :year holidays, the year-end calculation may pick the wrong date.',
    'holiday_reminder_notice' => 'This reminder is sent daily until the first holiday of the year is captured.',
    'holiday_reminder_button' => 'Capture holidays',

    'footer_support' => 'Questions? Write to',
    'footer_rights' => 'All rights reserved.',
    'override_notice' => 'This email was generated in test mode. In production it would have been sent to :recipient.',
];
