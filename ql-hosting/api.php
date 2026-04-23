<?php
declare(strict_types=1);

session_start();
require __DIR__ . '/lib.php';

try {
    $action = (string) ($_GET['action'] ?? '');
    $payload = qlh_request_json();

    if ($action === 'status') {
        qlh_json_response([
            'ok' => true,
            'authenticated' => !empty($_SESSION['qlh_auth']),
            'setup_required' => qlh_setup_required(),
        ]);
    }

    if ($action === 'setup') {
        if (!qlh_setup_required()) {
            qlh_json_response(['ok' => false, 'message' => 'App đã được thiết lập.'], 409);
        }
        $username = qlh_normalize_username((string) ($payload['username'] ?? 'phudigital'));
        $password = (string) ($payload['password'] ?? '');
        if ($username === '') {
            qlh_json_response(['ok' => false, 'message' => 'Bạn cần nhập tài khoản.'], 422);
        }
        if (mb_strlen($password) < 8) {
            qlh_json_response(['ok' => false, 'message' => 'Mật khẩu cần tối thiểu 8 ký tự.'], 422);
        }
        $settings = qlh_settings();
        $settings['username'] = $username;
        $settings['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        $settings['cron_key'] = bin2hex(random_bytes(24));
        qlh_save_settings($settings);
        $_SESSION['qlh_auth'] = true;
        qlh_json_response(['ok' => true, 'message' => 'Đã tạo mật khẩu quản trị.']);
    }

    if ($action === 'login') {
        $settings = qlh_settings();
        $username = qlh_normalize_username((string) ($payload['username'] ?? 'phudigital'));
        $password = (string) ($payload['password'] ?? '');
        if (!hash_equals(qlh_normalize_username((string) ($settings['username'] ?? 'phudigital')), $username) || !password_verify($password, (string) ($settings['password_hash'] ?? ''))) {
            qlh_json_response(['ok' => false, 'message' => 'Tài khoản hoặc mật khẩu không đúng.'], 401);
        }
        $_SESSION['qlh_auth'] = true;
        qlh_json_response(['ok' => true, 'message' => 'Đã đăng nhập.']);
    }

    if ($action === 'logout') {
        $_SESSION = [];
        session_destroy();
        qlh_json_response(['ok' => true, 'message' => 'Đã đăng xuất.']);
    }

    qlh_require_auth();

    if ($action === 'data') {
        $brandFile = qlh_brand_file();
        qlh_json_response([
            'ok' => true,
            'brand' => qlh_brand_data(),
            'settings' => qlh_public_settings(qlh_settings()),
            'brand_writable' => is_writable($brandFile) || (!file_exists($brandFile) && is_writable(dirname($brandFile))),
            'brand_file' => $brandFile,
        ]);
    }

    if ($action === 'save-brand') {
        $brand = qlh_normalize_brand((array) ($payload['brand'] ?? []));
        qlh_write_json_file(qlh_brand_file(), $brand, true);
        qlh_json_response(['ok' => true, 'message' => 'Đã lưu brand.json.', 'brand' => $brand]);
    }

    if ($action === 'save-settings') {
        $settings = qlh_settings();
        $incoming = (array) ($payload['settings'] ?? []);
        $telegram = (array) ($incoming['telegram'] ?? []);
        $reminders = (array) ($incoming['reminders'] ?? []);

        $settings['telegram']['enabled'] = !empty($telegram['enabled']);
        $settings['telegram']['chat_id'] = trim((string) ($telegram['chat_id'] ?? $settings['telegram']['chat_id']));
        if (isset($telegram['bot_token']) && trim((string) $telegram['bot_token']) !== '') {
            $settings['telegram']['bot_token'] = trim((string) $telegram['bot_token']);
        }

        $days = array_values(array_unique(array_map('intval', (array) ($reminders['days'] ?? $settings['reminders']['days']))));
        sort($days);
        $settings['reminders']['days'] = $days;
        $settings['reminders']['notify_overdue'] = !empty($reminders['notify_overdue']);
        $settings['reminders']['repeat_after_days'] = max(1, (int) ($reminders['repeat_after_days'] ?? 1));

        if (!empty($incoming['new_password'])) {
            $newPassword = (string) $incoming['new_password'];
            if (mb_strlen($newPassword) < 8) {
                qlh_json_response(['ok' => false, 'message' => 'Mật khẩu mới cần tối thiểu 8 ký tự.'], 422);
            }
            $settings['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        }
        if (isset($incoming['username']) && trim((string) $incoming['username']) !== '') {
            $settings['username'] = qlh_normalize_username((string) $incoming['username']);
        }

        qlh_save_settings($settings);
        qlh_json_response(['ok' => true, 'message' => 'Đã lưu cài đặt.', 'settings' => qlh_public_settings($settings)]);
    }

    if ($action === 'test-telegram') {
        $result = qlh_send_telegram('PDL ql-hosting: gửi thử Telegram thành công lúc ' . date('d/m/Y H:i'), qlh_settings());
        qlh_json_response($result, $result['ok'] ? 200 : 422);
    }

    if ($action === 'run-reminders') {
        qlh_json_response(qlh_run_reminders(!empty($payload['dry_run'])));
    }

    qlh_json_response(['ok' => false, 'message' => 'Action không hợp lệ.'], 404);
} catch (Throwable $e) {
    qlh_json_response(['ok' => false, 'message' => $e->getMessage()], 500);
}
