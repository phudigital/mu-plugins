<?php
declare(strict_types=1);

require __DIR__ . '/lib.php';
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$cssVersion = (string) @filemtime(__DIR__ . '/assets/app.css');
$jsVersion = (string) @filemtime(__DIR__ . '/assets/app.js');
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#f6f7f9">
    <title>QL Hosting PDL</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/app.css?v=<?= htmlspecialchars($cssVersion) ?>">
</head>
<body>
    <div id="auth" class="auth-shell">
        <section class="auth-panel">
            <div>
                <p class="eyebrow">PDL</p>
                <h1>QL Hosting</h1>
                <p class="muted" id="authCopy">Đăng nhập để cập nhật brand.json và lịch nhắc Telegram.</p>
            </div>
            <form id="authForm" class="stack">
                <label>
                    <span>Tài khoản</span>
                    <input id="authUsername" type="text" autocomplete="username" value="phudigital" autocapitalize="none" spellcheck="false" required>
                </label>
                <label>
                    <span>Mật khẩu</span>
                    <input id="authPassword" type="password" minlength="8" autocomplete="current-password" required>
                </label>
                <button class="primary" type="submit">Tiếp tục</button>
            </form>
        </section>
    </div>

    <main id="app" class="app-shell" hidden>
        <header class="topbar">
            <div>
                <p class="eyebrow">PDL</p>
                <h1>QL Hosting</h1>
                <p id="sessionLabel" class="muted">Quản trị brand.json</p>
            </div>
            <div class="top-actions">
                <button id="saveBtn" class="primary icon-text" type="button" title="Lưu">
                    <span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 4h11l3 3v13H5z"/><path d="M8 4v6h8V4"/><path d="M9 20v-6h6v6"/></svg></span>
                    <span>Lưu</span>
                </button>
                <button id="logoutBtn" class="ghost icon-text" type="button" aria-label="Đăng xuất" title="Đăng xuất">
                    <span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/></svg></span>
                    <span>Thoát</span>
                </button>
            </div>
        </header>

        <nav class="tabs" aria-label="Khu vực quản lý">
            <button class="tab active" data-tab="overview" type="button" title="Tổng quan">
                <span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 13h8V3H3z"/><path d="M13 21h8v-6h-8z"/><path d="M13 3h8v8h-8z"/><path d="M3 21h8v-6H3z"/></svg></span>
                <span>Tổng</span>
            </button>
            <button class="tab" data-tab="brand" type="button" title="Brand">
                <span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 20V4h12l4 4v12z"/><path d="M8 4v6h8V4"/><path d="M8 16h8"/></svg></span>
                <span>Brand</span>
            </button>
            <button class="tab" data-tab="domains" type="button" title="Domain">
                <span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a15 15 0 0 1 0 18"/><path d="M12 3a15 15 0 0 0 0 18"/></svg></span>
                <span>Site</span>
            </button>
            <button class="tab" data-tab="contacts" type="button" title="Liên hệ">
                <span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 4h12a2 2 0 0 1 2 2v12l-4-3H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/></svg></span>
                <span>CSKH</span>
            </button>
            <button class="tab" data-tab="telegram" type="button" title="Telegram">
                <span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M21 4 3 11l7 2 2 7 9-16z"/><path d="M10 13 21 4"/></svg></span>
                <span>Bot</span>
            </button>
            <button class="tab" data-tab="json" type="button" title="JSON">
                <span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M8 4C6 4 5 5 5 7v3c0 1-1 2-2 2 1 0 2 1 2 2v3c0 2 1 3 3 3"/><path d="M16 4c2 0 3 1 3 3v3c0 1 1 2 2 2-1 0-2 1-2 2v3c0 2-1 3-3 3"/></svg></span>
                <span>JSON</span>
            </button>
        </nav>

        <section id="notice" class="notice" hidden></section>

        <section class="panel active" data-panel="overview">
            <section class="overview-hero">
                <div class="hero-main widget-preview-shell">
                    <div class="preview-toolbar">
                        <div>
                            <small>Preview WordPress widget</small>
                            <strong>Thông tin &amp; Hỗ trợ - PDL</strong>
                        </div>
                        <div class="preview-actions">
                            <span id="previewDomain" class="preview-domain">pdl.vn</span>
                            <button id="jumpDomainsBtn" class="text-btn icon-text" type="button" title="Sửa domain">
                                <span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a15 15 0 0 1 0 18"/><path d="M12 3a15 15 0 0 0 0 18"/></svg></span>
                            </button>
                            <button id="jumpTelegramBtn" class="text-btn icon-text" type="button" title="Telegram">
                                <span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M21 4 3 11l7 2 2 7 9-16z"/><path d="M10 13 21 4"/></svg></span>
                            </button>
                        </div>
                    </div>

                    <div class="wp-widget-frame">
                        <div class="wp-widget-title">
                            <span>Thông tin &amp; Hỗ trợ - PDL</span>
                            <span class="wp-widget-controls" aria-hidden="true">•••</span>
                        </div>
                        <div class="wp-widget-body">
                            <div id="pdlWidgetPreview" class="pdl-widget-preview">
                                <div class="pw">
                                    <div id="previewHead" class="pw-head">
                                        <div class="pw-logo"><span>PDL</span></div>
                                        <div><p class="pw-htitle">Đang tải...</p><p class="pw-hsub">pdl.vn</p></div>
                                    </div>
                                    <div class="pw-bars">
                                        <div id="previewNotify" class="pw-bar"></div>
                                        <div id="previewSite" class="pw-bar"></div>
                                        <div id="previewExpire" class="pw-bar"></div>
                                    </div>
                                    <div class="pw-inner">
                                        <div id="previewGrid" class="pw-grid">
                                            <p class="pw-loading">Đang kết nối...</p>
                                        </div>
                                        <div class="pw-foot">
                                            <span id="previewCopy" class="pw-copy">&copy; 2026 Công Ty TNHH Giải Pháp PDL</span>
                                            <a id="previewLink" class="pw-link" href="https://pdl.vn" target="_blank" rel="noopener noreferrer">pdl.vn &rarr;</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="signal-strip">
                    <div class="signal-card">
                        <small>Tổng domain</small>
                        <strong id="totalDomains">0</strong>
                    </div>
                    <div class="signal-card">
                        <small>Sắp hết hạn</small>
                        <strong id="nearExpiry">0</strong>
                    </div>
                    <div class="signal-card">
                        <small>Quá hạn</small>
                        <strong id="expiredDomains">0</strong>
                    </div>
                    <div class="signal-card">
                        <small>File dữ liệu</small>
                        <strong id="heroFile">brand.json</strong>
                    </div>
                    <div class="signal-card">
                        <small>Thông báo chung</small>
                        <strong id="heroNotify">Đang đọc cấu hình</strong>
                    </div>
                    <div class="signal-card">
                        <small>Tháng cao điểm</small>
                        <strong id="heroPeakMonth">--/----</strong>
                    </div>
                </div>
            </section>
            <div class="section-head">
                <h2>Cần chú ý</h2>
                <button id="refreshBtn" class="text-btn icon-text" type="button" title="Tải lại">
                    <span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg></span>
                </button>
            </div>
            <div id="attentionList" class="attention-list"></div>
            <button id="scheduleToggle" class="schedule-toggle" type="button" aria-expanded="false" aria-controls="schedulePanel">
                <span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M7 2v4"/><path d="M17 2v4"/><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16"/></svg></span>
                <span class="schedule-toggle-text">
                    <strong>Gia hạn 12 tháng</strong>
                    <small>Xổ ra khi cần xem</small>
                </span>
                <span class="icon chevron" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg></span>
            </button>
            <div id="monthPreview" class="month-strip"></div>
            <section id="schedulePanel" class="schedule-panel" hidden>
                <div class="section-head compact-head">
                    <h2>Lịch gia hạn</h2>
                    <p class="muted">12 tháng tới</p>
                </div>
                <div id="monthSchedule" class="month-schedule"></div>
            </section>
        </section>

        <section class="panel" data-panel="brand">
            <div class="form-grid">
                <label><span>Công ty</span><input data-brand="company" type="text"></label>
                <label><span>Website</span><input data-brand="website" type="url"></label>
                <label><span>Địa chỉ</span><input data-brand="address" type="text"></label>
                <label><span>Logo URL</span><input data-brand="logo" type="url"></label>
                <label><span>Ngày cập nhật</span><input data-brand="updated_at" data-date-input="updated_at" type="text" inputmode="numeric" placeholder="dd/mm/yyyy"></label>
            </div>
            <div class="notify-editor" data-notify="global"></div>
        </section>

        <section class="panel" data-panel="domains">
            <div class="section-head sticky-search">
                <label class="search"><span>Tìm domain</span><input id="domainSearch" type="search" placeholder="pdl.vn"></label>
                <button id="addDomainBtn" class="secondary icon-text" type="button" title="Thêm domain">
                    <span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 5v14"/><path d="M5 12h14"/></svg></span>
                </button>
            </div>
            <div id="domainList" class="domain-list"></div>
        </section>

        <section class="panel" data-panel="contacts">
            <div class="section-head">
                <h2>Danh bạ hiển thị</h2>
                <button id="addContactBtn" class="secondary icon-text" type="button" title="Thêm liên hệ">
                    <span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 5v14"/><path d="M5 12h14"/></svg></span>
                </button>
            </div>
            <div id="contactList" class="repeat-list"></div>
        </section>

        <section class="panel" data-panel="telegram">
            <div class="form-grid">
                <label class="checkline"><input id="tgEnabled" type="checkbox"><span>Bật thông báo Telegram</span></label>
                <label><span>Bot token</span><input id="tgToken" type="password" placeholder="Để trống nếu không đổi"></label>
                <label><span>Chat ID</span><input id="tgChatId" type="text"></label>
                <label><span>Nhắc trước các ngày</span><input id="reminderDays" type="text" placeholder="30,14,7,3,1,0"></label>
                <label><span>Lặp lại sau số ngày</span><input id="repeatAfter" type="number" min="1" value="1"></label>
                <label class="checkline"><input id="notifyOverdue" type="checkbox"><span>Nhắc tiếp khi đã quá hạn</span></label>
                <label><span>Tài khoản quản trị</span><input id="adminUsername" type="text" autocomplete="username" autocapitalize="none" spellcheck="false"></label>
                <label><span>Đổi mật khẩu quản trị</span><input id="newPassword" type="password" minlength="8" placeholder="Để trống nếu không đổi"></label>
            </div>
            <div class="button-row">
                <button id="testTelegramBtn" class="secondary icon-text" type="button">
                    <span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M21 4 3 11l7 2 2 7 9-16z"/><path d="M10 13 21 4"/></svg></span>
                    <span>Gửi thử</span>
                </button>
                <button id="dryRunBtn" class="ghost icon-text" type="button">
                    <span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 8v5l3 2"/><circle cx="12" cy="12" r="9"/></svg></span>
                    <span>Kiểm tra</span>
                </button>
            </div>
            <p id="cronHint" class="muted"></p>
        </section>

        <section class="panel" data-panel="json">
            <div class="section-head">
                <h2>Chỉnh JSON trực tiếp</h2>
                <button id="formatJsonBtn" class="secondary icon-text" type="button">
                    <span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M8 4C6 4 5 5 5 7v3c0 1-1 2-2 2 1 0 2 1 2 2v3c0 2 1 3 3 3"/><path d="M16 4c2 0 3 1 3 3v3c0 1 1 2 2 2-1 0-2 1-2 2v3c0 2-1 3-3 3"/></svg></span>
                    <span>Format</span>
                </button>
            </div>
            <textarea id="jsonEditor" spellcheck="false"></textarea>
        </section>
    </main>

    <script>window.QLH_BASE = <?= json_encode($basePath, JSON_UNESCAPED_SLASHES) ?>;</script>
    <script src="<?= htmlspecialchars($basePath) ?>/assets/app.js?v=<?= htmlspecialchars($jsVersion) ?>" defer></script>
</body>
</html>
