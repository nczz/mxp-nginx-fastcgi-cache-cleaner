<?php
/*
Plugin Name: Nginx FastCGI 快取清除外掛
Plugin URI:
Description: 輕量且分桌面與行動裝置的版本一起清除
Author: Chun
Version: 1.0.0
Author URI: https://www.mxp.tw/
 */

if (!defined('WPINC')) {
    die;
}
//沿用 Nginx Helper 方法定義
if (!defined('RT_WP_NGINX_HELPER_CACHE_PATH')) {
    define('RT_WP_NGINX_HELPER_CACHE_PATH', '/tmp/nginx-cache');
}

function mxp_nxfcgi_toolbar_purge_link($wp_admin_bar) {

    if (!current_user_can('manage_options')) {
        return;
    }

    if (is_admin()) {
        $clean_page = 'all';
        $link_title = '清除全站快取';
    } else {
        $clean_page = 'current-url';
        $link_title = '清除當前頁面快取';
    }

    $purge_url = add_query_arg(
        array(
            'mxp_clean_action' => 'purge',
            'mxp_clean_page'   => $clean_page,
        )
    );

    $nonced_url = wp_nonce_url($purge_url, 'mxp-purge_all');

    $wp_admin_bar->add_menu(
        array(
            'id'    => 'mxp-purge-all',
            'title' => $link_title,
            'href'  => $nonced_url,
            'meta'  => array('title' => $link_title),
        )
    );

}
add_action('admin_bar_menu', 'mxp_nxfcgi_toolbar_purge_link', 100);

function mxp_nxfcgi_admin_menu() {
    add_submenu_page(
        'options-general.php',
        'Nginx FastCGI 快取',
        'Nginx FastCGI 快取',
        'manage_options',
        'mxp_nxfcgi_setting',
        'mxp_nxfcgi_setting_page_callback'
    );
}
add_action('admin_menu', 'mxp_nxfcgi_admin_menu');

function mxp_nxfcgi_setting_page_callback() {
    include plugin_dir_path(__FILE__) . '/mxp-nxfcgi-settings.php';
}

function mxp_nginx_fastcgi_purge_all_admin_bar() {
    global $wp;
    $method = filter_input(INPUT_SERVER, 'REQUEST_METHOD', FILTER_SANITIZE_STRING);
    if ('POST' === $method) {
        $action = filter_input(INPUT_POST, 'mxp_clean_action', FILTER_SANITIZE_STRING);
    } else {
        $action = filter_input(INPUT_GET, 'mxp_clean_action', FILTER_SANITIZE_STRING);
    }

    if (empty($action)) {
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_die('抱歉，你無權操作此功能！');
    }

    if ('done' === $action) {

        add_action('admin_notices', 'mxp_purge_success_display_notices');
        add_action('network_admin_notices', 'mxp_purge_success_display_notices');
        return;

    }

    check_admin_referer('mxp-purge_all');

    $current_url = user_trailingslashit(home_url($wp->request));

    if (!is_admin()) {
        $action       = 'purge_current_page';
        $redirect_url = $current_url;
    } else {
        $redirect_url = add_query_arg(array('mxp_clean_action' => 'done'));
    }

    switch ($action) {
    case 'purge':
        mxp_nginx_fastcgi_purge_all(RT_WP_NGINX_HELPER_CACHE_PATH, false);
        break;
    case 'purge_current_page':
        mxp_nginx_fastcgi_purge_url($current_url);
        break;
    }

    wp_redirect(esc_url_raw($redirect_url));
    exit();

}
add_action('admin_bar_init', 'mxp_nginx_fastcgi_purge_all_admin_bar');

function mxp_purge_success_display_notices() {
    echo '<div class="updated"><p>成功清除快取！</p></div>';
}

function mxp_nginx_fastcgi_purge_url($url) {
    //檢查連結是否正確
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    $url_data = wp_parse_url($url);

    //要清除的版本
    $page_version = array('desktop' => 0, 'mobile' => 1);
    foreach ($page_version as $version => $key) {

        //組合快取檔案
        $hash = md5($url_data['scheme'] . 'GET' . $url_data['host'] . $url_data['path'] . $key);

        //組合路徑
        $cache_path = RT_WP_NGINX_HELPER_CACHE_PATH;
        $cache_path = ('/' === substr($cache_path, -1)) ? $cache_path : $cache_path . '/';

        //組合快取檔案路徑
        $cached_file = $cache_path . substr($hash, -1) . '/' . substr($hash, -3, 2) . '/' . $hash;
        //確認是否存在快取檔案，並移除
        if (file_exists($cached_file)) {
            if (unlink($cached_file)) {
                error_log('清除 ' . $url . ' 頁面快取成功。');
            } else {
                error_log('CLEAN_CACHE_ERROR');
            }
        } else {
            error_log($version . ' 版本的 ' . $url . ' 快取檔案不存在');
        }
    }
}
/**
 * Unlink file recursively.
 * Source - http://stackoverflow.com/a/1360437/156336
 *
 * @param string $dir Directory.
 * @param bool   $delete_root_too Delete root or not.
 *
 * @return void
 */
function mxp_nginx_fastcgi_purge_all($dir, $delete_root_too) {
    if (!is_dir($dir)) {
        return;
    }
    $dh = opendir($dir);
    if (!$dh) {
        return;
    }
    while (false !== ($obj = readdir($dh))) {
        if ('.' === $obj || '..' === $obj) {
            continue;
        }
        if (!@unlink($dir . '/' . $obj)) {
            mxp_nginx_fastcgi_purge_all($dir . '/' . $obj, true);
        }
    }
    if ($delete_root_too) {
        rmdir($dir);
    }
    closedir($dh);
}

function mxp_nginx_fastcgi_purge_homepage() {
    // WPML installetd?.
    if (function_exists('icl_get_home_url')) {
        $homepage_url = trailingslashit(icl_get_home_url());
    } else {
        $homepage_url = trailingslashit(home_url());
    }
    mxp_nginx_fastcgi_purge_url($homepage_url);
    return true;

}

function mxp_nxfcgi_purge_by_options($post_id, $_purge_page, $_purge_archive, $_purge_custom_taxa) {

    $_post_type = get_post_type($post_id);

    if ($_purge_page) {
        $post_status = get_post_status($post_id);

        // if ('publish' !== $post_status) {
        //     if (!function_exists('get_sample_permalink')) {
        //         require_once ABSPATH . '/wp-admin/includes/post.php';
        //     }
        //     $url = get_sample_permalink($post_id);
        //     if (!empty($url[0]) && !empty($url[1])) {
        //         $url = str_replace('%postname%', $url[1], $url[0]);
        //     } else {
        //         $url = '';
        //     }
        // } else {
        $url = get_permalink($post_id);
        // }

        if (empty($url) && !is_array($url)) {
            return;
        }

        if ('trash' === $post_status) {
            $url = str_replace('__trashed', '', $url);
        }
        mxp_nginx_fastcgi_purge_url($url);
    }

    if ($_purge_archive) {

        $_post_type_archive_link = "";

        if (function_exists('get_post_type_archive_link')) {
            $_post_type_archive_link = get_post_type_archive_link($_post_type);
            mxp_nginx_fastcgi_purge_url($_post_type_archive_link);
        }
        error_log("permalink_structure: XXX > " . get_option('permalink_structure'));
        if ('post' === $_post_type) {
            $day   = get_the_time('d', $post_id);
            $month = get_the_time('m', $post_id);
            $year  = get_the_time('Y', $post_id);
            if ($year) {
                mxp_nginx_fastcgi_purge_url(get_year_link($year));
                if ($month) {
                    mxp_nginx_fastcgi_purge_url(get_month_link($year, $month));
                    if ($day) {
                        mxp_nginx_fastcgi_purge_url(get_day_link($year, $month, $day));
                    }
                }
            }
        }

        $categories = wp_get_post_categories($post_id);

        if (!is_wp_error($categories)) {
            foreach ($categories as $category_id) {
                mxp_nginx_fastcgi_purge_url(get_category_link($category_id));
            }
        }

        $tags = get_the_tags($post_id);

        if (!is_wp_error($tags) && !empty($tags)) {
            foreach ($tags as $tag) {
                mxp_nginx_fastcgi_purge_url(get_tag_link($tag->term_id));
            }
        }
        $author_id = get_post($post_id)->post_author;
        if (!empty($author_id)) {
            mxp_nginx_fastcgi_purge_url(get_author_posts_url($author_id));
        }
    }

    if ($_purge_custom_taxa) {
        $custom_taxonomies = get_taxonomies(
            array(
                'public'   => true,
                '_builtin' => false,
            )
        );

        if (!empty($custom_taxonomies)) {
            foreach ($custom_taxonomies as $taxon) {
                if (!in_array($taxon, array('category', 'post_tag', 'link_category'), true)) {
                    $terms = get_the_terms($post_id, $taxon);
                    if (!is_wp_error($terms) && !empty($terms)) {
                        foreach ($terms as $term) {
                            mxp_nginx_fastcgi_purge_url(get_term_link($term, $taxon));
                        }
                    }
                }
            }
        }
    }
    //清除特定連結
    mxp_nxfcgi_custom_purge_urls();
}

function mxp_nxfcgi_purge_post($post_id) {
    // 紀錄狀態用
    // switch (current_filter()) {
    // case 'publish_post':
    //     break;
    // case 'publish_page':
    //     break;
    // case 'comment_post':
    // case 'wp_set_comment_status':
    //     break;
    // default:
    //     break;
    // }

    //清除首頁
    mxp_nginx_fastcgi_purge_homepage();

    if ('comment_post' === current_filter() || 'wp_set_comment_status' === current_filter()) {
        mxp_nxfcgi_purge_by_options(
            $post_id,
            true, //purge_page_on_new_comment
            false, //purge_archive_on_new_comment
            false//purge_archive_on_new_comment
        );

    } else {
        mxp_nxfcgi_purge_by_options(
            $post_id,
            true, //purge_page_on_mod
            false, //purge_archive_on_edit
            false//purge_archive_on_edit
        );
    }
}
//特別指定要清除的網址
function mxp_nxfcgi_custom_purge_urls() {
    $parse      = wp_parse_url(home_url());
    $purge_urls = !empty(get_option('mxp_nxfcgi_custom_purge_urls', "")) ?
    explode("\r\n", get_option('mxp_nxfcgi_custom_purge_urls')) : array();

    $purge_urls = apply_filters('mxp_nxfcgi_custom_purge_urls', $purge_urls);

    $_url_purge_base = $parse['scheme'] . '://' . $parse['host'];
    if (is_array($purge_urls) && !empty($purge_urls)) {
        foreach ($purge_urls as $purge_url) {
            $purge_url = trim($purge_url);
            if (strpos($purge_url, '/') !== 0) {
                $purge_url = '/' . $purge_url;
            }
            if (strpos($purge_url, '*') === false) {
                $purge_url = $_url_purge_base . $purge_url;
                mxp_nginx_fastcgi_purge_url($purge_url);

            }
        }
    }
}

function mxp_nxfcgi_purge_on_term_taxonomy_edited($term_id, $tt_id, $taxon) {
    mxp_nginx_fastcgi_purge_homepage();
    return true;
}
add_action('delete_term', 'mxp_nxfcgi_purge_on_term_taxonomy_edited', 20, 3);
add_action('edit_term', 'mxp_nxfcgi_purge_on_term_taxonomy_edited', 20, 3);

function mxp_nxfcgi_purge_post_on_comment($comment_id, $comment) {
    $oldstatus = '';
    $approved  = $comment->comment_approved;
    if (null === $approved) {
        $newstatus = false;
    } elseif ('1' === $approved) {
        $newstatus = 'approved';
    } elseif ('0' === $approved) {
        $newstatus = 'unapproved';
    } elseif ('spam' === $approved) {
        $newstatus = 'spam';
    } elseif ('trash' === $approved) {
        $newstatus = 'trash';
    } else {
        $newstatus = false;
    }
    mxp_nxfcgi_purge_post_on_comment_change($newstatus, $oldstatus, $comment);
}
add_action('wp_insert_comment', 'mxp_nxfcgi_purge_post_on_comment', 200, 2);

function mxp_nxfcgi_purge_post_on_comment_change($newstatus, $oldstatus, $comment) {
    $_post_id    = $comment->comment_post_ID;
    $_comment_id = $comment->comment_ID;
    error_log("COMMENT STATUS LOG: " . $oldstatus . "_to_" . $newstatus . " PID: " . $_post_id . " CID: " . $_comment_id);
    switch ($newstatus) {
    case 'approved':
        mxp_nxfcgi_purge_post($_post_id);
        break;
    case 'spam':
    case 'unapproved':
    case 'trash':
        mxp_nxfcgi_purge_post($_post_id);
        break;
    default:
        break;
    }
}
add_action('transition_comment_status', 'mxp_nxfcgi_purge_post_on_comment_change', 200, 3);

function mxp_nxfcgi_check_post_status($new_status, $old_status, $post) {
    $case = $old_status . "_to_" . $new_status;
    error_log("POST STATUS LOG: " . $case . " PID: " . $post->ID);
    switch ($case) {
    case 'publish_to_publish': //更新內容
    case 'publish_to_trash': //刪除內容
    case 'future_to_publish': //排程發佈
    case 'new_to_publish': //發佈內容
    case 'trash_to_publish': //誤刪內容
    case 'auto-draft_to_publish': //直接發佈
    case 'draft_to_publish': //草稿發佈
        error_log("CLEANING STATUS: " . $case . " PID: " . $post->ID);
        mxp_nginx_fastcgi_purge_homepage();
        mxp_nxfcgi_purge_by_options(
            $post->ID,
            true, //清除頁面
            true, //清除列表頁（處理內建預設的分類與標籤）
            true//清除分類頁（如有CPT需要開啟）
        );
        break;
    default:
        break;
    }
}
add_action('transition_post_status', 'mxp_nxfcgi_check_post_status', 200, 3);
