<?php
/**
 * Extension Error Log View Class
 *
 * @package     EDD/Admin/Reports
 * @copyright   Copyright (c) 2015, Pippin Williamson
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */


// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;


// Load WP_List_Table if not loaded
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}


/**
 * EDD_Extension_Error_Log_Table Class
 *
 * Renders the extension errors list table
 *
 * @access      private
 * @since       2.4
 */
class EDD_Extension_Error_Log_Table extends WP_List_Table {
	/**
	 * Number of items per page
	 *
	 * @var int
	 * @since 1.4
	 */
	public $per_page = 30;

	/**
	 * Get things started
	 *
	 * @since 1.4
	 * @see WP_List_Table::__construct()
	 */
	public function __construct() {
		global $status, $page;

		// Set parent defaults
		parent::__construct( array(
			'singular'  => edd_get_label_singular(),    // Singular name of the listed records
			'plural'    => edd_get_label_plural(),    	// Plural name of the listed records
			'ajax'      => false             			// Does this table support ajax?
		) );
	}


	/**
	 * This function renders most of the columns in the list table.
	 *
	 * @access public
	 * @since 2.4
	 *
	 * @param array $item Contains all the data of the log item
	 * @param string $column_name The name of the column
	 *
	 * @return string Column Name
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'error' :
				return get_the_title( $item['ID'] ) ? get_the_title( $item['ID'] ) : __( 'Payment Error', 'edd' );
			default:
				return $item[ $column_name ];
		}
	}


	/**
	 * Output Error Message Column
	 *
	 * @access public
	 * @since 2.4
	 * @param array $item Contains all the data of the log
	 * @return void
	 */
	public function column_message( $item ) {
	?>
		<a href="#TB_inline?width=640&amp;inlineId=log-message-<?php echo $item['ID']; ?>" class="thickbox" title="<?php _e( 'View Log Message', 'edd' ); ?> "><?php _e( 'View Log Message', 'edd' ); ?></a>
		<div id="log-message-<?php echo $item['ID']; ?>" style="display:none;">
			<?php

			$log_message = get_post_field( 'post_content', $item['ID'] );
			$serialized  = strpos( $log_message, '{"' );

			// Check to see if the log message contains serialized information
			if ( $serialized !== false ) {
				$length  = strlen( $log_message ) - $serialized;
				$intro   = substr( $log_message, 0, - $length );
				$data    = substr( $log_message, $serialized, strlen( $log_message ) - 1 );

				echo wpautop( $intro );
				echo wpautop( __( '<strong>Log data:</strong>', 'edd' ) );
				echo '<div style="word-wrap: break-word;">' . wpautop( $data ) . '</div>';
			} else {
				// No serialized data found
				echo wpautop( $log_message );
			}
			?>
		</div>
	<?php
	}


	/**
	 * Retrieve the table columns
	 *
	 * @access public
	 * @since 2.4
	 * @return array $columns Array of all the list table columns
	 */
	public function get_columns() {
		$columns = array(
			'ID'         => __( 'Log ID', 'edd' ),
			'payment_id' => __( 'Payment ID', 'edd' ),
			'error'      => __( 'Error', 'edd' ),
			'message'    => __( 'Error Message', 'edd' ),
			'date'       => __( 'Date', 'edd' )
		);

		return $columns;
	}


	/**
	 * Retrieve the current page number
	 *
	 * @access public
	 * @since 2.4
	 * @return int Current page number
	 */
	public function get_paged() {
		return isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
	}


	/**
	 * Outputs the log views
	 *
	 * @access public
	 * @since 2.4
	 * @return void
	 */
	public function bulk_actions( $which = '' ) {
		// These aren't really bulk actions but this outputs the markup in the right place
		edd_log_views();
	}


	/**
	 * Gets the log entries for the current view
	 *
	 * @access public
	 * @since 2.4
	 * @global object $edd_logs EDD Logs Object
	 * @return array $logs_data Array of all the Log entires
	 */
	public function get_logs() {
		global $edd_logs;

		// Prevent the queries from getting cached. Without this there are occasional memory issues for some installs
		wp_suspend_cache_addition( true );

		$logs_data = array();
		$paged     = $this->get_paged();
		$log_query = array(
			'log_type'    => 'extension_error',
			'paged'       => $paged
		);

		$logs = $edd_logs->get_connected_logs( $log_query );

		if ( $logs ) {
			foreach ( $logs as $log ) {

				$logs_data[] = array(
					'ID'         => $log->ID,
					'payment_id' => $log->post_parent,
					'error'      => 'error',
					'date'	     => $log->post_date
				);
			}
		}

		return $logs_data;
	}


	/**
	 * Setup the final data for the table
	 *
	 * @access public
	 * @since 2.4
	 * @global object $edd_logs EDD Logs Object
	 * @uses EDD_Extension_Error_Log_Table::get_columns()
	 * @uses WP_List_Table::get_sortable_columns()
	 * @uses EDD_Extension_Error_Log_Table::get_pagenum()
	 * @uses EDD_Extension_Error_Log_Table::get_logs()
	 * @uses EDD_Extension_Error_Log_Table::get_log_count()
	 * @return void
	 */
	public function prepare_items() {
		global $edd_logs;

		$columns               = $this->get_columns();
		$hidden                = array(); // No hidden columns
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->items           = $this->get_logs();
		$total_items           = $edd_logs->get_log_count( 0, 'extension_error' );

		$this->set_pagination_args( array(
				'total_items'  => $total_items,
				'per_page'     => $this->per_page,
				'total_pages'  => ceil( $total_items / $this->per_page )
			)
		);
	}
}
