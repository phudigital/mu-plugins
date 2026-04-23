<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function pdl_dashboard_widget_content() {
    $year = date('Y');
    echo <<<HTML
<div id="pdl-widget-wrap">
<style>
    #pdl-widget-wrap .pw{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;}
    #pdl-widget-wrap .pw-head{background:#273253;padding:16px 20px;display:flex;align-items:center;gap:14px;margin:-12px -12px 0;}
    #pdl-widget-wrap .pw-logo{width:48px;height:48px;border-radius:8px;background:#fff;display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;}
    #pdl-widget-wrap .pw-logo img{width:42px;height:42px;object-fit:contain;}
    #pdl-widget-wrap .pw-htitle{color:#fff;font-size:14px;font-weight:700;margin:0 0 3px;}
    #pdl-widget-wrap .pw-hsub{color:rgba(255,255,255,.6);font-size:11px;margin:0;}
    #pdl-widget-wrap .pw-bars{margin:0 -12px;}
    #pdl-widget-wrap .pw-bar{padding:9px 16px;font-size:12px;font-weight:600;display:none;border-left:3px solid;border-bottom:1px solid transparent;}
    #pdl-widget-wrap .pw-bar.info   {background:#e8f0fe;color:#1a4fa0;border-left-color:#3b6fd4;border-bottom-color:#d0defa;}
    #pdl-widget-wrap .pw-bar.warning{background:#fff8e1;color:#8a5c00;border-left-color:#f5a623;border-bottom-color:#faecc0;}
    #pdl-widget-wrap .pw-bar.error  {background:#fdecea;color:#9b2121;border-left-color:#e53935;border-bottom-color:#facece;}
    #pdl-widget-wrap .pw-bar.success{background:#e8f5e9;color:#1b5e20;border-left-color:#43a047;border-bottom-color:#c5e8c7;}
    #pdl-widget-wrap .pw-bar-inner{display:flex;align-items:center;justify-content:space-between;gap:12px;}
    #pdl-widget-wrap .pw-bar-text{min-width:0;line-height:1.45;}
    #pdl-widget-wrap .pw-bar-btn{display:inline-flex;align-items:center;justify-content:center;padding:3px 12px;border-radius:999px;background:transparent;color:#273253;text-decoration:none;font-size:11px;font-weight:700;white-space:nowrap;box-shadow:0 6px 14px rgba(39,50,83,.12);transition:transform .18s ease,box-shadow .18s ease,opacity .18s ease;}
    #pdl-widget-wrap .pw-bar-btn:hover{transform:translateY(-1px);box-shadow:0 8px 16px rgba(39,50,83,.18);opacity:.96;}
    #pdl-widget-wrap .pw-bar-domain.info{background:#e8f0fe;color:#1a4fa0;border-left-color:#3b6fd4;border-bottom-color:#d0defa;}
    #pdl-widget-wrap .pw-bar-domain.warning{background:#e8f0fe;color:#1a4fa0;border-left-color:#3b6fd4;border-bottom-color:#d0defa;}
    #pdl-widget-wrap .pw-bar-domain.error{background:#e8f0fe;color:#1a4fa0;border-left-color:#3b6fd4;border-bottom-color:#d0defa;}
    #pdl-widget-wrap .pw-bar-domain.success{background:#e8f0fe;color:#1a4fa0;border-left-color:#3b6fd4;border-bottom-color:#d0defa;}
    #pdl-widget-wrap .pw-inner{padding:14px 0 2px;}
    #pdl-widget-wrap .pw-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px;}
    #pdl-widget-wrap .pw-card{background:#eef0f6;border-radius:8px;padding:10px 12px;display:flex;align-items:center;gap:9px;}
    #pdl-widget-wrap .pw-icon{width:32px;height:32px;border-radius:7px;background:#273253;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    #pdl-widget-wrap .pw-icon svg{width:15px;height:15px;}
    #pdl-widget-wrap .pw-cl{font-size:10px;font-weight:700;color:#8a93b8;text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:2px;}
    #pdl-widget-wrap .pw-cv{font-size:12.5px;font-weight:700;margin:0;}
    #pdl-widget-wrap .pw-cv a{color:#273253;text-decoration:none;}
    #pdl-widget-wrap .pw-cv a:hover{text-decoration:underline;}
    #pdl-widget-wrap .pw-foot{border-top:1px solid #dde1ef;padding-top:10px;display:flex;justify-content:space-between;align-items:center;}
    #pdl-widget-wrap .pw-copy{font-size:11px;color:#8a93b8;}
    #pdl-widget-wrap .pw-link{font-size:12px;font-weight:700;color:#273253;text-decoration:none;}
    @media (max-width: 680px){
        #pdl-widget-wrap .pw-head{padding:14px 16px;gap:12px;align-items:flex-start;}
        #pdl-widget-wrap .pw-logo{width:42px;height:42px;}
        #pdl-widget-wrap .pw-logo img{width:36px;height:36px;}
        #pdl-widget-wrap .pw-htitle{font-size:13px;line-height:1.35;}
        #pdl-widget-wrap .pw-hsub{font-size:10.5px;line-height:1.45;}
        #pdl-widget-wrap .pw-bar{padding:10px 14px;}
        #pdl-widget-wrap .pw-bar-inner{flex-direction:column;align-items:stretch;gap:10px;}
        #pdl-widget-wrap .pw-bar-btn{width:100%;padding:9px 12px;}
        #pdl-widget-wrap .pw-inner{padding:12px 0 2px;}
        #pdl-widget-wrap .pw-grid{grid-template-columns:1fr;gap:10px;}
        #pdl-widget-wrap .pw-card{padding:12px;}
        #pdl-widget-wrap .pw-foot{flex-direction:column;align-items:flex-start;gap:8px;}
        #pdl-widget-wrap .pw-link{display:inline-flex;align-items:center;gap:6px;}
    }
</style>
<div class="pw">
    <div id="pdl-head" class="pw-head">
        <div class="pw-logo"><span style="color:#273253;font-weight:800;font-size:12px;">PDL</span></div>
        <div><p class="pw-htitle">Đang tải...</p><p class="pw-hsub">pdl.vn</p></div>
    </div>
    <div class="pw-bars">
        <div id="pdl-b-notify" class="pw-bar"></div>
        <div id="pdl-b-site"   class="pw-bar"></div>
        <div id="pdl-b-expire" class="pw-bar"></div>
    </div>
    <div class="pw-inner">
        <div id="pdl-grid" class="pw-grid">
            <p style="color:#8a93b8;font-size:12px;padding:8px 0;grid-column:span 2;text-align:center;">Đang kết nối...</p>
        </div>
        <div class="pw-foot">
            <span class="pw-copy">&copy; {$year} Công Ty TNHH Giải Pháp PDL</span>
            <a class="pw-link" href="https://pdl.vn" target="_blank">pdl.vn &rarr;</a>
        </div>
    </div>
</div>
</div>
<script>
(function(){
    var ICONS = {
        phone:'<svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.81a19.79 19.79 0 01-3.07-8.69A2 2 0 012 1h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 8.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>',
        mail: '<svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
        web:  '<svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/></svg>'
    };
    function getIcon(c){return c.phone?ICONS.phone:c.email?ICONS.mail:ICONS.web;}
    function escapeHtml(value){
        return String(value).replace(/[&<>"']/g,function(char){
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char];
        });
    }
    function normalizeText(value){
        return String(value||'').trim();
    }
    function getSafeUrl(value){
        value=normalizeText(value);
        if(!value) return '';
        try{
            var url=new URL(value,location.origin);
            return /^(https?:|mailto:|tel:)$/.test(url.protocol)?url.href:'';
        }catch(e){
            return '';
        }
    }
    function getLink(c){
        var display=normalizeText(c.display||c.phone||c.email||c.url);
        var href=getSafeUrl(c.link_url||'');
        if(!href&&c.phone) href=getSafeUrl('tel:'+c.phone);
        if(!href&&c.email) href=getSafeUrl('mailto:'+c.email);
        if(!href&&c.url) href=getSafeUrl(c.url);
        if(!href) return escapeHtml(display);
        return '<a href="'+escapeHtml(href)+'" target="_blank" rel="noopener noreferrer">'+escapeHtml(display)+'</a>';
    }
    function buildBarContent(msg,buttonText,buttonUrl){
        msg=normalizeText(msg);
        buttonText=normalizeText(buttonText);
        var html='<div class="pw-bar-inner"><span class="pw-bar-text">'+escapeHtml(msg)+'</span>';
        var safeUrl=getSafeUrl(buttonUrl);
        if(buttonText&&safeUrl){
            html+='<a class="pw-bar-btn" href="'+escapeHtml(safeUrl)+'" target="_blank" rel="noopener noreferrer">'+escapeHtml(buttonText)+'</a>';
        }
        html+='</div>';
        return html;
    }
    function showBar(id,type,msg,buttonText,buttonUrl){
        var el=document.getElementById(id);
        msg=normalizeText(msg);
        if(!msg){el.style.display='none';return;}
        el.className='pw-bar '+type+(id==='pdl-b-site'?' pw-bar-domain':'');
        el.innerHTML=buildBarContent(msg,buttonText,buttonUrl);
        el.style.display='block';
    }
    function daysUntil(str){
        var d=new Date(str),now=new Date();
        now.setHours(0,0,0,0);
        return Math.round((d-now)/86400000);
    }
    function formatDate(str){
        var p=str.split('-');return p[2]+'/'+p[1]+'/'+p[0];
    }
    function renderExpire(info){
        if(!info||!info.expire)return;
        var days=daysUntil(info.expire);
        var note=info.hosting_note?' ('+info.hosting_note+')':'';
        var type,msg;
        if(days<0){
            type='error';
            msg='Hosting đã hết hạn '+Math.abs(days)+' ngày trước'+note+'. Liên hệ PDL ngay!';
        } else if(days<=14){
            type='error';
            msg='Hosting hết hạn sau '+days+' ngày - '+formatDate(info.expire)+note;
        } else if(days<=30){
            type='warning';
            msg='Hosting sắp hết hạn sau '+days+' ngày - '+formatDate(info.expire)+note;
        } else {
            type='success';
            msg='Dịch vụ còn hạn đến '+formatDate(info.expire)+' ('+days+' ngày)'+note;
        }
        showBar('pdl-b-expire',type,msg);
    }
    var domain=location.hostname.replace(/^www\./,'');
    var bust=Math.floor(Date.now()/3600000);
    fetch('https://pdl.vn/brand.json?_='+bust)
        .then(function(r){return r.json();})
        .then(function(d){
            var upd=d.updated_at?' &nbsp;&middot;&nbsp; Cập nhật: '+formatDate(d.updated_at):'';
            document.getElementById('pdl-head').innerHTML=
                '<div class="pw-logo"><img src="'+d.logo+'" alt="PDL"></div>'+
                '<div><p class="pw-htitle">'+d.company+'</p>'+
                '<p class="pw-hsub">'+d.address+upd+'</p></div>';
            if(d.notify&&d.notify.active)
                showBar('pdl-b-notify',d.notify.type||'info',d.notify.message,d.notify.button_text,d.notify.button_url);
            var site=d.domains?d.domains[domain]:null;
            if(site){
                if(site.notify&&site.notify.active)
                    showBar('pdl-b-site',site.notify.type||'info',site.notify.message,site.notify.button_text,site.notify.button_url);
                renderExpire(site);
            }
            document.getElementById('pdl-grid').innerHTML=d.contacts.map(function(c){
                return '<div class="pw-card">'+
                    '<div class="pw-icon">'+getIcon(c)+'</div>'+
                    '<div><span class="pw-cl">'+c.label+'</span>'+
                    '<p class="pw-cv">'+getLink(c)+'</p></div></div>';
            }).join('');
        })
        .catch(function(){
            document.getElementById('pdl-grid').innerHTML=
                '<p style="color:#c0392b;font-size:12px;padding:8px 0;grid-column:span 2">'+
                'Không tải được dữ liệu. Liên hệ: <a href="tel:0901110008" style="color:#273253;">0901 11 0008</a></p>';
        });
})();
</script>
HTML;
}

function pdl_add_dashboard_widget() {
    wp_add_dashboard_widget(
        'pdl_brand_widget',
        'Thông tin & Hỗ trợ - PDL',
        'pdl_dashboard_widget_content'
    );
}
add_action( 'wp_dashboard_setup', 'pdl_add_dashboard_widget' );

function pdl_force_widget_top_left() {
    global $wp_meta_boxes;

    if ( empty( $wp_meta_boxes['dashboard'] ) ) {
        return;
    }

    $contexts = [ 'normal', 'side', 'column3', 'column4' ];
    $widget   = null;

    foreach ( $contexts as $context ) {
        if ( empty( $wp_meta_boxes['dashboard'][ $context ] ) ) {
            continue;
        }

        foreach ( $wp_meta_boxes['dashboard'][ $context ] as $priority => $boxes ) {
            if ( isset( $boxes['pdl_brand_widget'] ) ) {
                $widget = $boxes['pdl_brand_widget'];
                unset( $wp_meta_boxes['dashboard'][ $context ][ $priority ]['pdl_brand_widget'] );
            }
        }
    }

    if ( null === $widget ) {
        return;
    }

    // Re-add the widget in the highest-priority slot of the left column.
    $wp_meta_boxes['dashboard']['normal']['high'] = array_merge(
        [ 'pdl_brand_widget' => $widget ],
        $wp_meta_boxes['dashboard']['normal']['high'] ?? []
    );
}
add_action( 'wp_dashboard_setup', 'pdl_force_widget_top_left', 999 );

function pdl_force_dashboard_widget_order( $order ) {
    if ( ! is_array( $order ) ) {
        $order = [];
    }

    $contexts = [ 'normal', 'side', 'column3', 'column4' ];

    foreach ( $contexts as $context ) {
        $ids = array_filter( array_map( 'trim', explode( ',', (string) ( $order[ $context ] ?? '' ) ) ) );
        $ids = array_values( array_diff( $ids, [ 'pdl_brand_widget' ] ) );

        if ( 'normal' === $context ) {
            array_unshift( $ids, 'pdl_brand_widget' );
        }

        $order[ $context ] = implode( ',', array_unique( $ids ) );
    }

    return $order;
}
add_filter( 'get_user_option_meta-box-order_dashboard', 'pdl_force_dashboard_widget_order' );

function pdl_force_widget_visible( $boxes ) {
    if ( ! is_array( $boxes ) ) {
        return [];
    }

    return array_values( array_diff( $boxes, [ 'pdl_brand_widget' ] ) );
}
add_filter( 'get_user_option_closedpostboxes_dashboard', 'pdl_force_widget_visible' );
add_filter( 'get_user_option_metaboxhidden_dashboard', 'pdl_force_widget_visible' );

function pdl_force_dashboard_two_columns( $columns ) {
    if ( ! is_array( $columns ) ) {
        $columns = [];
    }

    $columns['dashboard'] = 2;

    return $columns;
}
add_filter( 'screen_layout_columns', 'pdl_force_dashboard_two_columns' );

function pdl_force_dashboard_layout( $layout ) {
    return 2;
}
add_filter( 'get_user_option_screen_layout_dashboard', 'pdl_force_dashboard_layout' );

function pdl_lock_dashboard_widget_position() {
    ?>
    <script>
    (function(){
        function movePdlWidget(){
            var widget = document.getElementById('pdl_brand_widget');
            var leftColumn = document.getElementById('normal-sortables');

            if(!widget || !leftColumn){
                return;
            }

            if(leftColumn.firstElementChild !== widget){
                leftColumn.insertBefore(widget, leftColumn.firstElementChild);
            }
        }

        movePdlWidget();
        document.addEventListener('DOMContentLoaded', movePdlWidget);
        window.addEventListener('load', movePdlWidget);

        if(window.MutationObserver){
            new MutationObserver(movePdlWidget).observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    })();
    </script>
    <?php
}
add_action( 'admin_print_footer_scripts-index.php', 'pdl_lock_dashboard_widget_position', 999 );

function pdl_custom_admin_footer_text() {
    return wp_kses(
        'Phát triển bởi <a href="https://pdl.vn" target="_blank" rel="noopener noreferrer">PDL Solutions</a> (Phú Digital)',
        [
            'a' => [
                'href'   => [],
                'target' => [],
                'rel'    => [],
            ],
        ]
    );
}
add_filter( 'admin_footer_text', 'pdl_custom_admin_footer_text', 999 );
