<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'mgr_add_menu' );
add_action( 'admin_init', 'mgr_register_settings' );
add_action( 'admin_post_mgr_manual_sync', 'mgr_handle_manual_sync' );
add_action( 'admin_post_mgr_toggle_review', 'mgr_handle_toggle_review' );

function mgr_add_menu() {
	add_options_page(
		'Google Reviews',
		'Google Reviews',
		'manage_options',
		'my-google-reviews',
		'mgr_render_admin_page'
	);
}

function mgr_register_settings() {
	register_setting( 'mgr_settings', 'mgr_api_key',  [ 'sanitize_callback' => 'sanitize_text_field' ] );
	register_setting( 'mgr_settings', 'mgr_place_id', [ 'sanitize_callback' => 'sanitize_text_field' ] );
	register_setting( 'mgr_settings', 'mgr_language', [ 'sanitize_callback' => 'sanitize_text_field', 'default' => 'en' ] );
}

function mgr_handle_manual_sync() {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'mgr_manual_sync' ) ) {
		wp_die( 'Unauthorised' );
	}

	$result = mgr_sync_reviews();

	$status = is_wp_error( $result ) ? 'error&msg=' . rawurlencode( $result->get_error_message() ) : 'synced';
	wp_safe_redirect( add_query_arg( [ 'page' => 'my-google-reviews', 'mgr' => $status ], admin_url( 'options-general.php' ) ) );
	exit;
}

function mgr_handle_toggle_review() {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'mgr_toggle_review' ) ) {
		wp_die( 'Unauthorised' );
	}

	global $wpdb;
	$id    = (int) ( $_POST['review_id'] ?? 0 );
	$table = $wpdb->prefix . 'google_reviews';

	if ( $id ) {
		$current = (int) $wpdb->get_var( $wpdb->prepare( "SELECT hidden FROM $table WHERE id = %d", $id ) );
		$wpdb->update( $table, [ 'hidden' => $current ? 0 : 1 ], [ 'id' => $id ] );
	}

	wp_safe_redirect( add_query_arg( [ 'page' => 'my-google-reviews', 'mgr' => 'toggled' ], admin_url( 'options-general.php' ) ) );
	exit;
}

function mgr_render_admin_page() {
	global $wpdb;
	$table   = $wpdb->prefix . 'google_reviews';
	$reviews = $wpdb->get_results( "SELECT * FROM $table ORDER BY published DESC" );
	$place   = mgr_get_place_details();
	$status  = sanitize_key( $_GET['mgr'] ?? '' );
	$msg     = sanitize_text_field( $_GET['msg'] ?? '' );
	?>
	<div class="wrap">
		<h1>Google Reviews</h1>

		<?php if ( $status === 'synced' ) : ?>
			<div class="notice notice-success is-dismissible"><p>Reviews synced successfully.</p></div>
		<?php elseif ( $status === 'error' ) : ?>
			<div class="notice notice-error is-dismissible"><p>Sync failed: <?php echo esc_html( $msg ); ?></p></div>
		<?php elseif ( $status === 'toggled' ) : ?>
			<div class="notice notice-info is-dismissible"><p>Review visibility updated.</p></div>
		<?php endif; ?>

		<h2>Settings</h2>
		<form method="post" action="options.php">
			<?php settings_fields( 'mgr_settings' ); ?>
			<table class="form-table">
				<tr>
					<th><label for="mgr_api_key">Google Places API Key</label></th>
					<td><input type="password" id="mgr_api_key" name="mgr_api_key" value="<?php echo esc_attr( get_option( 'mgr_api_key' ) ); ?>" class="regular-text" /></td>
				</tr>
				<tr>
					<th><label for="mgr_place_id">Place ID</label></th>
					<td>
						<input type="text" id="mgr_place_id" name="mgr_place_id" value="<?php echo esc_attr( get_option( 'mgr_place_id' ) ); ?>" class="regular-text" />
						<p class="description">Find your Place ID at <a href="https://developers.google.com/maps/documentation/javascript/examples/places-placeid-finder" target="_blank">Google Place ID Finder</a>.</p>
					</td>
				</tr>
				<tr>
					<th><label for="mgr_language">Language Code</label></th>
					<td><input type="text" id="mgr_language" name="mgr_language" value="<?php echo esc_attr( get_option( 'mgr_language', 'en' ) ); ?>" class="small-text" placeholder="en" /></td>
				</tr>
			</table>
			<?php submit_button( 'Save Settings' ); ?>
		</form>

		<h2>Sync Reviews</h2>
		<?php if ( $place ) : ?>
			<p>
				<strong><?php echo esc_html( $place['name'] ); ?></strong>
				&mdash; <?php echo esc_html( $place['address'] ); ?><br>
				Overall rating: <strong><?php echo esc_html( $place['rating'] ); ?></strong>
				(<?php echo esc_html( number_format( $place['total_reviews'] ) ); ?> reviews)
			</p>
		<?php endif; ?>
		<p>Last sync: <strong><?php echo esc_html( get_option( 'mgr_last_sync', 'Never' ) ); ?></strong> &mdash; auto-runs daily.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="mgr_manual_sync">
			<?php wp_nonce_field( 'mgr_manual_sync' ); ?>
			<?php submit_button( 'Sync Now', 'secondary' ); ?>
		</form>

		<h2>Stored Reviews (<?php echo count( $reviews ); ?>)</h2>
		<?php if ( ! $reviews ) : ?>
			<p>No reviews yet. Configure your API key and Place ID, then click Sync Now.</p>
		<?php else : ?>
			<table class="widefat striped" style="max-width:900px">
				<thead>
					<tr>
						<th>Author</th>
						<th>Rating</th>
						<th>Date</th>
						<th>Excerpt</th>
						<th>Visible</th>
						<th>Action</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $reviews as $r ) : ?>
					<tr<?php echo $r->hidden ? ' style="opacity:.5"' : ''; ?>>
						<td><?php echo esc_html( $r->author ); ?></td>
						<td><?php echo esc_html( $r->rating ); ?> ★</td>
						<td><?php echo esc_html( substr( $r->published, 0, 10 ) ); ?></td>
						<td><?php echo esc_html( mb_substr( $r->body, 0, 80 ) ); ?><?php echo mb_strlen( $r->body ) > 80 ? '…' : ''; ?></td>
						<td><?php echo $r->hidden ? 'Hidden' : 'Visible'; ?></td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
								<input type="hidden" name="action" value="mgr_toggle_review">
								<input type="hidden" name="review_id" value="<?php echo (int) $r->id; ?>">
								<?php wp_nonce_field( 'mgr_toggle_review' ); ?>
								<button type="submit" class="button button-small">
									<?php echo $r->hidden ? 'Show' : 'Hide'; ?>
								</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}
