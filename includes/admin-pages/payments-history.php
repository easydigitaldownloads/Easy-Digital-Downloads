<?php
/**
 * Admin Payment History
 *
 * @package     Easy Digital Downloads
 * @subpackage  Admin Payment History
 * @copyright   Copyright (c) 2012, Pippin Williamson
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0 
*/

function edd_payments_column_orderby( $vars ) {
	if ( isset( $vars[ 'orderby' ] ) && 'id' == $vars[ 'orderby' ] ) {
		$vars['orderby'] = 'id';
	}

	if ( isset( $vars[ 'orderby' ] ) && 'email' == $vars[ 'orderby' ] ) {
		$vars = array_merge( $vars, array(
			'meta_key' => '_edd_payment_user_email',
			'orderby'  => 'meta_value'
		) );
	}
 
	return $vars;
}
add_filter( 'request', 'edd_payments_column_orderby' );

function edd_payments_column_register_sortable( $columns ) {
	$columns[ 'order_title' ] = 'id';
	$columns[ 'email' ] = 'email';
 
	return $columns;
}
add_filter( 'manage_edit-edd_payment_sortable_columns', 'edd_payments_column_register_sortable' );

/**
 * 
 */
function edd_filter_payments() {
	global $typenow, $wp_query;

	if ( $typenow != 'edd_payment' )
		return;

	$users = get_users();

	$selected = $_GET[ 'user_email' ];
?>
	<select name="user_email">
		<option value=""><?php _e( 'All Customers', 'edd' ); ?></option>
		<?php foreach ( $users as $user ) : ?>
		<option value="<?php echo $user->user_email; ?>" <?php selected( $selected, $user->user_email ); ?>><?php echo $user->display_name; ?> (<?php echo $user->user_email; ?>)</option>
		<?php endforeach; ?>
	</select>
<?php
}
add_action( 'restrict_manage_posts', 'edd_filter_payments' );

/**
 * 
 */
function edd_payments_order_by_user( $vars ) {
	global $typenow, $wp_query;

	if ( $typenow != 'edd_payment' )
		return $vars;

	if ( ! isset( $_GET[ 'user_email' ] ) )
		return $vars;

	if ( ! is_email( $_GET[ 'user_email' ] ) )
		return $vars;

	$vars[ 'meta_key' ]   = '_edd_payment_user_email';
	$vars[ 'meta_value' ] = $_GET[ 'user_email' ];

	return $vars;
}
add_filter( 'request', 'edd_payments_order_by_user' );

function edd_payment_history_columns( $cols ) {
	$cols = array(
		'cb'       => '<input type="checkbox" />',
		'order_title' => __( 'Order', 'edd' ),
		'email'    => __( 'Email', 'edd' ),
		'price'    => __( 'Price', 'edd' ),
		'ordered'  => __( 'Date', 'edd' ),
		'user'     => __( 'User', 'edd' ),
		'status'   => __( 'Status', 'edd' )
	);

	return $cols;
}
add_filter( 'manage_edd_payment_posts_columns', 'edd_payment_history_columns' );

function edd_payment_history_custom_columns( $column, $post_id ) {
	global $post;

	$payment_meta = get_post_meta( $post->ID, '_edd_payment_meta', true);
	$user_info    = maybe_unserialize( $payment_meta[ 'user_info' ] );
	$user         = new WP_User( $user_info[ 'id' ] );
	$email        = get_post_meta( $post->ID, '_edd_payment_user_email', true );
	$payment      = $post;

	$post_type_object = get_post_type_object( 'edd_payment' );

	$can_edit_post = current_user_can( $post_type_object->cap->edit_post, $post->ID );

	switch ( $column ) {
		case 'order_title':
			echo '<strong><a href="' . admin_url( sprintf( 'post.php?post=%d&action=edit', $post_id ) ) . '" class="row-title">';
			echo sprintf( __( 'Order #%d', 'edd' ), $post_id );
			echo '</a></strong>';
			
			$actions = array();

			if ( current_user_can( 'edit_post', $post->ID ) ) {
				$actions[ 'edit' ] = "<a title='" . esc_attr( __( 'Edit Payment', 'edd' ) ) . "' href='" . admin_url( sprintf( 'post.php?post=%d&action=edit', $post->ID ) ) . "'>" . __( 'Edit' ) . "</a>";
			}

			$actions[ 'resend' ] = "<a title='" . esc_attr( __( 'Resend Purchase Receipt', 'edd' ) ) . "' href='" . admin_url( sprintf( 'edit.php?post_type=edd_payment&edd-action=email_links&purchase_id=%d', $post->ID ) ) . "'>" . __( 'Resend Purchase Receipt' ) . "</a>";

			if ( current_user_can( $post_type_object->cap->delete_post, $post->ID ) ) {
				if ( 'trash' == $post->post_status )
					$actions['untrash'] = "<a title='" . esc_attr( __( 'Restore this item from the Trash', 'edd' ) ) . "' href='" . wp_nonce_url( admin_url( sprintf( $post_type_object->_edit_link . '&amp;action=untrash', $post->ID ) ), 'untrash-' . $post->post_type . '_' . $post->ID ) . "'>" . __( 'Restore', 'edd' ) . "</a>";
				elseif ( EMPTY_TRASH_DAYS )
					$actions['trash'] = "<a class='submitdelete' title='" . esc_attr( __( 'Move this item to the Trash', 'edd' ) ) . "' href='" . get_delete_post_link( $post->ID ) . "'>" . __( 'Trash', 'edd' ) . "</a>";
				if ( 'trash' == $post->post_status || !EMPTY_TRASH_DAYS )
					$actions['delete'] = "<a class='submitdelete' title='" . esc_attr( __( 'Delete this item permanently', 'edd' ) ) . "' href='" . get_delete_post_link( $post->ID, '', true ) . "'>" . __( 'Delete Permanently', 'edd' ) . "</a>";
			}

			$actions = apply_filters( 'post_row_actions', $actions, $post );

			echo '<div class="row-actions">';

			$i = 0;
			$action_count = sizeof($actions);

			foreach ( $actions as $action => $link ) {
				++$i;
				( $i == $action_count ) ? $sep = '' : $sep = ' | ';
				echo "<span class='$action'>$link$sep</span>";
			}
			echo '</div>';

			break;
		case 'email':
			echo $payment_meta[ 'email' ];
			break;
		case 'price' :
			echo edd_currency_filter( $payment_meta[ 'amount' ] );
			break;
		case 'ordered' :
			echo date_i18n( get_option( 'date_format' ), strtotime( $payment->post_date ) );
			break;
		case 'user' :
			$name = $user->display_name;

			if ( ! empty ( $user_info[ 'first_name' ] ) || ! empty( $user_info[ 'last_name' ] ) )
				$name = $user_info[ 'first_name' ] . ' ' . $user_info[ 'last_name' ];

			if ( $user->ID == 0 )
				$name = sprintf( __( '%s (Guest)', 'edd' ), $name );
				
			$name = sprintf( 
				'<a href="%s" title="%s">%s</a>', 
				self_admin_url( sprintf( 'edit.php?post_type=edd_payment&user_email=%s', $email ) ), 
				sprintf( __( 'View all payments by %s', 'edd' ), $name ),
				$name 
			);

			echo $name;

			break;
		case 'status' :
			echo edd_get_payment_status( $payment, true );
			break;
	}
}
add_action( 'manage_posts_custom_column', 'edd_payment_history_custom_columns', 10, 2 );

function edd_add_payment_meta_boxes() {
	add_meta_box( 'buyer-information', __( 'Purchase Information', 'edd' ), 'edd_render_buyer_info_meta_box', 'edd_payment', 'normal', 'default' );
	add_meta_box( 'purchased-files', __( 'Download Information', 'edd' ), 'edd_render_purchased_files_meta_box', 'edd_payment', 'normal', 'default' );
}
add_action( 'add_meta_boxes', 'edd_add_payment_meta_boxes');

function edd_render_buyer_info_meta_box() {
	global $post;

	$payment_meta = get_post_meta( $post->ID, '_edd_payment_meta', true );
	$user_info    = maybe_unserialize( $payment_meta['user_info'] ); 
	$user         = new WP_user( $user_info[ 'id' ] );

	$email        = get_post_meta( $post->ID, '_edd_payment_user_email', true );

	$gateways = edd_get_enabled_payment_gateways();
	$gateway  = get_post_meta( $post->ID, '_edd_payment_gateway', true );

	$status   = $post->post_status;
	$statuses = edd_get_payment_statuses();

	$name = $user->display_name;

	if ( ! empty ( $user_info[ 'first_name' ] ) || ! empty( $user_info[ 'last_name' ] ) )
		$name = $user_info[ 'first_name' ] . ' ' . $user_info[ 'last_name' ];

	if ( $user->ID == 0 )
		$name = sprintf( __( '%s (Guest)', 'edd' ), $name );
		
	$name = sprintf( 
		'<a href="%s" title="%s">%s</a>', 
		self_admin_url( sprintf( 'edit.php?post_type=edd_payment&user_email=%s', $email ) ), 
		sprintf( __( 'View all payments by %s', 'edd' ), $name ),
		$name 
	);
?>
	<div class="purcase-personal-details">
		<p>
			<label for="edd_payment_buyer_name">
				<strong><?php _e( 'Buyer', 'edd' ); ?></strong>: <?php echo $name; ?><br />
				<input type="text" name="edd_payment_buyer_email" value="<?php echo $payment_meta['email']; ?>" class="regular-text" />
			</label>
		</p>

		<?php do_action('edd_payment_personal_details_list', $payment_meta, $user_info); ?>
	</div>

	<div class="status-wrap">
		<p>
			<label for="edd_payment_status">
				<strong><?php _e( 'Payment Status', 'edd' ); ?></strong>: <br />
				<select name="edd-payment-status" id="edd_payment_status">
					<?php foreach( $statuses as $status_id => $label ) : ?>
					<option value="<?php echo $status_id; ?>" <?php selected( $status, $status_id ); ?>><?php echo $label; ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label for="edd_send_email">
				<input type="checkbox" name="edd-payment-send-email" id="edd_send_email" value="yes"/>
				<span class="description"><?php _e('Send Purchase Receipt', 'edd'); ?></span>
			</label>
		</p>
	</div>

	<div class="payment-method">
		<p>
			<label for="edd_payment_gateway">
				<strong><?php _e( 'Payment Method', 'edd' ); ?></strong>: <br />
				<select class="edd-select" name="payment-mode" id="edd-gateway">
				<?php foreach( $gateways as $gateway_id => $_gateway ) : ?>
					<option value="<?php echo $gateway_id; ?>" <?php selected( $gateway_id, $gateway ); ?>><?php echo $_gateway[ 'checkout_label' ]; ?></option>
				<?php endforeach; ?>
				</select>
			</label>
		</p>
	</div>

	<?php if( isset( $user_info['discount']) && $user_info['discount'] != 'none') : ?>
	<div class="discount-wrap">
		<p>
			<label for="edd_payment_discount">
				<strong><?php _e( 'Discount Code', 'edd'); ?></strong>: <br />
				<input type="text" name="edd_payment_discount" value="<?php echo $user_info['discount']; ?>" class="regular-text" readonly="readonly" />
			</label>
		</p>
	</div>
	<?php endif; ?>

	<div class="total-wrap">
		<p>
			<label for="edd_payment_total">
				<strong><?php _e( 'Total', 'edd' ); ?></strong>: <br />
				<input type="text" name="edd_payment_discount" value="<?php echo edd_currency_filter( $payment_meta[ 'amount' ] ); ?>" class="regular-text" readonly="readonly" />
			</label>
		</p>
	</div>

	<div class="purchase-key-wrap">
		<p>
			<label for="edd_payment_gateway">
				<strong><?php _e( 'Purchase Key', 'edd' ); ?></strong>: <br />
				<input type="text" name="edd_payment_purchase_key" value="<?php echo $payment_meta['key']; ?>" class="regular-text"  readonly="readonly" />
			</label>
		</p>
	</div>
<?php
}

function edd_render_purchased_files_meta_box() {
	global $post;

	$payment_meta = get_post_meta( $post->ID, '_edd_payment_meta', true );
	$user_info    = maybe_unserialize( $payment_meta[ 'user_info' ] ); 
	
	/*$downloads    = isset( $payment_meta[ 'cart_details' ] ) ? maybe_unserialize( $payment_meta[ 'cart_details' ] ) : false;
	
	if ( empty( $downloads ) || ! $downloads ) {
		$downloads = maybe_unserialize( $payment_meta[ 'downloads' ] );
	}*/

	$downloads = maybe_unserialize( $payment_meta[ 'downloads' ] );
?>
		
	<p>
		<strong><?php _e( 'Current Downloads', 'edd' ); ?></strong>: 
	</p>

	<div id="postcustomstuff">
		<table id="newmeta" class="widefat">
			<thead>
				<tr>
					<th align="left"><?php _e( 'Download', 'edd' ); ?></th>
					<th align="left"><?php _e( 'Price', 'edd' ); ?></th>
					<th align="left"><?php _e( 'Options', 'edd' ); ?></th>
				</tr>
			<thead>
			<tbody>
				<?php if ( $downloads ) : foreach( $downloads as $key => $download ) : ?>
					<?php
						$id = $download[ 'id' ];
						$user_info = unserialize( $payment_meta[ 'user_info' ]);
						$price = edd_get_download_final_price( $id, $user_info );
					?>
					<tr>
						<td>
							<strong><a href="<?php echo self_admin_url( sprintf( 'post.php?post=%d&action=edit', $id ) ); ?>" target="_blank" class="row-title"><?php echo get_the_title( $id ); ?></a></strong>

							<div class="row-actions">
								<?php printf( 'ID: #%d', $id ); ?> | 
								<a href="<?php echo esc_url( admin_url( sprintf( 'post.php?post=%d&action=edit', $id ) ) ); ?>"><?php _e( 'Edit', 'edd' ); ?></a> | <span class="trash"><a href="#" class="submitdelete"><?php _e( 'Remove', 'edd' ); ?></a></span>
							</div>

							<input type="hidden" name="edd-purchased-downloads[]" value="<?php echo $id; ?>" />
						</td>
						<td>
							<?php echo edd_currency_filter( $price ); ?>
						</td>
						<td>
							<?php
								if ( isset( $download[ 'options' ]['price_id'] ) ) {
										echo edd_get_price_option_name( $id, $download[ 'options' ]['price_id'] );
								}
							?>
						</td>
					</tr>
				<?php endforeach; endif; ?>
			</tbody>
		</table>

		<p id="edd-add-download" class="submit">
			<?php
				$downloads = get_posts( array(
					'post_type' => 'download', 
					'posts_per_page' => -1
				) );

				if ( $downloads ) :
			?>
				<select name="edd-add-download">
					<option value="0"><?php _e( 'Select Download', 'edd' ); ?></option>
					<?php foreach ( $downloads as $download ) : ?>
					<option value="<?php echo $download->ID; ?>"><?php echo $download->post_title; ?></option>
					<?php 
						$prices = get_post_meta( $download->ID, 'edd_variable_prices', true ); 
						if ( $prices ) :  foreach ( $prices as $key => $price ) :
					?>

						<option value="<?php echo $download->ID; ?>.<?php echo $key; ?>">&nbsp; &mdash; <?php echo $price[ 'name' ]; ?></option>
					<?php endforeach; endif; ?>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>
			<?php submit_button( __( 'Add New Download', 'edd' ), 'button-secondary', 'add-download', false ); ?>
		</p>
	</div>
	<script>
	jQuery( 'input[name="add-download"]' ).click(function(e) {
		e.preventDefault();
		jQuery('#publish').click();
	});
	</script>
<?php
}