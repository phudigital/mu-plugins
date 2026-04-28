<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'PDL_HIDDEN_PLUGINS_OPTION' ) ) {
    define( 'PDL_HIDDEN_PLUGINS_OPTION', 'pdl_hidden_plugins' );
}

function pdl_hidden_plugins_default_list() {
    return [
        'contact-form-7/wp-contact-form-7.php',
        'generateblocks/plugin.php',
        'generateblocks-pro/plugin.php',
        'gp-premium/plugin.php',
        'imsanity/imsanity.php',
        'gp-premium/gp-premium.php',
        'pdl-project-shortcodes/pdl-project-repeater-shortcodes.php',
        'leads/leads.php',
        'admin-site-enhancements/admin-site-enhancements.php',
        'secure-custom-fields/secure-custom-fields.php',
        'tinymce-advanced/tinymce-advanced.php',
    ];
}

function pdl_hidden_plugins_get_controller_user_id() {
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

function pdl_hidden_plugins_is_single_site_root_user( $user_id = null ) {
    if ( null === $user_id ) {
        $user    = wp_get_current_user();
        $user_id = isset( $user->ID ) ? (int) $user->ID : 0;
    }

    $root_user_ids = apply_filters( 'pdl_hidden_plugins_root_user_ids', [ pdl_hidden_plugins_get_controller_user_id() ] );
    $root_user_ids = array_map( 'intval', (array) $root_user_ids );

    if ( $user_id > 0 && in_array( $user_id, $root_user_ids, true ) ) {
        return true;
    }

    $user = wp_get_current_user();
    $email = strtolower( (string) ( $user->user_email ?? '' ) );
    $root_emails = array_map( 'strtolower', (array) apply_filters( 'pdl_hidden_plugins_root_emails', [] ) );

    return '' !== $email && in_array( $email, $root_emails, true );
}

function pdl_hidden_plugins_is_super_admin() {
    if ( ! is_user_logged_in() ) {
        return false;
    }

    if ( is_multisite() ) {
        return is_super_admin();
    }

    return pdl_hidden_plugins_is_single_site_root_user();
}

function pdl_hidden_plugins_normalize_plugin_files( $plugin_files ) {
    $normalized = [];

    foreach ( (array) $plugin_files as $plugin_file ) {
        $plugin_file = sanitize_text_field( wp_unslash( (string) $plugin_file ) );
        $plugin_file = trim( str_replace( '\\', '/', $plugin_file ), '/' );

        if ( '' === $plugin_file || false === strpos( $plugin_file, '.php' ) ) {
            continue;
        }

        $normalized[] = $plugin_file;
    }

    return array_values( array_unique( $normalized ) );
}

function pdl_hidden_plugins_list() {
    $saved = get_option( PDL_HIDDEN_PLUGINS_OPTION, null );

    if ( is_array( $saved ) ) {
        return pdl_hidden_plugins_normalize_plugin_files( $saved );
    }

    return pdl_hidden_plugins_normalize_plugin_files(
        apply_filters( 'pdl_hidden_plugins_default_list', pdl_hidden_plugins_default_list() )
    );
}

function pdl_hidden_plugins_filter_all_plugins( $plugins ) {
    if ( pdl_hidden_plugins_is_super_admin() ) {
        return $plugins;
    }

    foreach ( pdl_hidden_plugins_list() as $plugin_file ) {
        if ( isset( $plugins[ $plugin_file ] ) ) {
            unset( $plugins[ $plugin_file ] );
        }
    }

    return $plugins;
}
add_filter( 'all_plugins', 'pdl_hidden_plugins_filter_all_plugins', 999 );

function pdl_hidden_plugins_get_installed_plugins() {
    if ( ! function_exists( 'get_plugins' ) ) {
        $plugin_file = ABSPATH . 'wp-admin/includes/plugin.php';
        if ( file_exists( $plugin_file ) ) {
            require_once $plugin_file;
        }
    }

    if ( ! function_exists( 'get_plugins' ) ) {
        return [];
    }

    return get_plugins();
}

function pdl_hidden_plugins_register_settings_page() {
    if ( ! pdl_hidden_plugins_is_super_admin() ) {
        return;
    }

    add_options_page(
        'PDL Hidden Plugins',
        'PDL Hidden Plugins',
        'manage_options',
        'pdl-hidden-plugins',
        'pdl_hidden_plugins_settings_page'
    );
}
add_action( 'admin_menu', 'pdl_hidden_plugins_register_settings_page' );

function pdl_hidden_plugins_save_settings() {
    if (
        'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ||
        ! isset( $_POST['pdl_hidden_plugins_nonce'] ) ||
        ! wp_verify_nonce( $_POST['pdl_hidden_plugins_nonce'], 'pdl_hidden_plugins_save' ) ||
        ! pdl_hidden_plugins_is_super_admin()
    ) {
        return;
    }

    $installed = pdl_hidden_plugins_get_installed_plugins();
    $allowed   = array_keys( $installed );
    $submitted = isset( $_POST['pdl_hidden_plugins'] ) ? (array) wp_unslash( $_POST['pdl_hidden_plugins'] ) : [];
    $hidden    = array_values( array_intersect( pdl_hidden_plugins_normalize_plugin_files( $submitted ), $allowed ) );

    update_option( PDL_HIDDEN_PLUGINS_OPTION, $hidden );

    add_action(
        'admin_notices',
        function() {
            echo '<div class="notice notice-success is-dismissible"><p>Đã lưu cấu hình ẩn plugin.</p></div>';
        }
    );
}
add_action( 'admin_init', 'pdl_hidden_plugins_save_settings' );

function pdl_hidden_plugins_admin_styles() {
    if ( ! isset( $_GET['page'] ) || 'pdl-hidden-plugins' !== $_GET['page'] ) {
        return;
    }
    ?>
    <style>
        #pdl-hidden-plugins-wrap{max-width:1040px;margin:28px auto 56px;color:#172033;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
        #pdl-hidden-plugins-wrap *{box-sizing:border-box}
        .pdl-hp-head{display:flex;gap:16px;align-items:center;padding:24px 28px;margin-bottom:16px;border-radius:12px;background:#273253;color:#fff;box-shadow:0 8px 28px rgba(39,50,83,.18)}
        .pdl-hp-head h1{margin:0 0 4px;color:#fff;font-size:22px;line-height:1.2}
        .pdl-hp-head p{margin:0;color:rgba(255,255,255,.68);font-size:13px}
        .pdl-hp-badge{margin-left:auto;padding:5px 12px;border:1px solid rgba(255,255,255,.2);border-radius:999px;background:rgba(255,255,255,.1);color:rgba(255,255,255,.82);font-size:11px;white-space:nowrap}
        .pdl-hp-toolbar{display:grid;grid-template-columns:1fr auto auto;gap:10px;margin-bottom:14px}
        .pdl-hp-search,.pdl-hp-toolbar button,.pdl-hp-save{min-height:38px;border-radius:8px;font:inherit;font-size:13px}
        .pdl-hp-search{width:100%;padding:8px 12px;border:1px solid #d9dee8;background:#fff}
        .pdl-hp-toolbar button{padding:7px 14px;border:1px solid #d9dee8;background:#fff;color:#344054;cursor:pointer}
        .pdl-hp-toolbar button:hover{border-color:#2f6fb3;background:#eef5ff;color:#1a4fa0}
        .pdl-hp-summary{display:flex;justify-content:space-between;gap:12px;margin:0 0 16px;color:#667085;font-size:12px}
        .pdl-hp-list{display:grid;gap:8px}
        .pdl-hp-row{display:grid;grid-template-columns:minmax(0,1fr) auto auto;gap:12px;align-items:center;padding:13px 16px;border:1px solid #e8e8f0;border-radius:10px;background:#fff;box-shadow:0 2px 16px rgba(16,24,40,.05)}
        .pdl-hp-name{margin:0 0 4px;color:#202939;font-weight:650}
        .pdl-hp-file{overflow:hidden;margin:0;color:#98a2b3;font-size:11px;text-overflow:ellipsis;white-space:nowrap}
        .pdl-hp-hidden .pdl-hp-name{color:#98a2b3;text-decoration:line-through}
        .pdl-hp-hidden-text{min-width:28px;color:#d92d20;font-size:11px;font-weight:700;text-align:right}
        .pdl-hp-switch{position:relative;display:inline-block;width:44px;height:24px}
        .pdl-hp-switch input{width:0;height:0;opacity:0}
        .pdl-hp-slider{position:absolute;inset:0;border-radius:24px;background:#e4e7ec;cursor:pointer;transition:.2s}
        .pdl-hp-slider:before{content:"";position:absolute;left:3px;top:3px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 4px rgba(16,24,40,.2);transition:.2s}
        .pdl-hp-switch input:checked + .pdl-hp-slider{background:#d92d20}
        .pdl-hp-switch input:checked + .pdl-hp-slider:before{transform:translateX(20px)}
        .pdl-hp-footer{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-top:22px;color:#667085;font-size:12px}
        .pdl-hp-save{padding:10px 26px;border:0;background:#273253;color:#fff;font-weight:700;cursor:pointer}
        .pdl-hp-empty{display:none;padding:22px;border:1px dashed #cbd5e1;border-radius:10px;background:#fff;text-align:center;color:#667085}
        @media(max-width:782px){#pdl-hidden-plugins-wrap{margin:18px 12px 48px}.pdl-hp-head,.pdl-hp-footer,.pdl-hp-summary{display:block}.pdl-hp-badge,.pdl-hp-save{margin-top:14px}.pdl-hp-toolbar{grid-template-columns:1fr 1fr}.pdl-hp-search{grid-column:1/-1}.pdl-hp-row{grid-template-columns:minmax(0,1fr) auto}.pdl-hp-hidden-text{display:none}}
    </style>
    <?php
}
add_action( 'admin_head', 'pdl_hidden_plugins_admin_styles' );

function pdl_hidden_plugins_settings_page() {
    if ( ! pdl_hidden_plugins_is_super_admin() ) {
        wp_die( 'Bạn không có quyền truy cập trang này.' );
    }

    $plugins = pdl_hidden_plugins_get_installed_plugins();
    uasort(
        $plugins,
        function( $a, $b ) {
            return strcasecmp( (string) ( $a['Name'] ?? '' ), (string) ( $b['Name'] ?? '' ) );
        }
    );

    $hidden = pdl_hidden_plugins_list();
    ?>
    <div id="pdl-hidden-plugins-wrap">
        <div class="pdl-hp-head">
            <div aria-hidden="true">Plugins</div>
            <div>
                <h1>PDL Hidden Plugins</h1>
                <p>Ẩn plugin khỏi trang Plugins cho các tài khoản không phải super admin.</p>
            </div>
            <span class="pdl-hp-badge">Super admin only</span>
        </div>

        <form method="post">
            <?php wp_nonce_field( 'pdl_hidden_plugins_save', 'pdl_hidden_plugins_nonce' ); ?>

            <div class="pdl-hp-toolbar">
                <input class="pdl-hp-search" type="search" placeholder="Tìm plugin, folder/file.php..." oninput="pdlHiddenPluginsFilter(this.value)">
                <button type="button" onclick="pdlHiddenPluginsToggleAll(false)">Hiện tất cả</button>
                <button type="button" onclick="pdlHiddenPluginsToggleAll(true)">Ẩn tất cả</button>
            </div>

            <div class="pdl-hp-summary">
                <span><?php echo esc_html( count( $plugins ) ); ?> plugin đang cài đặt.</span>
                <span>Đang ẩn: <strong id="pdl-hp-hidden-count"><?php echo esc_html( count( array_intersect( $hidden, array_keys( $plugins ) ) ) ); ?></strong> plugin</span>
            </div>

            <div class="pdl-hp-list" id="pdl-hp-list">
                <?php foreach ( $plugins as $plugin_file => $plugin_data ) :
                    $plugin_name = (string) ( $plugin_data['Name'] ?? $plugin_file );
                    $is_hidden   = in_array( $plugin_file, $hidden, true );
                    $search_text = strtolower( $plugin_name . ' ' . $plugin_file );
                    ?>
                    <div class="pdl-hp-row <?php echo $is_hidden ? 'pdl-hp-hidden' : ''; ?>" data-search="<?php echo esc_attr( $search_text ); ?>">
                        <div>
                            <p class="pdl-hp-name"><?php echo esc_html( $plugin_name ); ?></p>
                            <p class="pdl-hp-file"><?php echo esc_html( $plugin_file ); ?></p>
                        </div>
                        <label class="pdl-hp-switch">
                            <input type="checkbox" name="pdl_hidden_plugins[]" value="<?php echo esc_attr( $plugin_file ); ?>" <?php checked( $is_hidden ); ?> onchange="pdlHiddenPluginsOnChange(this)">
                            <span class="pdl-hp-slider"></span>
                        </label>
                        <span class="pdl-hp-hidden-text"><?php echo $is_hidden ? 'ẨN' : ''; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="pdl-hp-empty" id="pdl-hp-empty">Không tìm thấy plugin phù hợp.</div>

            <div class="pdl-hp-footer">
                <span>Super admin vẫn thấy đầy đủ plugin để quản trị và chỉnh danh sách ẩn.</span>
                <button class="pdl-hp-save" type="submit">Lưu cấu hình</button>
            </div>
        </form>
    </div>

    <script>
    function pdlHiddenPluginsOnChange(cb){
        var row=cb.closest('.pdl-hp-row'),label=row?row.querySelector('.pdl-hp-hidden-text'):null;
        if(cb.checked){row&&row.classList.add('pdl-hp-hidden');label&&(label.textContent='ẨN');}
        else{row&&row.classList.remove('pdl-hp-hidden');label&&(label.textContent='');}
        pdlHiddenPluginsUpdateCount();
    }
    function pdlHiddenPluginsToggleAll(state){
        document.querySelectorAll('#pdl-hp-list input[type=checkbox]').forEach(function(cb){cb.checked=state;pdlHiddenPluginsOnChange(cb);});
    }
    function pdlHiddenPluginsFilter(term){
        var query=(term||'').trim().toLowerCase(),visible=0;
        document.querySelectorAll('.pdl-hp-row').forEach(function(row){
            var match=!query||(row.dataset.search||'').indexOf(query)!==-1;
            row.style.display=match?'':'none';if(match)visible++;
        });
        var empty=document.getElementById('pdl-hp-empty');if(empty)empty.style.display=visible?'none':'block';
    }
    function pdlHiddenPluginsUpdateCount(){
        var el=document.getElementById('pdl-hp-hidden-count');if(el)el.textContent=document.querySelectorAll('#pdl-hp-list input:checked').length;
    }
    </script>
    <?php
}
