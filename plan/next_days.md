use Carbon\Carbon;

$hari = ['selasa', 'sabtu'];

$map = [
    'minggu' => 'Sunday',
    'senin'  => 'Monday',
    'selasa' => 'Tuesday',
    'rabu'   => 'Wednesday',
    'kamis'  => 'Thursday',
    'jumat'  => 'Friday',
    'sabtu'  => 'Saturday',
];

$lastgeneret = Tanggal last generet;

$nextDay = null;

foreach ($hari as $h) {
    $date = $lastgeneret->copy()->next($map[$h]);

    if (!$nextDay || $date->lt($nextDay)) {
        $nextDay = $date;
    }
}

// ⛔ jangan pakai translatedFormat
// hasil langsung Carbon object
var_dump($nextDay);