<?php

return [
    /*
    | Default transaction-date window when the UI / CLI omits from/to.
    */
    'default_days_back' => 90,

    /*
    | Flag manual rows when created_at is more than this many calendar days after date.
    */
    'late_entry_days' => 7,

    /*
    | Minimum move/sell counts in the filtered period before a user appears in "frequent" lists.
    */
    'frequent_min_count' => 3,

    /*
    | Exclude Jubelio cron sells/moves from leaderboards by default (user_id -100, submit_type 2).
    */
    'exclude_jubelio_by_default' => true,
];
