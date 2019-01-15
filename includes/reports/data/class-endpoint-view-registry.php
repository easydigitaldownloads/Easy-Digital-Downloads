<?php
/**
 * Reports API - Endpoint View Registry
 *
 * @package     EDD
 * @subpackage  Reports
 * @copyright   Copyright (c) 2018, Easy Digital Downloads, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.0
 */
namespace EDD\Reports\Data;

use EDD\Utils;
use EDD\Reports;

/**
 * Implements a singleton registry for registering endpoint views.
 *
 * @since 3.0
 *
 * @see \EDD\Reports\Registry
 * @see \EDD\Utils\Static_Registry
 *
 * @method array get_endpoint_view( string $view_id )
 */
class Endpoint_View_Registry extends Reports\Registry implements Utils\Static_Registry {

	/**
	 * Item error label.
	 *
	 * @since 3.0
	 * @var   string
	 */
	public static $item_error_label = 'endpoint view';

	/**
	 * The one true registry instance.
	 *
	 * @since 3.0
	 * @var   Endpoint_View_Registry
	 */
	private static $instance;

	/**
	 * Retrieves the one true Endpoint View registry instance.
	 *
	 * @since 3.0
	 *
	 * @return Endpoint_View_Registry Registry instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new Endpoint_View_Registry();
		}

		return self::$instance;
	}

	/**
	 * Handles magic method calls for endpoint view manipulation.
	 *
	 * @since 3.0
	 *
	 * @throws \EDD_Exception in get_item() if the item does not exist.
	 *
	 * @param string $name      Method name.
	 * @param array  $arguments Method arguments (if any)
	 * @return mixed Results of the method call (if any).
	 */
	public function __call( $name, $arguments ) {
		$view_id_or_sort = isset( $arguments[0] )
			? $arguments[0]
			: '';

		switch ( $name ) {
			case 'get_endpoint_view':
				return parent::get_item( $view_id_or_sort );
		}
	}

	/**
	 * Adds a new endpoint view to the master registry.
	 *
	 * @since 3.0
	 *
	 * @throws \EDD_Exception if the `$view_id` doesn't match a core view.
	 * @throws \EDD_Exception if attributes other than 'group_callback', 'handler', or 'display_callback'
	 *                        are defined.
	 * @throws \EDD_Exception if one or more attributes are not of a valid specification.
	 *
	 * @param string $view_id   Endpoint view ID.
	 * @param array  $attributes {
	 *     Endpoint view attributes. All arguments are required.
	 *
	 *     @type string $label     Report label.
	 *     @type int    $priority  Optional. Priority by which to register the report. Default 10.
	 *     @type array  $filters   Filters available to the report.
	 *     @type array  $endpoints Optional. Endpoints to associate with the report.
	 * }
	 * @return bool True if the report was successfully registered, otherwise false.
	 */
	public function add_endpoint_view( $view_id, $attributes ) {
		$error = false;

		$view_atts = $this->get_core_view( $view_id );

		if ( empty( $view_atts ) ) {
			throw new Utils\Exception( sprintf( 'The \'%1$s\' endpoint view is invalid.', $view_id ) );
		}

		if ( ! empty( $attributes['group_callback'] ) ) {
			$view_atts['group_callback'] = $attributes['group_callback'];
		}

		if ( ! empty( $attributes['handler'] ) ) {
			$view_atts['handler'] = $attributes['handler'];
		}

		if ( ! empty( $attributes['fields']['display_callback'] ) ) {
			$view_atts['fields']['display_callback'];
		}

		try {
			$this->validate_attributes( $view_atts, $view_id );
		} catch ( \EDD_Exception $exception ) {
			$error = true;

			throw $exception;
		}

		if ( true === $error ) {
			return false;

		} else {
			return parent::add_item( $view_id, $view_atts );
		}
	}

	/**
	 * Retrieves registered endpoint views.
	 *
	 * @since 3.0
	 *
	 * @param string $sort  Optional. How to sort the list of registered endpoint views before retrieval.
	 *                      Accepts 'ID' (alphabetized by item ID), or empty (none).
	 *                      Default empty.
	 * @param string $group Optional. The reports group to retrieve reports for. Default 'core'.
	 * @return array Endpoint view records.
	 */
	public function get_endpoint_views( $sort = '', $group = '' ) {
		$views = $this->get_items_sorted( $sort );

		foreach ( $views as $view_id => $atts ) {
			if ( $group !== $atts['group'] ) {
				unset( $views[ $view_id ] );
			}
		}

		return $views;
	}

	/**
	 * Prevents removing items from the registry.
	 *
	 * @since 3.0
	 *
	 * @param string $item_id Item ID.
	 */
	public function remove_item( $item_id ) {
		return;
	}

	/**
	 * Prevents removing items from the registry.
	 *
	 * @since 3.0
	 *
	 * @param mixed $index Item index to check.
	 */
	public function offsetUnset( $index ) {
		return;
	}

	/**
	 * Retrieves the core-defined views and their (mostly) immutable defaults.
	 *
	 * @since 3.0
	 *
	 * @param string $view_id View ID.
	 * @return array List of attributes for the given view ID if it exists, otherwise an empty array.
	 */
	public function get_core_view( $view_id ) {
		$views = $this->get_core_views();

		$attributes = array();

		if ( array_key_exists( $view_id, $views ) ) {
			$attributes = $views[ $view_id ];
		}

		return $attributes;
	}

	/**
	 * Retrieves the core-defined views and their (mostly) immutable defaults.
	 *
	 * @since 3.0
	 *
	 * @return array List of supported endpoint types and their attributes.
	 */
	public function get_core_views() {

	}
}