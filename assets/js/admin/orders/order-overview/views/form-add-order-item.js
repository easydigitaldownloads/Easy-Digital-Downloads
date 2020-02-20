/* global wp */

/**
 * Internal dependencies
 */
import {
	Base,
	Dialog,
} from './';

import {
	OrderItem,
} from './../models';

import {
	NumberFormat,
} from '@easy-digital-downloads/currency';

const number = new NumberFormat();

/**
 * "Add Item" view
 *
 * @since 3.0
 *
 * @class FormAddOrderItem
 * @augments Dialog
 */
export const FormAddOrderItem = Dialog.extend( /** Lends FormAddItem.prototype */ {
	/**
	 * @since 3.0
	 */
	el: '#edd-admin-order-add-item-dialog',

	/**
	 * @since 3.0
	 */
	template: wp.template( 'edd-admin-order-form-add-order-item' ),

	/**
	 * "Add Item" view.
	 *
	 * @since 3.0
	 *
	 * @constructs FormAddOrderItem
	 * @augments wp.Backbone.View
	 */
	initialize() {
		Dialog.prototype.initialize.apply( this, arguments );

		// Delegate additional events.
		this.addEvents( {
			'submit form': 'onAdd',

			'change #download': 'onChangeDownload',
			'change #quantity': 'onChangeQuantity',
			'change #auto-calculate': 'onAutoCalculateToggle',

			'keyup #amount': 'onChangeAmount',
			'keyup #tax': 'onChangeTax',
			'keyup #subtotal': 'onChangeSubtotal',
		} );

		// Assign Collection from State.
		this.collection = this.options.state.get( 'items' );

		// Create a fresh `OrderItem` to be added.
		this.model = new OrderItem( {
			amountManual: 0,
			taxManual: 0,
			subtotalManual: 0,
		} );

		// Listen for events.
		this.listenTo( this.model, 'change', this.render );
		this.listenTo( this.options.state, 'change:hasTax', this.render );
		this.listenTo( this.collection, 'add', this.closeDialog );
	},

	/**
	 * Prepares data to be used in `render` method.
	 *
	 * @since 3.0
	 *
	 * @see wp.Backbone.View
	 * @link https://github.com/WordPress/WordPress/blob/master/wp-includes/js/wp-backbone.js
	 *
	 * @return {Object} The data for this view.
	 */
	prepare() {
		const {
			model,
			options,
		} = this;

		const {
			state,
		} = options;

		const quantity = model.get( 'quantity' );

		let amount = model.get( 'amount' ) * quantity;
		let tax = model.get( 'tax' ) * quantity;
		let subtotal = model.get( 'subtotal' ) * quantity;

		if ( true === model.get( 'isAdjustingManually' ) ) {
			amount = model.get( 'amountManual' );
			tax = model.get( 'taxManual' );
			subtotal = model.get( 'subtotalManual' );
		}

		return {
			...Base.prototype.prepare.apply( this ),

			amountManual: number.format( amount ),
			taxManual: number.format( tax ),
			subtotalManual: number.format( subtotal ),

			_isDuplicate: undefined !== state.get( 'items' ).findWhere( {
				id: model.get( 'id' ),
			} ),
		};
	},

	/**
	 * Updates the OrderItem when the Download changes.
	 *
	 * @since 3.0
	 *
	 * @param {Objec} e Change event for Download selector.
	 */
	onChangeDownload( e ) {
		const {
			target: {
				options,
				selectedIndex,
			},
		} = e;

		const {
			state,
		} = this.options;

		// Find the selected Download.
		const selected = options[ selectedIndex ];

		// Set the `eddUid` -- a combination of ID and Price ID,
		// generated by the server when searching for a Download.
		const eddUid = selected.value;

		// Set ID and Price ID.
		let id = eddUid;
		let priceId = 0;

		const parts = id.split( '_' );

		id = parseInt( parts[0] );

		if ( parts[1] ) {
			priceId = parseInt( parts[1] );
		}

		// Update basic attributes.
		this.model.set( {
			eddUid,
			id,
			priceId,
			name: selected.text,
		} );

		// Update amount attributes.
		this.model.getAmounts( {
			country: state.getTaxCountry(),
			region: state.getTaxRegion(),
			items: state.get( 'items' ),
			adjustments: state.get( 'adjustments' ),
		} )
			.then( ( response ) => {
				const {
					amount,
					discount,
					tax,
					subtotal,
					total,
				} = response;

				this.model.set( {
					amount,
					discount,
					tax,
					subtotal,
					total,

					amountManual: number.format( amount ),
					taxManual: number.format( tax ),
					subtotalManual: number.format( subtotal ),
				} );
			} );
	},

	/**
	 * Updates the `OrderItem`'s when the Quantity changes.
	 *
	 * @since 3.0
	 * @todo Validate.
	 *
	 * @param {Object} e Change event.
	 */
	onChangeQuantity( e ) {
		this.model.set( 'quantity', parseInt( e.target.value ) );
	},

	/**
	 * Updates the `OrderItem`'s when the manually managed Amount changes.
	 *
	 * @since 3.0
	 *
	 * @param {Object} e Change event.
	 */
	onChangeAmount( e ) {
		this.model.set( 'amountManual', e.target.value );
	},

	/**
	 * Updates the `OrderItem`'s when the manually managed Tax changes.
	 *
	 * @since 3.0
	 *
	 * @param {Object} e Change event.
	 */
	onChangeTax( e ) {
		this.model.set( 'taxManual', e.target.value );
	},

	/**
	 * Updates the `OrderItem`'s when the manually managed Subtotal changes.
	 *
	 * @since 3.0
	 *
	 * @param {Object} e Change event.
	 */
	onChangeSubtotal( e ) {
		this.model.set( 'subtotalManual', e.target.value );
	},

	/**
	 * Toggles manual amount adjustments.
	 *
	 * @since 3.0
	 *
	 * @param {Object} e Change event.
	 */
	onAutoCalculateToggle( e ) {
		e.preventDefault();

		this.model.set( {
			isAdjustingManually: ! e.target.checked,
		} );
	},

	/**
	 * Adds an OrderItem to the OrderItems collection.
	 *
	 * @since 3.0
	 *
	 * @param {Object} e Submit event.
	 */
	onAdd( e ) {
		e.preventDefault();

		const {
			model,
			collection,
			options,
		} = this;

		const {
			state,
		} = options;

		// Use manual amounts if adjusting manually.
		if ( true === model.get( 'isAdjustingManually' ) ) {
			model.set( {
				amount: number.unformat( model.get( 'amountManual' ) ),
				tax: number.unformat( model.get( 'taxManual' ) ),
				subtotal: number.unformat( model.get( 'subtotalManual' ) ),
			} );

			// Duplicate base amounts by the quantity set.
		} else {
			const quantity = model.get( 'quantity' );

			model.set( {
				tax: model.get( 'tax' ) * quantity,
				subtotal: model.get( 'subtotal' ) * quantity,
			} );
		}

		// Update discount amount based on new amounts.
		model.getAmounts( {
			country: state.getTaxCountry(),
			region: state.getTaxRegion(),
			items: state.get( 'items' ),
			adjustments: state.get( 'adjustments' ),
		} )
			.then( ( response ) => {
				// Update Discount.
				model.set( 'discount', response.discount );

				// Add OrderItem to OrderItems.
				collection.add( model );
			} );
	},
} );