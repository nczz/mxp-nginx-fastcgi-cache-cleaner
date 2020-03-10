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
		<span>Nginx FastCGI Purge Settings</span>
	</h3>
	<div class="inside">
		<table class="form-table">
			<tbody>
				<tr valign="top">
				<th scope="row">
					<h4>Custom Page Links:</h4>
				</th>
				<td>
					<textarea rows="5" cols="50" class="" id="custom_purge_urls" name="custom_purge_urls"><?php echo esc_textarea(get_option('mxp_nxfcgi_custom_purge_urls', "")); ?></textarea>
					<p class="description">
						Links list per line without including DOMAIN and SCHEMA.<br>Example: If you wish clean <code>http://example.com/sample-page/</code> only need to type <strong><code>/sample-page/</code></strong>(Notice: End of link with or without <code>/</code>symbol, It means different url.)</p>
				</td>
				</tr>
			</tbody>
		</table>
	</div> <!-- End of .inside -->
</div>
<?php
wp_nonce_field('mxp_nxfcgi_save_form', '_mxp_nonce');
submit_button('Save', 'primary large', 'mxp_nxfcgi_save', true);
?>
</form>