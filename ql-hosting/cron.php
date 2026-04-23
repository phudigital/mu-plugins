<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $settings = qlh_settings();
    $key = (string) ($_GET['key'] ?? '');
    if ($key === '' || !hash_equals((string) ($settings['cron_key'] ?? ''), $key)) {
        http_response_code(403);
        echo "Invalid cron key\n";
        exit;
    }

    $result = qlh_run_reminders(false);
    echo 'OK ' . date('c') . PHP_EOL;
    echo 'Sent: ' . implode(', ', $result['sent']) . PHP_EOL;
    if (!empty($result['skipped'])) {
        echo 'Skipped: ' . implode(' | ', $result['skipped']) . PHP_EOL;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo 'ERROR ' . $e->getMessage() . PHP_EOL;
}
