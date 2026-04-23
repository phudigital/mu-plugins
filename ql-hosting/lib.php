<?php
declare(strict_types=1);

const QLH_DEFAULT_SETTINGS = [
    'username' => 'phudigital',
    'password_hash' => '$2y$10$9u65wb9cjlE4fclgNWjuieCGng9T26mU1o70UIq/8Vpm.R.MAIom6',
    'telegram' => [
        'enabled' => false,
        'bot_token' => '',
        'chat_id' => '',
    ],
    'reminders' => [
        'days' => [30, 14, 7, 3, 1, 0],
        'notify_overdue' => true,
        'repeat_after_days' => 1,
    ],
    'notification_log' => [],
    'cron_key' => '',
];

function qlh_config(): array
{
    $default = [
        'brand_file' => __DIR__ . '/brand.json',
        'settings_file' => __DIR__ . '/data/settings.json',
        'backup_dir' => __DIR__ . '/data/backups',
        'timezone' => 'Asia/Ho_Chi_Minh',
    ];

    $customFile = __DIR__ . '/config.php';
    if (is_file($customFile)) {
        $custom = require $customFile;
        if (is_array($custom)) {
            $default = array_replace($default, $custom);
        }
    }

    date_default_timezone_set((string) ($default['timezone'] ?? 'Asia/Ho_Chi_Minh'));
    return $default;
}

function qlh_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function qlh_read_json_file(string $path, array $fallback = []): array
{
    if (!is_file($path)) {
        return $fallback;
    }

    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return $fallback;
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException("Không đọc được JSON: {$path}");
    }

    return $data;
}

function qlh_write_json_file(string $path, array $data, bool $backup = false): void
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException("Không tạo được thư mục: {$dir}");
    }

    if ($backup && is_file($path)) {
        $config = qlh_config();
        $backupDir = (string) $config['backup_dir'];
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        $name = basename($path, '.json') . '-' . date('Ymd-His') . '.json';
        copy($path, rtrim($backupDir, '/') . '/' . $name);
    }

    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Không encode được JSON.');
    }

    $fp = fopen($path, 'c+');
    if (!$fp) {
        throw new RuntimeException("Không mở được file để ghi: {$path}");
    }

    try {
        if (!flock($fp, LOCK_EX)) {
            throw new RuntimeException('Không khóa được file JSON.');
        }
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, $json . PHP_EOL);
        fflush($fp);
        flock($fp, LOCK_UN);
    } finally {
        fclose($fp);
    }
}

function qlh_settings(): array
{
    $config = qlh_config();
    $settings = qlh_read_json_file((string) $config['settings_file'], QLH_DEFAULT_SETTINGS);
    return array_replace_recursive(QLH_DEFAULT_SETTINGS, $settings);
}

function qlh_save_settings(array $settings): void
{
    $config = qlh_config();
    qlh_write_json_file((string) $config['settings_file'], array_replace_recursive(QLH_DEFAULT_SETTINGS, $settings));
}

function qlh_setup_required(): bool
{
    $settings = qlh_settings();
    return empty($settings['password_hash']);
}

function qlh_normalize_username(string $username): string
{
    return mb_strtolower(trim($username), 'UTF-8');
}

function qlh_require_auth(): void
{
    if (qlh_setup_required()) {
        qlh_json_response(['ok' => false, 'setup_required' => true, 'message' => 'Cần tạo mật khẩu quản trị.'], 403);
    }

    if (empty($_SESSION['qlh_auth'])) {
        qlh_json_response(['ok' => false, 'message' => 'Bạn cần đăng nhập.'], 401);
    }
}

function qlh_request_json(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        qlh_json_response(['ok' => false, 'message' => 'Payload không phải JSON hợp lệ.'], 400);
    }

    return $data;
}

function qlh_brand_file(): string
{
    $config = qlh_config();
    return (string) $config['brand_file'];
}

function qlh_brand_data(): array
{
    $data = qlh_read_json_file(qlh_brand_file(), []);
    if (!$data) {
        $data = [
            'company' => '',
            'address' => '',
            'website' => '',
            'logo' => '',
            'updated_at' => date('Y-m-d'),
            'notify' => qlh_empty_notify(),
            'contacts' => [],
            'domains' => [],
        ];
    }
    $data['notify'] = qlh_normalize_notify($data['notify'] ?? []);
    $data['contacts'] = array_values($data['contacts'] ?? []);
    $data['domains'] = is_array($data['domains'] ?? null) ? $data['domains'] : [];
    return $data;
}

function qlh_empty_notify(): array
{
    return [
        'active' => false,
        'type' => 'info',
        'message' => '',
        'button_text' => '',
        'button_url' => '',
    ];
}

function qlh_normalize_notify(mixed $notify): array
{
    $notify = is_array($notify) ? $notify : [];
    $type = (string) ($notify['type'] ?? 'info');
    if (!in_array($type, ['info', 'warning', 'error', 'success'], true)) {
        $type = 'info';
    }

    return [
        'active' => (bool) ($notify['active'] ?? false),
        'type' => $type,
        'message' => trim((string) ($notify['message'] ?? '')),
        'button_text' => trim((string) ($notify['button_text'] ?? '')),
        'button_url' => trim((string) ($notify['button_url'] ?? '')),
    ];
}

function qlh_normalize_date_string(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $matches)) {
        [$_, $day, $month, $year] = $matches;
        if (checkdate((int) $month, (int) $day, (int) $year)) {
            return sprintf('%04d-%02d-%02d', (int) $year, (int) $month, (int) $day);
        }
    }

    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches)) {
        [$_, $year, $month, $day] = $matches;
        if (checkdate((int) $month, (int) $day, (int) $year)) {
            return sprintf('%04d-%02d-%02d', (int) $year, (int) $month, (int) $day);
        }
    }

    return '';
}

function qlh_normalize_brand(array $data): array
{
    $normalized = [
        'company' => trim((string) ($data['company'] ?? '')),
        'address' => trim((string) ($data['address'] ?? '')),
        'website' => trim((string) ($data['website'] ?? '')),
        'logo' => trim((string) ($data['logo'] ?? '')),
        'updated_at' => qlh_normalize_date_string((string) ($data['updated_at'] ?? '')) ?: date('Y-m-d'),
        'notify' => qlh_normalize_notify($data['notify'] ?? []),
        'contacts' => [],
        'domains' => [],
    ];

    foreach (($data['contacts'] ?? []) as $contact) {
        if (!is_array($contact)) {
            continue;
        }
        $row = [
            'label' => trim((string) ($contact['label'] ?? '')),
            'phone' => trim((string) ($contact['phone'] ?? '')),
            'display' => trim((string) ($contact['display'] ?? '')),
            'link_url' => trim((string) ($contact['link_url'] ?? '')),
            'email' => trim((string) ($contact['email'] ?? '')),
            'url' => trim((string) ($contact['url'] ?? '')),
        ];
        $row = array_filter($row, static fn ($value) => $value !== '');
        if ($row) {
            $normalized['contacts'][] = $row;
        }
    }

    $domains = $data['domains'] ?? [];
    if (is_array($domains)) {
        ksort($domains, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($domains as $domain => $info) {
            $domain = strtolower(trim((string) $domain));
            if ($domain === '') {
                continue;
            }
            $info = is_array($info) ? $info : [];
            $normalized['domains'][$domain] = [
                'expire' => qlh_normalize_date_string((string) ($info['expire'] ?? '')),
                'hosting_note' => trim((string) ($info['hosting_note'] ?? '')),
                'notify' => qlh_normalize_notify($info['notify'] ?? []),
            ];
        }
    }

    return $normalized;
}

function qlh_public_settings(array $settings): array
{
    $masked = $settings;
    $token = (string) ($masked['telegram']['bot_token'] ?? '');
    $masked['telegram']['bot_token_masked'] = $token === '' ? '' : substr($token, 0, 6) . '...' . substr($token, -4);
    unset($masked['telegram']['bot_token'], $masked['password_hash'], $masked['notification_log']);
    return $masked;
}

function qlh_send_telegram(string $text, array $settings): array
{
    $telegram = $settings['telegram'] ?? [];
    $token = trim((string) ($telegram['bot_token'] ?? ''));
    $chatId = trim((string) ($telegram['chat_id'] ?? ''));

    if ($token === '' || $chatId === '') {
        return ['ok' => false, 'message' => 'Thiếu bot token hoặc chat id Telegram.'];
    }

    $url = 'https://api.telegram.org/bot' . rawurlencode($token) . '/sendMessage';
    $payload = http_build_query([
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => '1',
    ]);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $payload,
            'timeout' => 12,
        ],
    ]);

    $result = @file_get_contents($url, false, $context);
    if ($result === false) {
        return ['ok' => false, 'message' => 'Không gọi được Telegram API.'];
    }

    $decoded = json_decode($result, true);
    return [
        'ok' => (bool) ($decoded['ok'] ?? false),
        'message' => (bool) ($decoded['ok'] ?? false) ? 'Đã gửi Telegram.' : ($decoded['description'] ?? 'Telegram trả về lỗi.'),
    ];
}

function qlh_days_until(string $date): ?int
{
    $date = qlh_normalize_date_string($date);
    if ($date === '') {
        return null;
    }
    $today = new DateTimeImmutable('today');
    $target = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$target) {
        return null;
    }

    return (int) $today->diff($target)->format('%r%a');
}

function qlh_run_reminders(bool $dryRun = false): array
{
    $settings = qlh_settings();
    $brand = qlh_brand_data();
    $telegram = $settings['telegram'] ?? [];
    $reminders = $settings['reminders'] ?? QLH_DEFAULT_SETTINGS['reminders'];
    $daysList = array_map('intval', (array) ($reminders['days'] ?? []));
    $repeatAfter = max(1, (int) ($reminders['repeat_after_days'] ?? 1));
    $log = is_array($settings['notification_log'] ?? null) ? $settings['notification_log'] : [];
    $sent = [];
    $skipped = [];

    if (empty($telegram['enabled'])) {
        return ['ok' => true, 'sent' => [], 'skipped' => ['Telegram đang tắt.']];
    }

    foreach (($brand['domains'] ?? []) as $domain => $info) {
        $expire = (string) ($info['expire'] ?? '');
        $days = qlh_days_until($expire);
        if ($days === null) {
            continue;
        }

        $shouldSend = in_array($days, $daysList, true);
        if ($days < 0 && !empty($reminders['notify_overdue'])) {
            $shouldSend = true;
        }
        if (!$shouldSend) {
            continue;
        }

        $fingerprint = $domain . '|' . $expire . '|' . ($days < 0 ? 'overdue' : $days);
        $lastSent = $log[$fingerprint] ?? '';
        if ($lastSent) {
            $last = DateTimeImmutable::createFromFormat(DATE_ATOM, (string) $lastSent);
            if ($last && $last->modify('+' . $repeatAfter . ' days') > new DateTimeImmutable('now')) {
                $skipped[] = "{$domain}: đã gửi gần đây";
                continue;
            }
        }

        $status = $days < 0 ? 'đã hết hạn ' . abs($days) . ' ngày' : 'còn ' . $days . ' ngày';
        $note = trim((string) ($info['hosting_note'] ?? ''));
        $message = "PDL Hosting\nDomain: {$domain}\nHạn: {$expire}\nTrạng thái: {$status}";
        if ($note !== '') {
            $message .= "\nGhi chú: {$note}";
        }

        $result = $dryRun ? ['ok' => true, 'message' => 'Dry run'] : qlh_send_telegram(htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $settings);
        if ($result['ok']) {
            $log[$fingerprint] = (new DateTimeImmutable('now'))->format(DATE_ATOM);
            $sent[] = $domain;
        } else {
            $skipped[] = "{$domain}: {$result['message']}";
        }
    }

    if (!$dryRun) {
        $settings['notification_log'] = $log;
        qlh_save_settings($settings);
    }

    return ['ok' => true, 'sent' => $sent, 'skipped' => $skipped];
}
