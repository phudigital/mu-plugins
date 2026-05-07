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
        #pdl-admin-menu-wrap{max-width:1040px;margin:28px auto 56px;color:#172033;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
        #pdl-admin-menu-wrap *{box-sizing:border-box}
        .pdl-am-head{display:flex;gap:16px;align-items:center;padding:24px 28px;margin-bottom:16px;border-radius:12px;background:#273253;color:#fff;box-shadow:0 8px 28px rgba(39,50,83,.18)}
        .pdl-am-head h1{margin:0 0 4px;color:#fff;font-size:22px;line-height:1.2}
        .pdl-am-head p{margin:0;color:rgba(255,255,255,.68);font-size:13px}
        .pdl-am-badge{margin-left:auto;padding:5px 12px;border:1px solid rgba(255,255,255,.2);border-radius:999px;background:rgba(255,255,255,.1);color:rgba(255,255,255,.82);font-size:11px;white-space:nowrap}
        .pdl-am-toolbar{display:grid;grid-template-columns:1fr auto auto auto;gap:10px;margin-bottom:14px}
        .pdl-am-search,.pdl-am-toolbar button,.pdl-am-actions button,.pdl-am-save{min-height:38px;border-radius:8px;font:inherit;font-size:13px}
        .pdl-am-search{width:100%;padding:8px 12px;border:1px solid #d9dee8;background:#fff}
        .pdl-am-toolbar button,.pdl-am-actions button{padding:7px 14px;border:1px solid #d9dee8;background:#fff;color:#344054;cursor:pointer}
        .pdl-am-toolbar button:hover,.pdl-am-actions button:hover{border-color:#2f6fb3;background:#eef5ff;color:#1a4fa0}
        .pdl-am-option{display:flex;align-items:center;justify-content:space-between;gap:16px;margin:0 0 14px;padding:14px 16px;border:1px solid #e8e8f0;border-radius:10px;background:#fff;box-shadow:0 2px 16px rgba(16,24,40,.04)}
        .pdl-am-option strong{display:block;margin-bottom:3px;color:#202939;font-size:13px}
        .pdl-am-option span{display:block;color:#667085;font-size:12px;line-height:1.45}
        .pdl-am-summary{display:flex;justify-content:space-between;gap:12px;margin:0 0 16px;color:#667085;font-size:12px}
        .pdl-am-tree{display:grid;gap:10px}
        .pdl-am-group{overflow:hidden;border:1px solid #e8e8f0;border-radius:10px;background:#fff;box-shadow:0 2px 16px rgba(16,24,40,.06)}
        .pdl-am-parent{display:grid;grid-template-columns:auto minmax(0,1fr) auto auto;gap:12px;align-items:center;padding:13px 16px;border-bottom:1px solid #edf0f6;background:#f8fafc}
        .pdl-am-children{display:grid}
        .pdl-am-group.is-collapsed .pdl-am-children{display:none}
        .pdl-am-collapse{width:28px;height:28px;border:1px solid #d9dee8;border-radius:7px;background:#fff;line-height:1;cursor:pointer}
        .pdl-am-group.is-collapsed .pdl-am-collapse{transform:rotate(-90deg)}
        .pdl-am-main,.pdl-am-child{display:flex;min-width:0;align-items:center;gap:10px}
        .pdl-am-child{padding:12px 16px 12px 56px;border-bottom:1px solid #f2f2f8}
        .pdl-am-child:last-child{border-bottom:0}
        .pdl-am-label{font-weight:650;color:#202939}
        .pdl-am-child .pdl-am-label{font-weight:500}
        .pdl-am-slug{overflow:hidden;max-width:260px;padding:2px 8px;border-radius:5px;background:#f2f4f7;color:#98a2b3;font-size:11px;text-overflow:ellipsis;white-space:nowrap}
        .pdl-am-hidden .pdl-am-label{color:#98a2b3;text-decoration:line-through}
        .pdl-am-hidden-text{min-width:28px;color:#d92d20;font-size:11px;font-weight:700;text-align:right}
        .pdl-am-switch{position:relative;display:inline-block;width:44px;height:24px;flex-shrink:0}
        .pdl-am-switch input{width:0;height:0;opacity:0}
        .pdl-am-slider{position:absolute;inset:0;border-radius:24px;background:#e4e7ec;cursor:pointer;transition:.2s}
        .pdl-am-slider:before{content:"";position:absolute;left:3px;top:3px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 4px rgba(16,24,40,.2);transition:.2s}
        .pdl-am-switch input:checked + .pdl-am-slider{background:#d92d20}
        .pdl-am-switch input:checked + .pdl-am-slider:before{transform:translateX(20px)}
        .pdl-am-footer{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-top:22px;color:#667085;font-size:12px}
        .pdl-am-save{padding:10px 26px;border:0;background:#273253;color:#fff;font-weight:700;cursor:pointer}
        .pdl-am-empty{display:none;padding:22px;border:1px dashed #cbd5e1;border-radius:10px;background:#fff;text-align:center;color:#667085}
        @media(max-width:782px){#pdl-admin-menu-wrap{margin:18px 12px 48px}.pdl-am-head,.pdl-am-footer,.pdl-am-summary,.pdl-am-option{display:block}.pdl-am-badge,.pdl-am-save,.pdl-am-option .pdl-am-switch{margin-top:14px}.pdl-am-toolbar{grid-template-columns:1fr 1fr}.pdl-am-search{grid-column:1/-1}.pdl-am-parent{grid-template-columns:auto minmax(0,1fr) auto}.pdl-am-actions{display:none}.pdl-am-child{padding-left:42px;flex-wrap:wrap}.pdl-am-slug{max-width:100%}}
    </style>
    <?php
}
add_action( 'admin_head', 'pdl_admin_menu_admin_styles' );

function pdl_admin_menu_render_row( $entry_key, $entry, $hidden, $class = 'pdl-am-child' ) {
    $is_hidden = in_array( $entry_key, $hidden, true );
    ?>
    <div class="<?php echo esc_attr( $class . ' pdl-am-row ' . ( $is_hidden ? 'pdl-am-hidden' : '' ) ); ?>">
        <span aria-hidden="true"><?php echo esc_html( $entry['icon'] ); ?></span>
        <span class="pdl-am-label"><?php echo esc_html( $entry['label'] ); ?></span>
        <span class="pdl-am-slug"><?php echo esc_html( $entry['slug_label'] ); ?></span>
        <label class="pdl-am-switch">
            <input type="checkbox" name="pdl_admin_menu_hidden[]" value="<?php echo esc_attr( $entry_key ); ?>" <?php checked( $is_hidden ); ?> onchange="pdlAdminMenuOnChange(this)">
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
    ?>
    <div id="pdl-admin-menu-wrap">
        <div class="pdl-am-head">
            <div aria-hidden="true">Menu</div>
            <div>
                <h1>PDL Admin Menu</h1>
                <p>Ẩn menu WordPress Admin theo menu thật của website hiện tại.</p>
            </div>
            <span class="pdl-am-badge">Oldest user only - v<?php echo esc_html( defined( 'PDL_VERSION' ) ? PDL_VERSION : 'dev' ); ?></span>
        </div>

        <form method="post">
            <?php wp_nonce_field( 'pdl_admin_menu_save', 'pdl_admin_menu_nonce' ); ?>

            <div class="pdl-am-toolbar">
                <input class="pdl-am-search" id="pdl-am-search" type="search" placeholder="Tìm menu, submenu, plugin, slug..." oninput="pdlAdminMenuFilter(this.value)">
                <button type="button" onclick="pdlAdminMenuCollapseAll(false)">Mở tất cả</button>
                <button type="button" onclick="pdlAdminMenuCollapseAll(true)">Thu gọn</button>
                <button type="button" onclick="pdlAdminMenuToggleAll(false)">Hiện tất cả</button>
                <button type="button" onclick="pdlAdminMenuToggleAll(true)">Ẩn tất cả</button>
            </div>

            <div class="pdl-am-option">
                <div>
                    <strong>Áp dụng ẩn menu cho super admin</strong>
                    <span>Bật: super admin cũng bị áp rule ẩn menu. User gốc vẫn giữ PDL Admin Menu để cấu hình.</span>
                </div>
                <label class="pdl-am-switch">
                    <input type="checkbox" name="pdl_admin_menu_apply_to_super_admins" value="1" <?php checked( ! empty( $settings['apply_to_super_admins'] ) ); ?>>
                    <span class="pdl-am-slider"></span>
                </label>
            </div>

            <div class="pdl-am-summary">
                <span><?php echo esc_html( count( $menu_tree['tree'] ) ); ?> nhóm menu, <?php echo esc_html( count( $entries ) ); ?> mục theo admin hiện tại.</span>
                <span>Đang ẩn: <strong id="pdl-am-hidden-count"><?php echo esc_html( count( $hidden ) ); ?></strong> / <?php echo esc_html( count( $entries ) ); ?> mục</span>
            </div>

            <div class="pdl-am-tree" id="pdl-am-tree">
                <?php foreach ( $menu_tree['tree'] as $group ) :
                    $parent_key  = $group['entry_key'];
                    $parent      = $group['entry'];
                    $children    = $group['children'];
                    $is_hidden   = in_array( $parent_key, $hidden, true );
                    $search_text = strtolower( $parent['label'] . ' ' . $parent['slug_label'] );
                    foreach ( $children as $child ) {
                        $search_text .= ' ' . strtolower( $child['label'] . ' ' . $child['slug_label'] );
                    }
                    ?>
                    <div class="pdl-am-group" data-search="<?php echo esc_attr( $search_text ); ?>">
                        <div class="pdl-am-parent pdl-am-row <?php echo $is_hidden ? 'pdl-am-hidden' : ''; ?>">
                            <button class="pdl-am-collapse" type="button" onclick="pdlAdminMenuToggleGroup(this)" aria-label="Thu gọn/mở nhóm">⌄</button>
                            <div class="pdl-am-main">
                                <span aria-hidden="true"><?php echo esc_html( $parent['icon'] ); ?></span>
                                <span class="pdl-am-label"><?php echo esc_html( $parent['label'] ); ?></span>
                                <span class="pdl-am-slug"><?php echo esc_html( $parent['slug_label'] ); ?></span>
                            </div>
                            <div class="pdl-am-actions">
                                <button type="button" onclick="pdlAdminMenuToggleGroupChecks(this, false)">Hiện nhóm</button>
                                <button type="button" onclick="pdlAdminMenuToggleGroupChecks(this, true)">Ẩn nhóm</button>
                            </div>
                            <label class="pdl-am-switch">
                                <input type="checkbox" name="pdl_admin_menu_hidden[]" value="<?php echo esc_attr( $parent_key ); ?>" <?php checked( $is_hidden ); ?> onchange="pdlAdminMenuOnChange(this)">
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
    function pdlAdminMenuOnChange(cb){
        var row=cb.closest('.pdl-am-row'),label=row?row.querySelector('.pdl-am-hidden-text'):null;
        if(cb.checked){row&&row.classList.add('pdl-am-hidden');label&&(label.textContent='ẨN');}
        else{row&&row.classList.remove('pdl-am-hidden');label&&(label.textContent='');}
        pdlAdminMenuUpdateCount();
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
    function pdlAdminMenuFilter(term){
        var query=(term||'').trim().toLowerCase(),visible=0;
        document.querySelectorAll('.pdl-am-group').forEach(function(group){
            var match=!query||(group.dataset.search||'').indexOf(query)!==-1;
            group.style.display=match?'':'none';if(match)visible++;
        });
        var empty=document.getElementById('pdl-am-empty');if(empty)empty.style.display=visible?'none':'block';
    }
    function pdlAdminMenuUpdateCount(){
        var el=document.getElementById('pdl-am-hidden-count');if(el)el.textContent=document.querySelectorAll('#pdl-am-tree input:checked').length;
    }
    </script>
    <?php
}
