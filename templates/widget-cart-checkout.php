
<li class="cart_item edd_subtotal"><?php echo _( 'Subtotal:', 'edd' ). " <span class='subtotal'>".edd_currency_filter( edd_get_cart_amount( false ) ); ?></span></li>
<li class="cart_item edd_checkout"><a href="<?php echo edd_get_checkout_uri(); ?>"><?php _e( 'Checkout', 'edd' ); ?></a></li>