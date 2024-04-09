<?php
/*
Plugin Name: CD CLICK on demand
Description: Integrates WooCommerce with CDClick API for on-demand printing.
Version: 1.0
Author: CD CLICK
*/

// Add settings page to WooCommerce menu
function cdclick_integration_menu() {
    add_submenu_page(
        'woocommerce',
        'CD CLICK on demand Settings',
        'CD CLICK on demand',
        'manage_options',
        'cdclick_integration_settings',
        'cdclick_integration_settings_page'
    );
}
add_action('admin_menu', 'cdclick_integration_menu');

// Render settings page
function cdclick_integration_settings_page() {	
    ?>
    <div class="wrap">
        <h2>CD CLICK on demand Settings</h2>
        <form method="post" action="options.php">
            <?php settings_fields('cdclick_integration_settings_group'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">API Key</th>
                    <td><input type="text" name="cdclick_api_key" value="<?php echo esc_attr(get_option('cdclick_api_key')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Product Tag</th>
                    <td><input type="text" name="cdclick_product_tag" value="<?php echo esc_attr(get_option('cdclick_product_tag')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Automatic Shipping</th>
                    <td><input type="checkbox" name="cdclick_auto_shipping" value="1" <?php checked(get_option('cdclick_auto_shipping'), 1); ?> /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Duplicate Check</th>
                    <td><input type="checkbox" name="cdclick_duplicate_check" value="1" <?php checked(get_option('cdclick_duplicate_check'), 1); ?> /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Register settings
function cdclick_register_settings() {
    register_setting('cdclick_integration_settings_group', 'cdclick_api_key');
    register_setting('cdclick_integration_settings_group', 'cdclick_product_tag');
    register_setting('cdclick_integration_settings_group', 'cdclick_auto_shipping');
    register_setting('cdclick_integration_settings_group', 'cdclick_duplicate_check');
}
add_action('admin_init', 'cdclick_register_settings');

// Send order data to CDClick API
function cdclick_send_order($order_id) {
    $api_key = get_option('cdclick_api_key');
    $auto_shipping = get_option('cdclick_auto_shipping');
    $duplicate_check = get_option('cdclick_duplicate_check');
	if ($duplicate_check==1)
	{
		$duplicate_check=true;
	}
	else
	{
		$duplicate_check=false;
	}
	if ($auto_shipping==1)
	{
		$auto_shipping=true;
	}
	else
	{
		$auto_shipping=false;
	}	
    $order = wc_get_order($order_id);

	if ($api_key && $order->has_shipping_address()) {
		$items = $order->get_items();
		$cdclick_product_tag = get_option('cdclick_product_tag');

		// Check if any item has the CDClick product tag
		$has_cdclick_tag = false;
		foreach ($items as $item) {
			$product_id = $item->get_variation_id(); // Get variation ID
			if (!$product_id) {
				$product_id = $item->get_product_id(); // If not a variation, get product ID
			}
			$product = wc_get_product($product_id); // Get product object
			$parent_product_id = $product->get_parent_id(); // Get parent product ID

			// If product has a parent product ID, use that to get tags
			if ($parent_product_id!=0) {
				$parent_product = wc_get_product($parent_product_id); // Get parent product object
				$product_tag_ids = $parent_product->get_tag_ids(); // Get parent product tag IDs
			} else {
				// If no parent product ID, use tags directly from the product
				$product_tag_ids = $product->get_tag_ids(); // Get product tag IDs
			}

			// Search for the CDClick tag
			foreach ($product_tag_ids as $tag_id) {
				$tag = get_term($tag_id, 'product_tag'); // Get tag object
				if ($tag && $tag->slug === $cdclick_product_tag) { // Check if tag is the CDClick tag
					$has_cdclick_tag = true;
					break 2; // Exit both foreach loops
				}
			}
		}
		
		var_dump($has_cdclick_tag);
		
		if ($has_cdclick_tag) 
		{
			$shipping_info = $order->get_address('shipping');
			$billing_info = $order->get_address('billing');
			
			// Adapt shipping info to CDClick API format
			$shipping = array(
				'first_name' => $shipping_info['first_name'],
				'last_name' => $shipping_info['last_name'],
				'address_street' => $shipping_info['address_1'] . '

 ' . $shipping_info['address_2'],
				'city' => $shipping_info['city'],
				'state_province_code' => $shipping_info['state'],
				'zip_code' => $shipping_info['postcode'],
				'country_code' => $shipping_info['country'],
				'phone_number' => $shipping_info['phone'],
				'email' => $billing_info['email']
			);
			
			$items = $order->get_items();

			$data = array(
				'custom_id' => 'Order - ' . $order_id,
				'check_multiple_custom_id' => $duplicate_check,
				'idle' => $auto_shipping,
				'shipping' => $shipping,
				'cart' => array()
			);

			
			foreach ($items as $item) {
				$has_cdclick_tag = false;
				$product_id = $item->get_variation_id(); // Get variation ID
				if (!$product_id) {
					$product_id = $item->get_product_id(); // If not a variation, get product ID
				}
				$product = wc_get_product($product_id); // Get product object
				$parent_product_id = $product->get_parent_id(); // Get parent product ID

				// If product has a parent product ID, use that to get tags
				if ($parent_product_id!=0) {
					$parent_product = wc_get_product($parent_product_id); // Get parent product object
					$product_tag_ids = $parent_product->get_tag_ids(); // Get parent product tag IDs
				} else {
					// If no parent product ID, use tags directly from the product
					$product_tag_ids = $product->get_tag_ids(); // Get product tag IDs
				}

				// Search for the CDClick tag
				foreach ($product_tag_ids as $tag_id) {
					$tag = get_term($tag_id, 'product_tag'); // Get tag object
					if ($tag && $tag->slug === $cdclick_product_tag) { // Check if tag is the CDClick tag
						$has_cdclick_tag = true;
						break; 
					}
				}
				
				if ($has_cdclick_tag) {
					$data['cart'][] = array(
						'item_id' => $product->get_sku(),
						'quantity' => $item->get_quantity()
					);
				}				
			}

			$order->add_order_note(json_encode($data));			
			
			$response = wp_remote_post('https://wall.cdclick-europe.com/api/orders', array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type' => 'application/json'
				),
				'body' => json_encode($data)
			));

			if (!is_wp_error($response)) {
				$response_data = json_decode(wp_remote_retrieve_body($response), true);
				if ($response_data) {
					if ($response_data['success']) {
						// Update order meta with CDClick order ID
						update_post_meta($order_id, 'cdclick_order_id', $response_data['order_id']);
						
						// Add success note to order
						$note = 'CDClick API response: Success. Order ID: ' . $response_data['order_id'];
						$order->add_order_note($note);
					} else {
						// Add error note to order
						$note = 'CDClick API response: Error. ' . $response_data['errorText'];
						$order->add_order_note($note);
					}
				}
			}
		
		}
    }
}
add_action('woocommerce_order_status_completed', 'cdclick_send_order');

// Fetch shipping details from CDClick API
function cdclick_fetch_shipping_details() {
    $orders = wc_get_orders(array(
        'meta_key' => 'cdclick_order_id',
        'post_status' => 'wc-completed',
        'numberposts' => -1
    ));

    foreach ($orders as $order) {
        $order_id = $order->get_id();
        $cdclick_order_id = get_post_meta($order_id, 'cdclick_order_id', true);
        $api_key = get_option('cdclick_api_key');

        if ($cdclick_order_id && $api_key) {
            $response = wp_remote_get('https://wall.cdclick-europe.com/api/orders/' . $cdclick_order_id, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key
                )
            ));

            if (!is_wp_error($response)) {
                $response_data = json_decode(wp_remote_retrieve_body($response), true);
				if ($response_data) {
					if ($response_data['success']) {
						// Update order meta with shipping details
						if (!empty($response_data['orders'])) {
							$shipping_details = $response_data['orders'][0];
							$ship_date = $shipping_details['shipDate'];
							$courier_name = $shipping_details['courier_name'];
							$courier_tracking = $shipping_details['courier_tracking'];

							update_post_meta($order_id, 'cdclick_ship_date', $ship_date);
							update_post_meta

($order_id, 'cdclick_courier_name', $courier_name);
							update_post_meta($order_id, 'cdclick_courier_tracking', $courier_tracking);

							// Add note to order for success
							$note = 'CDClick API response: Success. Shipping details retrieved. Ship date: ' . $ship_date . ', Courier: ' . $courier_name . ', Tracking: ' . $courier_tracking;
							$order->add_order_note($note);

							// Mark order as shipped							
							$order->update_status('wc-cdc-shipped', 'CDClick order marked as shipped.');
						} else {
							// Add note to order if no shipping details found
							$note = 'CDClick API response: Success, but no shipping details found.';
							$order->add_order_note($note);
						}
					} else {
						// Add error note to order
						$note = 'CDClick API response: Error. ' . $response_data['errorText'];
						$order->add_order_note($note);
					}
				}

            }
        }
    }
}
// Schedule cron job to fetch shipping details
function cdclick_schedule_cron_job() {
    if (!wp_next_scheduled('cdclick_fetch_shipping_details')) {
        wp_schedule_event(time(), 'daily', 'cdclick_fetch_shipping_details');
    }
}
add_action('wp', 'cdclick_schedule_cron_job');
add_action('cdclick_fetch_shipping_details', 'cdclick_fetch_shipping_details');


// Add a new order state
add_action('init', 'register_custom_order_status');
function register_custom_order_status() {
    register_post_status('wc-cdc-shipped', array(
        'label' => _x('Shipped', 'WooCommerce Order status', 'woocommerce'),
        'public' => true,
        'exclude_from_search' => false,
        'show_in_admin_all_list' => true,
        'show_in_admin_status_list' => true,
        'label_count' => _n_noop('CDC Shipped <span class="count">(%s)</span>', 'CDC Shipped <span class="count">(%s)</span>', 'woocommerce')
    ));
}

// Add the new order status into the select
add_filter('wc_order_statuses', 'add_custom_order_status');
function add_custom_order_status($order_statuses) {
    $order_statuses['wc-cdc-shipped'] = _x('Shipped', 'WooCommerce Order status', 'woocommerce');
    return $order_statuses;
}

// Display CDClick order details in WooCommerce order admin
add_action( 'woocommerce_admin_order_data_after_shipping_address', 'cdclick_display_order_data', 10, 1 );
function cdclick_display_order_data( $order ) {
    echo '<div class="order_data_column">';
    echo '<h4>' . __( 'CDClick Order Details', 'woocommerce' ) . '</h4>';

    // CDClick Order ID
    $cdclick_order_id = get_post_meta( $order->get_id(), 'cdclick_order_id', true );
    if ( ! empty( $cdclick_order_id ) ) {
        echo '<p><strong>' . __( 'CDClick Order ID', 'woocommerce' ) . ':</strong> ' . esc_html( $cdclick_order_id ) . '</p>';
    }

    // CDClick Ship Date
    $cdclick_ship_date = get_post_meta( $order->get_id(), 'cdclick_ship_date', true );
    if ( ! empty( $cdclick_ship_date ) ) {
        echo '<p><strong>' . __( 'CDClick Ship Date', 'woocommerce' ) . ':</strong> ' . esc_html( $cdclick_ship_date ) . '</p>';
    }

    // CDClick Courier Name
    $cdclick_courier_name = get_post_meta( $order->get_id(), 'cdclick_courier_name', true );
    if ( ! empty( $cdclick_courier_name ) ) {
        echo '<p><strong>' . __( 'CDClick Courier Name', 'woocommerce' ) . ':</strong> ' . esc_html( $cdclick_courier_name ) . '</p>';
    }

    // CDClick Courier Tracking
    $cdclick_courier_tracking = get_post_meta( $order->get_id(), 'cdclick_courier_tracking', true );
    if ( ! empty( $cdclick_courier_tracking ) ) {
        echo '<p><strong>' . __( 'CDClick Courier Tracking', 'woocommerce' ) . ':</strong> ' . esc_html( $cdclick_courier_tracking ) . '</p>';
    }

    echo '</div>';
}

?>
