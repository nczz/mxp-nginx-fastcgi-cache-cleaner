<?php
if (!defined('ABSPATH')) {
    exit;
}

$args = array(
    'custom_purge_urls' => FILTER_SANITIZE_STRING,
    'mxp_nxfcgi_save'   => FILTER_SANITIZE_STRING,
    '_mxp_nonce'        => FILTER_SANITIZE_STRING,
);

$all_inputs = filter_input_array(INPUT_POST, $args);

if (isset($all_inputs['mxp_nxfcgi_save']) && wp_verify_nonce($all_inputs['_mxp_nonce'], 'mxp_nxfcgi_save_form')) {
    update_option('mxp_nxfcgi_custom_purge_urls', $all_inputs['custom_purge_urls']);
    echo "DONE!";
}
?>
<form id="post_form" method="post" action="#" name="mxp_nxfcgi_form" class="clearfix">
<div class="postbox">
	<h3 class="hndle">
		<span>Nginx FastCGI 快取清除設定</span>
	</h3>
	<div class="inside">
		<table class="form-table">
			<tbody>
				<tr valign="top">
				<th scope="row">
					<h4>指定連結:</h4>
				</th>
				<td>
					<textarea rows="5" cols="50" class="" id="custom_purge_urls" name="custom_purge_urls"><?php echo esc_textarea(get_option('mxp_nxfcgi_custom_purge_urls', "")); ?></textarea>
					<p class="description">
						一行一個網址，網址不需包含網域名稱與協定。<br>例如： 希望清除的網址是 <code>http://example.com/sample-page/</code> 只需增加 <strong><code>/sample-page/</code></strong> 這行至上方輸入匡。（注意：網址結尾路徑有無包含<code>/</code>符號將視為不同網址）</p>
				</td>
				</tr>
			</tbody>
		</table>
	</div> <!-- End of .inside -->
</div>
<?php
wp_nonce_field('mxp_nxfcgi_save_form', '_mxp_nonce');
submit_button('儲存', 'primary large', 'mxp_nxfcgi_save', true);
?>
</form>