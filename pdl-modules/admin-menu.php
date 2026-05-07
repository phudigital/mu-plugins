<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'PDL_ADMIN_MENU_OPTION' ) ) {
    define( 'PDL_ADMIN_MENU_OPTION', 'pdl_admin_menu_hidden' );
}

if ( ! defined( 'PDL_ADMIN_MENU_SETTINGS_OPTION' ) ) {
    define( 'PDL_ADMIN_MENU_SETTINGS_OPTION', 'pdl_admin_menu_settings' );
}

if ( ! defined( 'PDL_ADMIN_MENU_CAPTURE_PRIORITY' ) ) {
    define( 'PDL_ADMIN_MENU_CAPTURE_PRIORITY', PHP_INT_MAX - 30 );
}

if ( ! defined( 'PDL_ADMIN_MENU_SAVE_PRIORITY' ) ) {
    define( 'PDL_ADMIN_MENU_SAVE_PRIORITY', PHP_INT_MAX - 20 );
}

if ( ! defined( 'PDL_ADMIN_MENU_APPLY_PRIORITY' ) ) {
    define( 'PDL_ADMIN_MENU_APPLY_PRIORITY', PHP_INT_MAX - 10 );
}

function pdl_admin_menu_get_settings() {
    $defaults = [
        'apply_to_super_admins' => false,
    ];

    $settings = get_option( PDL_ADMIN_MENU_SETTINGS_OPTION, [] );

    if ( ! is_array( $settings ) ) {
        $settings = [];
    }

    return array_merge( $defaults, $settings );
}

function pdl_admin_menu_applies_to_super_admins() {
    $settings = pdl_admin_menu_get_settings();

    return ! empty( $settings['apply_to_super_admins'] );
}

function pdl_admin_menu_get_controller_user_id() {
    $users = get_users(
        [
            'orderby' => 'ID',
            'order'   => 'ASC',
            'number'  => 1,
        ]
    );

    if ( empty( $users ) ) {
        return 0;
    }

    return (int) $users[0]->ID;
}

function pdl_admin_menu_is_controller_user( $user_id = null ) {
    if ( null === $user_id ) {
        $user    = wp_get_current_user();
        $user_id = isset( $user->ID ) ? (int) $user->ID : 0;
    }

    return $user_id > 0 && $user_id === pdl_admin_menu_get_controller_user_id();
}

function pdl_admin_menu_is_super_admin_user( $user_id = null ) {
    if ( null === $user_id ) {
        $user    = wp_get_current_user();
        $user_id = isset( $user->ID ) ? (int) $user->ID : 0;
    }

    if ( function_exists( 'is_super_admin' ) ) {
        return is_super_admin( $user_id );
    }

    return false;
}

function pdl_admin_menu_build_entry_key( $type, $slug, $parent_slug = '' ) {
    if ( 'submenu' === $type ) {
        return 'submenu:' . $parent_slug . '|' . $slug;
    }

    return 'menu:' . $slug;
}

function pdl_admin_menu_normalize_label( $label ) {
    $label = html_entity_decode( (string) $label, ENT_QUOTES, 'UTF-8' );
    $label = preg_replace( '/\s+/', ' ', trim( wp_strip_all_tags( $label ) ) );

    return '' === $label ? 'Untitled' : $label;
}

function pdl_admin_menu_guess_icon( $slug, $type ) {
    $icons = [
        'index.php'               => 'Dashboard',
        'edit.php'                => 'Posts',
        'upload.php'              => 'Media',
        'edit.php?post_type=page' => 'Pages',
        'edit-comments.php'       => 'Comments',
        'themes.php'              => 'Appearance',
        'plugins.php'             => 'Plugins',
        'users.php'               => 'Users',
        'tools.php'               => 'Tools',
        'options-general.php'     => 'Settings',
        'woocommerce'             => 'WooCommerce',
    ];

    if ( isset( $icons[ $slug ] ) ) {
        return $icons[ $slug ];
    }

    return 'submenu' === $type ? 'Submenu' : 'Menu';
}

function pdl_admin_menu_get_entries() {
    global $menu, $submenu;

    $entries       = [];
    $parent_labels = [];

    foreach ( (array) $menu as $item ) {
        $slug = isset( $item[2] ) ? (string) $item[2] : '';

        if ( '' === $slug || 'pdl-admin-menu' === $slug || 0 === strpos( $slug, 'separator' ) ) {
            continue;
        }

        $label = pdl_admin_menu_normalize_label( $item[0] ?? $slug );
        $key   = pdl_admin_menu_build_entry_key( 'menu', $slug );

        $entries[ $key ] = [
            'key'         => $key,
            'type'        => 'menu',
            'slug'        => $slug,
            'parent_slug' => '',
            'label'       => $label,
            'icon'        => pdl_admin_menu_guess_icon( $slug, 'menu' ),
            'slug_label'  => $slug,
        ];

        $parent_labels[ $slug ] = $label;
    }

    foreach ( (array) $submenu as $parent_slug => $items ) {
        foreach ( (array) $items as $item ) {
            $slug = isset( $item[2] ) ? (string) $item[2] : '';

            if ( '' === $slug || 'pdl-admin-menu' === $slug ) {
                continue;
            }

            $child_label  = pdl_admin_menu_normalize_label( $item[0] ?? $slug );
            $parent_label = isset( $parent_labels[ $parent_slug ] ) ? $parent_labels[ $parent_slug ] : pdl_admin_menu_normalize_label( $parent_slug );
            $key          = pdl_admin_menu_build_entry_key( 'submenu', $slug, $parent_slug );

            $entries[ $key ] = [
                'key'         => $key,
                'type'        => 'submenu',
                'slug'        => $slug,
                'parent_slug' => $parent_slug,
                'label'       => $parent_label . ' / ' . $child_label,
                'icon'        => pdl_admin_menu_guess_icon( $slug, 'submenu' ),
                'slug_label'  => $parent_slug . ' -> ' . $slug,
            ];
        }
    }

    return $entries;
}

function pdl_admin_menu_capture_runtime_entries() {
    $GLOBALS['pdl_admin_menu_runtime_entries'] = pdl_admin_menu_get_entries();
}
add_action( 'admin_menu', 'pdl_admin_menu_capture_runtime_entries', PDL_ADMIN_MENU_CAPTURE_PRIORITY );

function pdl_admin_menu_get_runtime_entries() {
    if ( isset( $GLOBALS['pdl_admin_menu_runtime_entries'] ) && is_array( $GLOBALS['pdl_admin_menu_runtime_entries'] ) ) {
        return $GLOBALS['pdl_admin_menu_runtime_entries'];
    }

    return pdl_admin_menu_get_entries();
}

function pdl_admin_menu_normalize_hidden_keys( $hidden, $entries = null ) {
    if ( ! is_array( $hidden ) ) {
        return [];
    }

    if ( null === $entries ) {
        $entries = pdl_admin_menu_get_entries();
    }

    $normalized = [];

    foreach ( $hidden as $raw_key ) {
        $raw_key = (string) $raw_key;

        if ( isset( $entries[ $raw_key ] ) ) {
            $normalized[] = $raw_key;
            continue;
        }

        $legacy_top_key = pdl_admin_menu_build_entry_key( 'menu', $raw_key );
        if ( isset( $entries[ $legacy_top_key ] ) ) {
            $normalized[] = $legacy_top_key;
            continue;
        }

        $matches = [];
        foreach ( $entries as $entry_key => $entry ) {
            if ( $entry['slug'] === $raw_key ) {
                $matches[] = $entry_key;
            }
        }

        if ( 1 === count( $matches ) ) {
            $normalized[] = $matches[0];
        }
    }

    return array_values( array_unique( $normalized ) );
}

function pdl_admin_menu_get_hidden_keys( $entries = null ) {
    return pdl_admin_menu_normalize_hidden_keys( get_option( PDL_ADMIN_MENU_OPTION, [] ), $entries );
}

function pdl_admin_menu_get_tree( $entries = null ) {
    if ( null === $entries ) {
        $entries = pdl_admin_menu_get_entries();
    }

    $tree    = [];
    $orphans = [];

    foreach ( $entries as $entry_key => $entry ) {
        if ( 'menu' !== $entry['type'] ) {
            continue;
        }

        $tree[ $entry['slug'] ] = [
            'entry_key' => $entry_key,
            'entry'     => $entry,
            'children'  => [],
        ];
    }

    foreach ( $entries as $entry_key => $entry ) {
        if ( 'submenu' !== $entry['type'] ) {
            continue;
        }

        if ( isset( $tree[ $entry['parent_slug'] ] ) ) {
            $tree[ $entry['parent_slug'] ]['children'][ $entry_key ] = $entry;
            continue;
        }

        $orphans[ $entry_key ] = $entry;
    }

    return [
        'tree'    => $tree,
        'orphans' => $orphans,
    ];
}

function pdl_admin_menu_remove_entry( $entry_key, $entries = null ) {
    if ( null === $entries ) {
        $entries = pdl_admin_menu_get_entries();
    }

    if ( ! isset( $entries[ $entry_key ] ) ) {
        return;
    }

    $entry = $entries[ $entry_key ];

    if ( 'submenu' === $entry['type'] && ! empty( $entry['parent_slug'] ) ) {
        remove_submenu_page( $entry['parent_slug'], $entry['slug'] );
        return;
    }

    remove_menu_page( $entry['slug'] );
}

function pdl_admin_menu_is_controller_settings_parent_entry( $entry ) {
    return 'menu' === $entry['type'] && 'options-general.php' === $entry['slug'];
}

function pdl_admin_menu_apply_hidden() {
    $apply_to_super_admins = pdl_admin_menu_applies_to_super_admins();
    $is_controller_user    = pdl_admin_menu_is_controller_user();

    if ( $is_controller_user && ! $apply_to_super_admins ) {
        return;
    }

    if ( ! $apply_to_super_admins && pdl_admin_menu_is_super_admin_user() ) {
        return;
    }

    $entries = pdl_admin_menu_get_runtime_entries();
    $hidden  = pdl_admin_menu_get_hidden_keys( $entries );

    foreach ( $hidden as $entry_key ) {
        if ( $is_controller_user && isset( $entries[ $entry_key ] ) && pdl_admin_menu_is_controller_settings_parent_entry( $entries[ $entry_key ] ) ) {
            continue;
        }

        pdl_admin_menu_remove_entry( $entry_key, $entries );
    }
}
add_action( 'admin_menu', 'pdl_admin_menu_apply_hidden', PDL_ADMIN_MENU_APPLY_PRIORITY );

function pdl_admin_menu_register_settings_page() {
    if ( ! pdl_admin_menu_is_controller_user() ) {
        return;
    }

    add_options_page(
        'PDL Admin Menu',
        'PDL Admin Menu',
        'manage_options',
        'pdl-admin-menu',
        'pdl_admin_menu_settings_page'
    );
}
add_action( 'admin_menu', 'pdl_admin_menu_register_settings_page' );

function pdl_admin_menu_save_settings() {
    if (
        'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ||
        ! isset( $_POST['pdl_admin_menu_nonce'] ) ||
        ! wp_verify_nonce( $_POST['pdl_admin_menu_nonce'], 'pdl_admin_menu_save' ) ||
        ! pdl_admin_menu_is_controller_user()
    ) {
        return;
    }

    $entries   = pdl_admin_menu_get_runtime_entries();
    $all_keys  = array_keys( $entries );
    $submitted = isset( $_POST['pdl_admin_menu_hidden'] ) ? (array) wp_unslash( $_POST['pdl_admin_menu_hidden'] ) : [];
    $to_save   = array_values( array_intersect( $submitted, $all_keys ) );
    $settings  = [
        'apply_to_super_admins' => ! empty( $_POST['pdl_admin_menu_apply_to_super_admins'] ),
    ];

    update_option( PDL_ADMIN_MENU_OPTION, $to_save );
    update_option( PDL_ADMIN_MENU_SETTINGS_OPTION, $settings );

    add_action(
        'admin_notices',
        function() {
            echo '<div class="notice notice-success is-dismissible"><p>Đã lưu cấu hình PDL Admin Menu.</p></div>';
        }
    );
}
add_action( 'admin_menu', 'pdl_admin_menu_save_settings', PDL_ADMIN_MENU_SAVE_PRIORITY );

function pdl_admin_menu_admin_styles() {
    if ( ! isset( $_GET['page'] ) || 'pdl-admin-menu' !== $_GET['page'] ) {
        return;
    }
    ?>
    <style>
        #pdl-admin-menu-wrap{max-width:1180px;margin:24px auto 72px;color:#1d2433;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
        #pdl-admin-menu-wrap *{box-sizing:border-box}
        .pdl-am-head{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:18px;align-items:end;padding:24px;margin-bottom:14px;border:1px solid #dce2ea;border-radius:8px;background:#fff;box-shadow:0 8px 28px rgba(17,24,39,.07)}
        .pdl-am-kicker{margin:0 0 6px;color:#596579;font-size:11px;font-weight:800;letter-spacing:.06em;text-transform:uppercase}
        .pdl-am-head h1{margin:0 0 6px;color:#101828;font-size:24px;line-height:1.15}
        .pdl-am-head p{margin:0;color:#667085;font-size:13px}
        .pdl-am-badge{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border:1px solid #cfd7e3;border-radius:6px;background:#f7f9fc;color:#344054;font-size:12px;font-weight:700;white-space:nowrap}
        .pdl-am-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:12px}
        .pdl-am-stat{padding:14px 16px;border:1px solid #dfe5ee;border-radius:8px;background:#fff}
        .pdl-am-stat span{display:block;margin-bottom:5px;color:#667085;font-size:11px;font-weight:700;text-transform:uppercase}
        .pdl-am-stat strong{display:block;color:#101828;font-size:22px;line-height:1}
        .pdl-am-panel{position:sticky;top:32px;z-index:5;margin-bottom:12px;padding:12px;border:1px solid #d8e0ea;border-radius:8px;background:#f7f9fc;box-shadow:0 8px 24px rgba(17,24,39,.08)}
        .pdl-am-toolbar{display:grid;grid-template-columns:minmax(260px,1fr) auto auto;gap:10px;align-items:center}
        .pdl-am-search,.pdl-am-toolbar button,.pdl-am-actions button,.pdl-am-save{min-height:36px;border-radius:6px;font:inherit;font-size:13px}
        .pdl-am-search{width:100%;padding:8px 12px;border:1px solid #cfd7e3;background:#fff}
        .pdl-am-toolbar button,.pdl-am-actions button{display:inline-flex;align-items:center;gap:6px;padding:7px 11px;border:1px solid #cfd7e3;background:#fff;color:#344054;cursor:pointer}
        .pdl-am-toolbar button:hover,.pdl-am-actions button:hover,.pdl-am-filter.is-active{border-color:#2f6fb3;background:#eef5ff;color:#1a4fa0}
        .pdl-am-filters,.pdl-am-bulk{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
        .pdl-am-filter{font-weight:700}
        .pdl-am-option{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:14px;align-items:center;margin:12px 0 0;padding:12px 14px;border:1px solid #dfe5ee;border-radius:8px;background:#fff}
        .pdl-am-option strong{display:block;margin-bottom:3px;color:#202939;font-size:13px}
        .pdl-am-option span{display:block;color:#667085;font-size:12px;line-height:1.45}
        .pdl-am-dirty{display:none;margin-top:10px;padding:9px 12px;border-left:3px solid #b54708;border-radius:6px;background:#fff7ed;color:#9a3412;font-size:12px;font-weight:700}
        .pdl-am-dirty.is-active{display:block}
        .pdl-am-tree{display:grid;gap:9px}
        .pdl-am-group{overflow:hidden;border:1px solid #dfe5ee;border-radius:8px;background:#fff;box-shadow:0 2px 14px rgba(16,24,40,.05)}
        .pdl-am-parent{display:grid;grid-template-columns:auto minmax(0,1fr) auto auto auto;gap:10px;align-items:center;padding:12px 14px;border-bottom:1px solid #edf0f6;background:#fbfcfe}
        .pdl-am-children{display:grid}
        .pdl-am-group.is-collapsed .pdl-am-children{display:none}
        .pdl-am-collapse{width:28px;height:28px;border:1px solid #cfd7e3;border-radius:6px;background:#fff;line-height:1;cursor:pointer}
        .pdl-am-group.is-collapsed .pdl-am-collapse{transform:rotate(-90deg)}
        .pdl-am-main{display:grid;grid-template-columns:auto minmax(0,1fr);gap:10px;align-items:center;min-width:0}
        .pdl-am-child{display:grid;grid-template-columns:minmax(0,1fr) auto auto;gap:10px;align-items:center;padding:11px 14px 11px 54px;border-bottom:1px solid #f2f4f8}
        .pdl-am-child:last-child{border-bottom:0}
        .pdl-am-entry{min-width:0}
        .pdl-am-icon{display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:6px;background:#eef2f7;color:#475467;font-size:12px;font-weight:800}
        .pdl-am-label{display:block;overflow:hidden;color:#202939;font-weight:700;text-overflow:ellipsis;white-space:nowrap}
        .pdl-am-child .pdl-am-label{font-weight:500}
        .pdl-am-slug{display:block;overflow:hidden;margin-top:3px;color:#98a2b3;font-size:11px;text-overflow:ellipsis;white-space:nowrap}
        .pdl-am-status{min-width:96px;padding:5px 8px;border:1px solid #d0d5dd;border-radius:999px;background:#fff;color:#475467;font-size:11px;font-weight:800;text-align:center}
        .pdl-am-status.is-hidden{border-color:#fecaca;background:#fff1f2;color:#be123c}
        .pdl-am-status.is-partial{border-color:#fed7aa;background:#fff7ed;color:#c2410c}
        .pdl-am-status.is-visible{border-color:#bbf7d0;background:#f0fdf4;color:#15803d}
        .pdl-am-hidden .pdl-am-label{color:#98a2b3;text-decoration:line-through}
        .pdl-am-hidden-text{min-width:28px;color:#d92d20;font-size:11px;font-weight:800;text-align:right}
        .pdl-am-switch{position:relative;display:inline-block;width:44px;height:24px;flex-shrink:0}
        .pdl-am-switch input{width:0;height:0;opacity:0}
        .pdl-am-slider{position:absolute;inset:0;border-radius:24px;background:#e4e7ec;cursor:pointer;transition:.2s}
        .pdl-am-slider:before{content:"";position:absolute;left:3px;top:3px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 4px rgba(16,24,40,.2);transition:.2s}
        .pdl-am-switch input:checked + .pdl-am-slider{background:#d92d20}
        .pdl-am-switch input:checked + .pdl-am-slider:before{transform:translateX(20px)}
        .pdl-am-footer{position:sticky;bottom:0;display:flex;align-items:center;justify-content:space-between;gap:16px;margin-top:18px;padding:12px 14px;border:1px solid #d8e0ea;border-radius:8px;background:#fff;color:#667085;font-size:12px;box-shadow:0 -8px 24px rgba(17,24,39,.07)}
        .pdl-am-save{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;border:0;background:#273253;color:#fff;font-weight:800;cursor:pointer}
        .pdl-am-save:hover{background:#1f2947;color:#fff}
        .pdl-am-empty{display:none;padding:22px;border:1px dashed #cbd5e1;border-radius:8px;background:#fff;text-align:center;color:#667085}
        @media(max-width:960px){.pdl-am-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.pdl-am-toolbar{grid-template-columns:1fr}.pdl-am-panel{position:static}.pdl-am-parent{grid-template-columns:auto minmax(0,1fr) auto}.pdl-am-actions{grid-column:2/-1}.pdl-am-status{display:none}}
        @media(max-width:782px){#pdl-admin-menu-wrap{margin:16px 10px 56px}.pdl-am-head{grid-template-columns:1fr}.pdl-am-stats{grid-template-columns:1fr 1fr}.pdl-am-option{grid-template-columns:1fr}.pdl-am-child{grid-template-columns:minmax(0,1fr) auto;padding-left:42px}.pdl-am-hidden-text{display:none}.pdl-am-footer{display:grid}.pdl-am-save{justify-content:center;width:100%}}
    </style>
    <?php
}
add_action( 'admin_head', 'pdl_admin_menu_admin_styles' );

function pdl_admin_menu_render_row( $entry_key, $entry, $hidden, $class = 'pdl-am-child' ) {
    $is_hidden = in_array( $entry_key, $hidden, true );
    ?>
    <div class="<?php echo esc_attr( $class . ' pdl-am-row ' . ( $is_hidden ? 'pdl-am-hidden' : '' ) ); ?>">
        <div class="pdl-am-entry">
            <span class="pdl-am-label"><?php echo esc_html( $entry['label'] ); ?></span>
            <span class="pdl-am-slug"><?php echo esc_html( $entry['slug_label'] ); ?></span>
        </div>
        <label class="pdl-am-switch">
            <input type="checkbox" name="pdl_admin_menu_hidden[]" value="<?php echo esc_attr( $entry_key ); ?>" <?php checked( $is_hidden ); ?> onchange="pdlAdminMenuOnChange(this)" data-initial="<?php echo $is_hidden ? '1' : '0'; ?>">
            <span class="pdl-am-slider"></span>
        </label>
        <span class="pdl-am-hidden-text"><?php echo $is_hidden ? 'ẨN' : ''; ?></span>
    </div>
    <?php
}

function pdl_admin_menu_settings_page() {
    if ( ! pdl_admin_menu_is_controller_user() ) {
        wp_die( 'Bạn không có quyền truy cập trang này.' );
    }

    $entries   = pdl_admin_menu_get_runtime_entries();
    $menu_tree = pdl_admin_menu_get_tree( $entries );
    $hidden    = pdl_admin_menu_get_hidden_keys( $entries );
    $settings  = pdl_admin_menu_get_settings();
    $total_entries = count( $entries );
    $hidden_count  = count( $hidden );
    $visible_count = max( 0, $total_entries - $hidden_count );
    ?>
    <div id="pdl-admin-menu-wrap">
        <div class="pdl-am-head">
            <div>
                <p class="pdl-am-kicker">Tree Control Panel</p>
                <h1>PDL Admin Menu</h1>
                <p>Quản lý menu WordPress Admin theo cây cha-con của website hiện tại.</p>
            </div>
            <span class="pdl-am-badge"><span class="dashicons dashicons-shield-alt" aria-hidden="true"></span>Oldest user only - v<?php echo esc_html( defined( 'PDL_VERSION' ) ? PDL_VERSION : 'dev' ); ?></span>
        </div>

        <form method="post" id="pdl-am-form">
            <?php wp_nonce_field( 'pdl_admin_menu_save', 'pdl_admin_menu_nonce' ); ?>

            <div class="pdl-am-stats">
                <div class="pdl-am-stat"><span>Nhóm menu</span><strong id="pdl-am-group-count"><?php echo esc_html( count( $menu_tree['tree'] ) ); ?></strong></div>
                <div class="pdl-am-stat"><span>Tổng mục</span><strong><?php echo esc_html( $total_entries ); ?></strong></div>
                <div class="pdl-am-stat"><span>Đang hiện</span><strong id="pdl-am-visible-count"><?php echo esc_html( $visible_count ); ?></strong></div>
                <div class="pdl-am-stat"><span>Đang ẩn</span><strong id="pdl-am-hidden-count"><?php echo esc_html( $hidden_count ); ?></strong></div>
            </div>

            <div class="pdl-am-panel">
                <div class="pdl-am-toolbar">
                    <input class="pdl-am-search" id="pdl-am-search" type="search" placeholder="Tìm menu, submenu, plugin, slug..." oninput="pdlAdminMenuApplyFilters()">
                    <div class="pdl-am-filters" aria-label="Lọc trạng thái">
                        <button class="pdl-am-filter is-active" type="button" data-filter="all" onclick="pdlAdminMenuSetStatusFilter(this)">Tất cả</button>
                        <button class="pdl-am-filter" type="button" data-filter="hidden" onclick="pdlAdminMenuSetStatusFilter(this)">Đang ẩn</button>
                        <button class="pdl-am-filter" type="button" data-filter="visible" onclick="pdlAdminMenuSetStatusFilter(this)">Đang hiện</button>
                    </div>
                    <div class="pdl-am-bulk">
                        <button type="button" onclick="pdlAdminMenuCollapseAll(false)"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>Mở</button>
                        <button type="button" onclick="pdlAdminMenuCollapseAll(true)"><span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span>Gọn</button>
                        <button type="button" onclick="pdlAdminMenuToggleAll(false)"><span class="dashicons dashicons-visibility" aria-hidden="true"></span>Hiện tất cả</button>
                        <button type="button" onclick="pdlAdminMenuToggleAll(true)"><span class="dashicons dashicons-hidden" aria-hidden="true"></span>Ẩn tất cả</button>
                    </div>
                </div>

                <div class="pdl-am-option">
                    <div>
                        <strong>Áp dụng ẩn menu cho super admin</strong>
                        <span>Bật tùy chọn này khi muốn super admin cũng nhìn thấy admin menu đã được dọn gọn.</span>
                    </div>
                    <label class="pdl-am-switch">
                        <input type="checkbox" name="pdl_admin_menu_apply_to_super_admins" value="1" <?php checked( ! empty( $settings['apply_to_super_admins'] ) ); ?> data-initial="<?php echo ! empty( $settings['apply_to_super_admins'] ) ? '1' : '0'; ?>" onchange="pdlAdminMenuMarkDirty()">
                        <span class="pdl-am-slider"></span>
                    </label>
                </div>
                <div class="pdl-am-dirty" id="pdl-am-dirty">Có thay đổi chưa lưu.</div>
            </div>

            <div class="pdl-am-tree" id="pdl-am-tree">
                <?php foreach ( $menu_tree['tree'] as $group ) :
                    $parent_key  = $group['entry_key'];
                    $parent      = $group['entry'];
                    $children    = $group['children'];
                    $is_hidden   = in_array( $parent_key, $hidden, true );
                    $hidden_children = 0;
                    $search_text = strtolower( $parent['label'] . ' ' . $parent['slug_label'] );
                    foreach ( $children as $child_key => $child ) {
                        $search_text .= ' ' . strtolower( $child['label'] . ' ' . $child['slug_label'] );
                        if ( in_array( $child_key, $hidden, true ) ) {
                            $hidden_children++;
                        }
                    }
                    $group_total  = count( $children ) + 1;
                    $group_hidden = $hidden_children + ( $is_hidden ? 1 : 0 );
                    if ( 0 === $group_hidden ) {
                        $group_state = 'visible';
                        $state_label = 'Hiện';
                    } elseif ( $group_hidden >= $group_total ) {
                        $group_state = 'hidden';
                        $state_label = 'Ẩn toàn bộ';
                    } else {
                        $group_state = 'partial';
                        $state_label = 'Ẩn một phần';
                    }
                    ?>
                    <div class="pdl-am-group" data-search="<?php echo esc_attr( $search_text ); ?>" data-state="<?php echo esc_attr( $group_state ); ?>">
                        <div class="pdl-am-parent pdl-am-row <?php echo $is_hidden ? 'pdl-am-hidden' : ''; ?>">
                            <button class="pdl-am-collapse" type="button" onclick="pdlAdminMenuToggleGroup(this)" aria-label="Thu gọn/mở nhóm">⌄</button>
                            <div class="pdl-am-main">
                                <span class="pdl-am-icon" aria-hidden="true"><?php echo esc_html( substr( $parent['icon'], 0, 1 ) ); ?></span>
                                <div class="pdl-am-entry">
                                    <span class="pdl-am-label"><?php echo esc_html( $parent['label'] ); ?></span>
                                    <span class="pdl-am-slug"><?php echo esc_html( $parent['slug_label'] ); ?></span>
                                </div>
                            </div>
                            <span class="pdl-am-status is-<?php echo esc_attr( $group_state ); ?>"><?php echo esc_html( $state_label ); ?></span>
                            <div class="pdl-am-actions">
                                <button type="button" onclick="pdlAdminMenuToggleGroupChecks(this, false)"><span class="dashicons dashicons-visibility" aria-hidden="true"></span>Hiện nhóm</button>
                                <button type="button" onclick="pdlAdminMenuToggleGroupChecks(this, true)"><span class="dashicons dashicons-hidden" aria-hidden="true"></span>Ẩn nhóm</button>
                            </div>
                            <label class="pdl-am-switch">
                                <input type="checkbox" name="pdl_admin_menu_hidden[]" value="<?php echo esc_attr( $parent_key ); ?>" <?php checked( $is_hidden ); ?> onchange="pdlAdminMenuOnChange(this)" data-initial="<?php echo $is_hidden ? '1' : '0'; ?>">
                                <span class="pdl-am-slider"></span>
                            </label>
                            <span class="pdl-am-hidden-text"><?php echo $is_hidden ? 'ẨN' : ''; ?></span>
                        </div>
                        <div class="pdl-am-children">
                            <?php foreach ( $children as $entry_key => $entry ) : ?>
                                <?php pdl_admin_menu_render_row( $entry_key, $entry, $hidden ); ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if ( ! empty( $menu_tree['orphans'] ) ) : ?>
                    <div class="pdl-am-group" data-search="orphan submenus">
                        <div class="pdl-am-parent">
                            <button class="pdl-am-collapse" type="button" onclick="pdlAdminMenuToggleGroup(this)" aria-label="Thu gọn/mở nhóm">⌄</button>
                            <div class="pdl-am-main">
                                <span aria-hidden="true">Submenu</span>
                                <span class="pdl-am-label">Submenu không có menu cha trong runtime</span>
                                <span class="pdl-am-slug">orphan-submenus</span>
                            </div>
                        </div>
                        <div class="pdl-am-children">
                            <?php foreach ( $menu_tree['orphans'] as $entry_key => $entry ) : ?>
                                <?php pdl_admin_menu_render_row( $entry_key, $entry, $hidden ); ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="pdl-am-empty" id="pdl-am-empty">Không tìm thấy menu phù hợp.</div>

            <div class="pdl-am-footer">
                <span>Chỉ user tạo sớm nhất được cấu hình. Các user khác sẽ thấy menu đã được ẩn.</span>
                <button class="pdl-am-save" type="submit">Lưu cấu hình</button>
            </div>
        </form>
    </div>

    <script>
    var pdlAdminMenuStatusFilter='all';
    function pdlAdminMenuMarkDirty(){
        var dirty=false;
        document.querySelectorAll('#pdl-am-form input[data-initial]').forEach(function(input){
            dirty=dirty||((input.checked?'1':'0')!==input.dataset.initial);
        });
        var el=document.getElementById('pdl-am-dirty');if(el)el.classList.toggle('is-active',dirty);
    }
    function pdlAdminMenuOnChange(cb){
        var row=cb.closest('.pdl-am-row'),label=row?row.querySelector('.pdl-am-hidden-text'):null;
        if(cb.checked){row&&row.classList.add('pdl-am-hidden');label&&(label.textContent='ẨN');}
        else{row&&row.classList.remove('pdl-am-hidden');label&&(label.textContent='');}
        pdlAdminMenuUpdateGroupState(cb.closest('.pdl-am-group'));
        pdlAdminMenuUpdateCount();
        pdlAdminMenuApplyFilters();
        pdlAdminMenuMarkDirty();
    }
    function pdlAdminMenuToggleAll(state){
        document.querySelectorAll('#pdl-am-tree input[type=checkbox]').forEach(function(cb){cb.checked=state;pdlAdminMenuOnChange(cb);});
    }
    function pdlAdminMenuToggleGroup(button){
        var group=button.closest('.pdl-am-group');group&&group.classList.toggle('is-collapsed');
    }
    function pdlAdminMenuCollapseAll(state){
        document.querySelectorAll('.pdl-am-group').forEach(function(group){group.classList.toggle('is-collapsed',state);});
    }
    function pdlAdminMenuToggleGroupChecks(button,state){
        var group=button.closest('.pdl-am-group');if(!group)return;
        group.querySelectorAll('input[type=checkbox]').forEach(function(cb){cb.checked=state;pdlAdminMenuOnChange(cb);});
    }
    function pdlAdminMenuSetStatusFilter(button){
        pdlAdminMenuStatusFilter=button.dataset.filter||'all';
        document.querySelectorAll('.pdl-am-filter').forEach(function(btn){btn.classList.toggle('is-active',btn===button);});
        pdlAdminMenuApplyFilters();
    }
    function pdlAdminMenuUpdateGroupState(group){
        if(!group)return;
        var boxes=group.querySelectorAll('input[name="pdl_admin_menu_hidden[]"]'),checked=0;
        boxes.forEach(function(cb){if(cb.checked)checked++;});
        var state=checked===0?'visible':(checked===boxes.length?'hidden':'partial');
        var label=state==='visible'?'Hiện':(state==='hidden'?'Ẩn toàn bộ':'Ẩn một phần');
        group.dataset.state=state;
        var status=group.querySelector('.pdl-am-status');
        if(status){status.className='pdl-am-status is-'+state;status.textContent=label;}
    }
    function pdlAdminMenuApplyFilters(){
        var input=document.getElementById('pdl-am-search');
        var query=(input?input.value:'').trim().toLowerCase(),visible=0;
        document.querySelectorAll('.pdl-am-group').forEach(function(group){
            var state=group.dataset.state||'visible';
            var statusMatch=pdlAdminMenuStatusFilter==='all'||(pdlAdminMenuStatusFilter==='hidden'&&state!=='visible')||(pdlAdminMenuStatusFilter==='visible'&&state==='visible');
            var match=statusMatch&&(!query||(group.dataset.search||'').indexOf(query)!==-1);
            group.style.display=match?'':'none';if(match)visible++;
        });
        var empty=document.getElementById('pdl-am-empty');if(empty)empty.style.display=visible?'none':'block';
    }
    function pdlAdminMenuUpdateCount(){
        var hidden=document.querySelectorAll('#pdl-am-tree input:checked').length,total=document.querySelectorAll('#pdl-am-tree input[type=checkbox]').length;
        var hiddenEl=document.getElementById('pdl-am-hidden-count'),visibleEl=document.getElementById('pdl-am-visible-count');
        if(hiddenEl)hiddenEl.textContent=hidden;
        if(visibleEl)visibleEl.textContent=Math.max(0,total-hidden);
    }
    document.addEventListener('DOMContentLoaded',function(){
        document.querySelectorAll('.pdl-am-group').forEach(pdlAdminMenuUpdateGroupState);
        pdlAdminMenuUpdateCount();
        pdlAdminMenuApplyFilters();
    });
    </script>
    <?php
}
