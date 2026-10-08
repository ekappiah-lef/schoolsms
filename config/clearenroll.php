<?php

/*
 | ClearEnroll (school-to-school fee clearance) connection.
 | The key is created in ClearEnroll under "School System Key" by the school's admin.
 | Keep both in .env only:
 |   CLEARENROLL_API_URL=https://clearenrollportal.com/api/integration/v1
 |   CLEARENROLL_API_KEY=ce_live_…
 */
return [
    'url' => rtrim((string) env('CLEARENROLL_API_URL', ''), '/'),
    'key' => env('CLEARENROLL_API_KEY'),
    'timeout' => (int) env('CLEARENROLL_TIMEOUT', 15),
];
