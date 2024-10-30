<?php
/*
 * Plugin Name:       PortOne Payment
 * Plugin URI:        https://www.docs.portone.cloud/plugins_and_sdks/woocommerce-plugin.html
 * Description:       Single Payment
 * Version:           3.0.0
 * Requires at least: 5.6
 * Author:            PortOne
 * Author URI:        https://portone.io/global/en
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */


/**
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter('woocommerce_payment_gateways', 'chaiport_add_gateway_class');
function chaiport_add_gateway_class($gateways)
{
    $gateways[] = 'WC_Chaiport_Gateway'; // class name is here
    return $gateways;
}

/**
 * This filter adds settings links to our plugin in the plugins pages
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'chaiport_settings_link');
function chaiport_settings_link(array $links)
{
    $url = get_admin_url() . "admin.php?page=wc-settings&tab=checkout&section=chaiport";
    $settings_link = '<a href="' . $url . '">' . __('Settings', 'textdomain') . '</a>';
    $links[] = $settings_link;
    return $links;
}

/**
 * This filter disables pay button for failed and pending orders
 */
add_filter('woocommerce_my_account_my_orders_actions', 'chaiport_filter_woocommerce_my_account_my_orders_actions', 10, 2);
function chaiport_filter_woocommerce_my_account_my_orders_actions($actions, $order)
{
    // Get status
    $order_status = $order->get_status();
    // Status = failed
    if ($order_status == 'failed' || $order_status == "pending") {
        // Unset
        unset($actions['pay']);
    }

    return $actions;
}


/**
 * This filter adds a custom bulk edit option to fetch order status
 */
//add_filter('bulk_actions-edit-shop_order', 'chaiport_fetch_status_bulk_actions', 10, 1);
//function chaiport_fetch_status_bulk_actions($actions)
//{
//	$actions['fetch_status'] = __('Fetch transaction status from PortOne', 'woocommerce');
//	return $actions;
//}


/**
 * Generate Signature for the request
 * @return String signature calculated from inputs
 */
function ChaiportGenerateFetchStatusSignature($id, $secretKey)
{
    $data = array(
        'id' => $id,
    );

    ksort($data);
    $data = http_build_query($data);
    $message = $data;

    return base64_encode(hash_hmac('sha256', $message, $secretKey, true));
}


/**
 * This filter handles the custom option to fetch order status
 */
add_filter('handle_bulk_actions-edit-shop_order', 'chaiport_handle_bulk_action_fetch_status', 10, 3);
function chaiport_handle_bulk_action_fetch_status($redirect_to, $action, $post_ids)
{
    if ($action !== 'fetch_status')
        return $redirect_to; // Exit

    $processed_ids = array();

    foreach ($post_ids as $post_id) {
        $order = wc_get_order($post_id);
        $orderId = $order->get_id();

        $options = get_option('woocommerce_chaiport_settings');
        $chaiApiUrl = "https://api.portone.cloud/api/getTransactionStatus/";
        $publicKey = $options["publishable_key"];
        $privateKey = $options["private_key"];
        $chaiPortId = $publicKey . "-" . $orderId;
        $orderStatusType = $options["payment_process_action"];
        $getOrderStatusUrl = $chaiApiUrl . $chaiPortId;

        // Create token header as a JSON string
        $jwt_header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $encodeData = array(
            "iss" => "CHAIPAY",
            "sub" => $publicKey,
            "iat" => time(),
            "exp" => time() + 500
        );
        $payload = json_encode($encodeData);

        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($jwt_header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $privateKey, true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        // Create JWT
        $token = $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;

        $header = array(
            'headers' => array(
                'X-Chaipay-Client-Key' => $publicKey,
                'Authorization' => "Bearer " . $token
            )
        );

        $response = wp_remote_get($getOrderStatusUrl, $header);
        if (wp_remote_retrieve_response_code($response) == 200) {
            $result = wp_remote_retrieve_body($response);
            $data = json_decode($result, true);
            $status = $data['status'];
            $orderRef = $data["order_ref"];
            $statusCode = $data["status_code"];
            $statusReason = $data["status_reason"];
            $note = "--- Manual Update ---\n";

            if ($order->get_status() != "completed") {
                if ($status == "Success") {
                    if ($orderStatusType == "Processing") {
                        $order->update_status('wc-processing');
                    } else {
                        $order->update_status('wc-completed');
                    }
                    $note .= "Transaction Status: " . $status . "\n";
                    $note .= "Order reference: " . $orderRef . "\n";
                    $order->add_order_note($note);
                } else if ($status == "Failed") {
                    $order->update_status('wc-failed');

                    $note .= "Transaction Status: " . $status . "\n";
                    $note .= "Failure Code: " . $statusCode . "\n";
                    $note .= "Failure Reason: " . $statusReason . "\n";

                    $order->add_order_note($note);
                } else {
                    $note .= "Transaction Status: Processing";
                    $order->update_status('wc-on-hold');
                    $order->add_order_note($note);
                }
            } else {
                if ($status == "Success") {
                    $note .= "Transaction Status: " . $status . "\n";
                    $note .= "Order reference: " . $orderRef . "\n";
                    $order->add_order_note($note);
                } else if ($status == "Failed") {
                    $note .= "Transaction Status: " . $status . "\n";
                    $note .= "Failure Code: " . $statusCode . "\n";
                    $note .= "Failure Reason: " . $statusReason . "\n";

                    $order->add_order_note($note);
                } else {
                    $note .= "Transaction Status: Pending";
                    $order->add_order_note($note);
                }
            }
        } else {
            $note = "--- Manual Update ---\n";
            $note .= "Transaction for the order was not found on Chai Server";
            $order->add_order_note($note);
        }
        $processed_ids[] = $post_id;
    }
    return $redirect_to = add_query_arg(array(
        'processed_count' => count($processed_ids),
    ), $redirect_to);
}


/**
 * Method to verify signature of response
 * @return Bool Checks if given signature is valid for the given type
 */
function ChaiportVerifySignature($responseObj, $secretKey, $type)
{
    $signature_hash = "";
    if ($type == "redirect") {
        $queryParams = $responseObj->get_query_params();
        $statusTypes = array(
            "Success", "Failed"
        );
        if (in_array($queryParams['status'], $statusTypes)) {
            $statusData = $queryParams['status'];
        } else {
            $statusData = "Success";
            $pairs = explode('&', $_SERVER[REQUEST_URI]);
            foreach ($pairs as $value) {
                if (substr($value, 0, 6) == "status=") {
                    $val = explode($value, '=')[1];
                    if (in_array($val, $statusTypes)){
                        $statusData = $val;
                    }
                }
            }
        }
        $signature_hash = $queryParams['signature_hash'];
        $data = array(
            'order_ref' => $queryParams['order_ref'],
            'channel_order_ref' => $queryParams['channel_order_ref'],
            'merchant_order_ref' => $queryParams['merchant_order_ref'],
            'status' => $statusData
        );
    } else if ($type == "webhook") {
        $signature_hash = $responseObj['signature_hash'];
        $data = array(
            'currency' => $responseObj['currency'],
            'amount' => $responseObj['amount'],
            'order_ref' => $responseObj['order_ref'],
            'merchant_order_ref' => $responseObj['merchant_order_ref'],
            'channel_order_ref' => $responseObj['channel_order_ref'],
            'country_code' => $responseObj['country_code'],
            'status' => $responseObj['status'],
            'channel_key' => $responseObj['channel_key'],
            'method_name' => $responseObj['method_name']
        );
    }
    ksort($data);
    $message = http_build_query($data);
    $hash_value = base64_encode(hash_hmac('sha256', $message, $secretKey, true));

    if ($hash_value !== $signature_hash) {
        echo "Hash verification failed, not from valid source";
        return false;
    } else {
        echo "Hash verification succeeded";
        return true;
    }
}


/**
 * Action to add new custom REST Endpoints to handle the redirect after payment and also webhooks
 */
add_action('rest_api_init', function () {
    register_rest_route('portone/', 'redirect', array(
        'methods' => WP_REST_Server::READABLE,
        'permission_callback' => '__return_true',
        'callback' => 'chaiport_order_manage_redirect',
        'args' => array(
            'order_ref' => array(
                'required' => true,
            ),
            'channel_order_ref' => array(
                'required' => true,
            ),
            'merchant_order_ref' => array(
                'required' => true,
            ),
            'status' => array(
                'required' => true,
            ),
            'signature_hash' => array(
                'required' => true,
            ),
            'link_order_ref' => array(
                'required' => true,
            ),
            'status_code' => array(
                'required' => true,
            ),
            'status_reason' => array(
                'required' => true,
            )
        )
    ));
    register_rest_route('chaiport/v1', 'webhook', array(
        'methods' => WP_REST_Server::CREATABLE,
        'permission_callback' => '__return_true',
        'callback' => 'chaiport_order_manage_webhook'
    ));
    register_rest_route('portone/', 'webhook', array(
        'methods' => WP_REST_Server::CREATABLE,
        'permission_callback' => '__return_true',
        'callback' => 'chaiport_order_manage_webhook'
    ));
});


/**
 * Redirects the user after updating the order from params
 */
function chaiport_order_manage_redirect($request)
{
    $data = $request;

    // extracting woocommerce order-id
    $linkOrderRef = $data["link_order_ref"];
    $array = explode("-", $linkOrderRef);
    $orderId = end($array);

    // checking if order exists
    $order = wc_get_order($orderId);

    // fetching the plugin setting to extract keys & other settings
    $options = get_option('woocommerce_chaiport_settings');
    $privateKey = $options["private_key"];
    $orderStatusType = $options["payment_process_action"];

    // validating signature
    $isSignatureValid = ChaiportVerifySignature($data, $privateKey, "redirect");

    // updating order
    if (($order != false) && ($isSignatureValid == true)) {
        $statusTypes = array(
            "Success", "Failed"
        );
        if (in_array($data['status'], $statusTypes)) {
            $status = $data['status'];
        } else {
            $status = "Success";
            $pairs = explode('&', $_SERVER[REQUEST_URI]);
            foreach ($pairs as $value) {
                if (substr($value, 0, 6) == "status=") {
                    $val = explode($value, '=')[1];
                    if (in_array($val, $statusTypes)){
                        $status = $val;
                    }
                }
            }
        }
        $orderRef = $data["order_ref"];
        $note = "--- Redirect Update ---\n";

        if ($status == "Success") {
            if ($orderStatusType == "Processing") {
                $order->update_status('wc-processing');
            } else {
                $order->update_status('wc-completed');
            }
            $note .= "Transaction Status: " . $status . "\n";
            $note .= "Order reference: " . $orderRef;
            $order->add_order_note($note);
        } else if ($status == "Failed") {
            $statusCode = $data["status_code"];
            $statusReason = $data["status_reason"];

            $order->update_status('wc-failed');

            $note .= "Transaction Status: " . $status . "\n";
            $note .= "Failure Code: " . $statusCode . "\n";
            $note .= "Failure Reason: " . $statusReason;

            $order->add_order_note($note);
        } else {
            $note .= "Transaction Status: Pending";
            $order->update_status('wc-on-hold');
            $order->add_order_note($note);
        }
    } else {
        if ($order == false) {
            echo '<script>console.log("Error fetching order from order Id")</script>';
        } else {
            echo '<script>console.log("Error in validating the signature")</script>';
        }
    }

    // redirecting to order details page
    // $redirectUrl = get_bloginfo('url') . "/my-account/view-order/" . $orderId . "/";
    $redirectUrl = $order->get_view_order_url();
    if (wp_redirect($redirectUrl)) {
        exit;
    }
}


/**
 * Updates order status from the incoming webhook from Chai Servers
 */
function chaiport_order_manage_webhook($request)
{
    $data = $request->get_params();

    // extracting woocommerce order-id
    $linkOrderRef = $data["link_order_ref"];
    $array = explode("-", $linkOrderRef);
    $orderId = end($array);

    // checking if order exists
    $order = wc_get_order($orderId);

    // fetching the plugin setting to extract keys & other settings
    $options = get_option('woocommerce_chaiport_settings');
    $publicKey = $options["publishable_key"];
    $privateKey = $options["private_key"];
    $orderStatusType = $options["payment_process_action"];

    // validating signature
    $isSignatureValid = ChaiportVerifySignature($data, $privateKey, "webhook");

    // updating order
    if (($order != false) && ($isSignatureValid == true)) {
        $status = $data["status"];
        $orderRef = $data["order_ref"];
        $note = "--- Webhook Update ---\n";

        if ($status == "Success") {
            if ($orderStatusType == "Processing") {
                $order->update_status('wc-processing');
            } else {
                $order->update_status('wc-completed');
            }
            $note .= "Transaction Status: " . $status . "\n";
            $note .= "Order reference: " . $orderRef;
            $order->add_order_note($note);
        } else if ($status == "Failed") {
            $statusCode = $data["status_code"];
            $statusReason = $data["status_reason"];
            $statusChannelReason = $data["status_channel_reason"];

            $status = $order->update_status('wc-failed');

            $note .= "Transaction Status: " . $status . "\n";
            $note .= "Failure Code: " . $statusCode . "\n";
            $note .= "Failure Reason: " . $statusReason . "\n";
            $note .= "Failure Channel Reason: " . $statusChannelReason;

            $order->add_order_note($note);
        } else if ($status == "Expired") {
            $statusCode = $data["status_code"];
            $statusReason = $data["status_reason"];
            $statusChannelReason = $data["status_channel_reason"];

            $status = $order->update_status('wc-failed');

            $note .= "Transaction Status: " . $status . "\n";
            $note .= "Failure Code: " . $statusCode . "\n";
            $note .= "Failure Reason: " . $statusReason . "\n";
            $note .= "Failure Channel Reason: " . $statusChannelReason;

            $order->add_order_note($note);
        } else {
            $note .= "Transaction Status: Pending";
            $order->update_status('wc-on-hold');
            $order->add_order_note($note);
        }
    } else {
        if ($order == false) {
            echo '<script>console.log("Error fetching order from order Id")</script>';
        } else {
            echo '<script>console.log("Error in validating the signature")</script>';
        }
    }
}


/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action('plugins_loaded', 'chaiport_init_gateway_class');
function chaiport_init_gateway_class()
{

    add_action( 'before_woocommerce_init', 'portone_cart_checkout_blocks_compatibility' );
    add_action( 'woocommerce_blocks_loaded', 'portone_gateway_block_support' );

    class WC_Chaiport_Gateway extends WC_Payment_Gateway
    {

        const SESSION_KEY = 'wc_order_id';

        /**
         * Class constructor
         */
        public function __construct()
        {

            $plugin_location = plugin_dir_url(__FILE__);
            $imageUrl = $plugin_location . "images/woocommerce_icon.png";

            $websiteUrl = get_bloginfo('url');
            $webhookUrl = $websiteUrl . "/wp-json/portone/webhook";

            $this->id = 'chaiport'; //payment gateway plugin ID
            $this->icon = $imageUrl; // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = true; // in case you need a custom credit card form
            $this->method_title = 'PortOne Plugin';
            $this->method_description = 'Description of PortOne payment platform'; // will be displayed on the options page

            // gateways can support subscriptions, refunds, saved payment methods,
            // we begin with simple payments
            $this->supports = array(
                'products'
            );

            // Method with all the options fields
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            $this->testmode = 'yes' === $this->get_option('testmode');
            $this->private_key = $this->get_option('private_key');
            $this->publishable_key = $this->get_option('publishable_key');
            $this->webhook_url = $webhookUrl;
            $this->payment_process_action = $this->get_option('payment_process_action');

            // This action hook saves the settings
            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            } else {
                add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
            }

            // receipt page action hook
            add_action('woocommerce_receipt_' . $this->id, array($this, 'chaiport_receipt_page'));

            // We need custom JavaScript to obtain a token
            add_action('wp_enqueue_scripts', array($this, 'chaiport_payment_scripts'));

            // The results notice from bulk action on orders
            add_action('admin_notices', array($this, 'chaiport_bulk_action_fetch_status_admin_notice'));

        }

        /**
         * Adds notice for the custom bulk option
         */
        function chaiport_bulk_action_fetch_status_admin_notice()
        {
            if (empty($_REQUEST['fetch_status'])) return; // Exit
            $count = intval($_REQUEST['processed_count']);
            printf('<div id="message" class="updated fade"><p>' .
                _n('Processed %s Order for update.',
                    'Processed %s Orders for update.',
                    $count,
                    'fetch_status'
                ) . '</p></div>', $count);
        }


        /**
         * Plugin options fields
         */
        public function init_form_fields()
        {

            $websiteUrl = get_bloginfo('url');
            $webhookUrl = $websiteUrl . "/wp-json/portone/webhook";

            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Enable/Disable',
                    'label' => 'Enable PortOne Plugin',
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => 'Title',
                    'type' => 'text',
                    'description' => 'This is the title which the user sees during checkout.',
                    'default' => 'Cards/Wallets/Bank-Transfer',
                    'desc_tip' => false
                ),
                'description' => array(
                    'title' => 'Description',
                    'type' => 'textarea',
                    'description' => 'This is the description which the user sees during checkout.',
                    'default' => 'Pay with Cards/Wallets/Bank-Transfer via PortOne'
                ),
                'testmode' => array(
                    'title' => 'Sandbox mode',
                    'label' => 'Enable Sandbox Mode',
                    'type' => 'checkbox',
                    'description' => 'Initiate transaction in sandbox mode.',
                    'default' => 'yes',
                    'desc_tip' => true
                ),
                'publishable_key' => array(
                    'title' => 'PortOne Key',
                    'type' => 'text',
                    'id' => 'publishable_key'
                ),
                'private_key' => array(
                    'title' => 'PortOne Secure Secret Key',
                    'type' => 'password',
                    'id' => 'private_key'
                ),
                'webhook_url' => array(
                    'title' => 'Webhook URL',
                    'type' => 'textarea',
                    'id' => 'webhook_url',
                    'description' => 'This is the webhook URL that needs to be added in the PortOne Merchant Portal',
                    'custom_attributes' => array('readonly' => 'readonly'),
                    'default' => $webhookUrl
                ),
                'show_shipping_details' => array(
                    'title' => 'Shipping Details',
                    'label' => 'Show shipping details',
                    'type' => 'checkbox',
                    'description' => 'Toggle to display shipping details on the checkout page',
                    'default' => 'no',
                    'desc_tip' => true,
                    'id' => 'show_shipping_details'
                ),
                'show_back_button' => array(
                    'title' => 'Back Button',
                    'label' => 'Show back button',
                    'type' => 'checkbox',
                    'description' => 'Toggle to enable/disable back button on the checkout page',
                    'default' => 'no',
                    'desc_tip' => true,
                    'id' => 'show_back_button'
                ),
                'default_guest_checkout' => array(
                    'title' => 'Guest Checkout',
                    'label' => 'Enable guest mode in payment checkout',
                    'type' => 'checkbox',
                    'description' => 'Toggle to enable/disable guest mode while making an payment from checkout page',
                    'default' => 'yes',
                    'desc_tip' => true,
                    'id' => 'default_guest_checkout'
                ),
                'payment_process_action' => array(
                    'title' => 'Payment processing action',
                    'type' => 'select',
                    'description' => 'This is the status that the order will be moved to when payment is processed successfully',
                    'default' => 'Processing',
                    'options' => array(
                        'Processing' => 'Processing',
                        'Completed'   => 'Completed'
                    )
                ),
            );
        }


        /*
		 * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
		 */
        public function chaiport_payment_scripts()
        {
            // let's suppose it is our payment processor JavaScript that allows to obtain a token
            // wp_enqueue_script('axios', 'https://cdn.jsdelivr.net/npm/axios@0.18.0/dist/axios.min.js');
            $plugin_location = plugin_dir_url(__FILE__);
            wp_enqueue_script('axios', $plugin_location . '/js/axios.min.js', array('jquery'));
            wp_enqueue_script('chaiport', 'https://static.portone.cloud/portone.js');
        }


        /**
         * Receipt Page
         * @param String $orderId WC Order Id
         * @return Void html data for payment
         **/
        function chaiport_receipt_page($orderId)
        {
            $order = wc_get_order($orderId);

            //$html = __('Thank you for your order, please click the button below to pay.', $this->id);

            $html = $this->generateOrderForm($order);

            echo esc_html($html);
        }


        /**
         * @param WC_Order $order
         * @return String currency code
         */
        public function getOrderCurrency($order)
        {
            if (version_compare(WOOCOMMERCE_VERSION, '2.7.0', '>=')) {
                return $order->get_currency();
            }

            return $order->get_order_currency();
        }


        /**
         * Generate order form
         * @param WC_Order $order WC Order
         */
        function generateOrderForm(WC_Order $order)
        {

            $publicKey = $this->get_option('publishable_key');
            $secretKey = $this->get_option('private_key');
            $environment = $this->testmode ? "sandbox" : "live";

            $merchantOrder = $publicKey . "-" . $order->get_id();
            $pl = $this->getPL($merchantOrder, $publicKey, $secretKey);
            if (strlen($pl) > 0) {
                $merchantOrder = $this->getRandomStringRandomInt( 4 ) . "_" . $merchantOrder;
            }

            $order->add_order_note("PortOne OrderId: $merchantOrder");

            $websiteUrl = get_bloginfo('url');
            $red = $websiteUrl . "/wp-json/portone/redirect";

            $amount = $order->get_total();
            $currencyCode = $this->getOrderCurrency($order);

            $signatureReqObj = [
                'amount' => $amount,
                'currency' => $currencyCode,
                'failure_url' => "$red",
                'merchant_order_id' => $merchantOrder,
                'client_key' => $publicKey,
                'success_url' => "$red"
            ];

            $signature = $this->ChaiportGenerateSignature($signatureReqObj, $secretKey);

            $itemsArray = array();
            // Get and Loop Over Order Items
            $discount_total = 0;
            $item_total = 0;
            foreach ($order->get_items() as $item_id => $item) {
                $order_item_id = $item->get_id();
                $item = new WC_Order_Item_Product($order_item_id);
                $product = $item->get_product();
                $image_id = $product->get_image_id();
                $image_url = wp_get_attachment_image_url($image_id, 'full');
                $temp = array(
                    "id" => "$order_item_id",
                    "price" => (float)$product->get_regular_price(),
                    "name" => $item->get_name(),
                    "quantity" => $item->get_quantity(),
                    "image" => $image_url,
                );
                if ( $product->is_on_sale() ) {
                    $discount = ((float)$product->get_regular_price() - (float)$product->get_sale_price()) * (int)$item->get_quantity();
                    $discount_total += $discount;
                }
                $item_total += ((float)$product->get_regular_price()*$item->get_quantity());
                array_push($itemsArray, $temp);
            }

            $isItemsAvailable = true;
            foreach ($itemsArray as $item) {
                if ($item["price"] == 0) {
                    $isItemsAvailable = false;
                    break;
                }
            }

            $custom_logo_id = get_theme_mod('custom_logo');
            $image = wp_get_attachment_image_src($custom_logo_id, 'full');

            $merchantObj = array(
                "name" => get_bloginfo('name'),
                "logo" => $image[0],
                "back_url" => get_bloginfo('url') . "/checkout/",
                "shipping_charges" => round((float)$order->get_shipping_total(), 2),
                "promo_discount" => round((float)$discount_total, 2),
                "tax_amount" => round((float)$order->get_total_tax(), 2)
            );

            $this->console_log("Environment is: " . $environment, '');
            if ($amount != ((float)$order->get_total_tax() + (float)$order->get_shipping_total() + $item_total - round((float)$discount_total, 2))) {
                $this->console_log("Not sending item details", '');
                $isItemsAvailable = false;
                $merchantObj = array(
                    "name" => get_bloginfo('name'),
                    "logo" => $image[0],
                    "back_url" => get_bloginfo('url') . "/checkout/",
                    "shipping_charges" => 0,
                    "promo_discount" => 0
                );
            } else {
                $this->console_log("Total amount is: " . $amount, '');
                $this->console_log("Items total is: " . $item_total, '');
                $this->console_log("Tax amount is: " . round((float)$order->get_total_tax(), 2), '');
                $this->console_log("Shipping amount is: " . (float)$order->get_shipping_total(), '');
                $this->console_log("Discount is: " . round((float)$discount_total, 2), '');
            }

            $countryCode = $order->get_billing_country();
            if (strlen($countryCode) == 0) {
                $countryCode = $order->get_shipping_country();
            }
            if (strlen($countryCode) == 0) {
                if ($currencyCode == "VND") {
                    $countryCode = "VN";
                } else if ($currencyCode == "THB") {
                    $countryCode = "TH";
                } else if ($currencyCode == "IDR") {
                    $countryCode = "ID";
                } else if ($currencyCode == "MYR") {
                    $countryCode = "MY";
                } else if ($currencyCode == "SGD") {
                    $countryCode = "SG";
                } else if ($currencyCode == "PHP") {
                    $countryCode = "PH";
                } else if ($currencyCode == "TWD") {
                    $countryCode = "TW";
                } else if ($currencyCode == "KRW") {
                    $countryCode = "KR";
                } else if ($currencyCode == "USD") {
                    $countryCode = "US";
                }
            }

            $billingShippingArray = $this->getBillingShippingInfo($order);

            if ($isItemsAvailable) {
                $reqObj = array(
                    "chaipay_key" => "$publicKey",
                    "merchant_details" => $merchantObj,
                    "merchant_order_id" => "$merchantOrder",
                    "signature_hash" => "$signature",
                    "amount" => (float)$amount,
                    "currency" => "$currencyCode",
                    "description" => "Order Id: " . $order->get_id(),
                    "order_details" => $itemsArray,
                    "billing_details" => $billingShippingArray[0],
                    "shipping_details" => $billingShippingArray[1],
                    "success_url" => "$red",
                    "failure_url" => "$red",
                    "country_code" => $countryCode,
                    "expiry_hours" => 96,
                    "is_checkout_embed" => false,
                    "show_shipping_details" => $this->getOptionBool('show_shipping_details'),
                    "show_back_button" => $this->getOptionBool('show_back_button'),
                    "default_guest_checkout" => $this->getOptionBool('default_guest_checkout'),
                    "environment" => $environment,
                    "source" => "woocommerce",
                    "customer_details" => array(
                        "email_address" => $order->get_billing_email(),
                        "phone_number" => $order->get_billing_phone(),
                        "name" => $order->get_billing_first_name() . " " . $order->get_billing_last_name()
                    )
                );
            } else {
                $reqObj = array(
                    "chaipay_key" => "$publicKey",
                    "merchant_details" => $merchantObj,
                    "merchant_order_id" => "$merchantOrder",
                    "signature_hash" => "$signature",
                    "amount" => (float)$amount,
                    "currency" => "$currencyCode",
                    "description" => "Order Id: " . $order->get_id(),
                    "billing_details" => $billingShippingArray[0],
                    "shipping_details" => $billingShippingArray[1],
                    "success_url" => "$red",
                    "failure_url" => "$red",
                    "country_code" => $countryCode,
                    "expiry_hours" => 96,
                    "is_checkout_embed" => false,
                    "show_shipping_details" => $this->getOptionBool('show_shipping_details'),
                    "show_back_button" => $this->getOptionBool('show_back_button'),
                    "default_guest_checkout" => $this->getOptionBool('default_guest_checkout'),
                    "environment" => $environment,
                    "source" => "woocommerce",
                    "customer_details" => array(
                        "email_address" => $order->get_billing_email(),
                        "phone_number" => $order->get_billing_phone(),
                        "name" => $order->get_billing_first_name() . " " . $order->get_billing_last_name()
                    )
                );
            }

            $token = $this->ChaiportGenerateJwtToken($publicKey, $secretKey);

            $this->console_log("checkout object is", $reqObj);

            if (isset($_POST['cancel'])) {
                $order->add_order_note("User cancelled the order before payment");
                $order->update_status('wc-failed');
                $redirectUrl = $order->get_view_order_url();
                //$redirectUrl = get_bloginfo('url') . "/my-account/view-order/" . $order->get_id() . "/";
                if (wp_redirect($redirectUrl)) {
                    exit;
                }
            }

            ?>

            <!--            <button id="btn" class="button" onclick="pay()">Pay Now</button>-->
            <!--            <textarea id="errMsg" hidden></textarea>-->
            <!--            <textarea id="respMsg" hidden></textarea>-->
            <!--            <form method="post" style="float: left;">-->
            <!--                <button id="btn" class="button" name="cancel">Cancel</button>-->
            <!--            </form>-->
            <!--            <div class="chaipay-container" id="chaipay-container"-->
            <!--                 style="z-index: 1000000000;position: fixed;top: 0;display: none;left: 0;height: 100%;width: 100%;backface-visibility: hidden;overflow-y: visible;">-->
            <!--                <div class="chaipay-backdrop"-->
            <!--                     style="min-height: 100%; transition: all 0.3s ease-out 0s; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5);"></div>-->
            <!--                <iframe style="opacity: 1; height: 100%; position: relative; background: none; display: block; border: 0 none transparent; margin: 0; padding: 0; z-index: 2; width: 100%;"-->
            <!--                        allowtransparency="true" frameborder="0" width="100%" height="100%" allowpaymentrequest="true"-->
            <!--                        src="" id="chaipay-checkout-frame" class="chaipay-checkout-frame"></iframe>-->
            <!--            </div>-->


            <style>
                body {
                    font-family: Arial, sans-serif;
                    background-color: #f8f8f8;
                }
                .loader {
                    border: 8px solid #f3f3f3;
                    border-top: 8px solid #fb6425;
                    border-radius: 50%;
                    width: 50px;
                    height: 50px;
                    animation: spin 2s linear infinite;
                    margin: 0 auto;
                    display: block;
                    margin-top: 100px;
                }
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                h1 {
                    text-align: center;
                    color: #fb6425;
                    margin-top: 30px;
                }
                p {
                    text-align: center;
                    font-size: 18px;
                    color: #777;
                    margin-top: 10px;
                }
                a {
                    text-align: center;
                    display: block;
                    margin-top: 20px;
                    font-size: 18px;
                    color: #fb6425;
                    text-decoration: none;
                }
                a:hover {
                    text-decoration: underline;
                }
            </style>

            <body>
            <div class="loader"></div>
            <h1>Redirecting to Payment Screen</h1>
            <p>Please wait while we redirect you to the payment screen...</p>
            </body>

            <script>
                const chaipay = new window.ChaiPay({
                    // Your Chai Pay Key
                    chaiPayKey: "<?php echo esc_html($publicKey); ?>",
                    jwtToken: "<?php echo esc_html($token); ?>",
                })

                window.onload = chaipay.checkoutService.checkout(<?php echo json_encode($reqObj, JSON_UNESCAPED_SLASHES); ?>)

                function pay() {
                    chaipay.checkoutService.checkout(<?php echo json_encode($reqObj, JSON_UNESCAPED_SLASHES); ?>)
                }
            </script>

            <?php
        }

        /**
         * Fetch Payment Link Method
         */
        function getPL($order_id, $publicKey, $privateKey) {
            $apiUrl = "https://api.portone.cloud/api/paymentLink/" . $order_id . "/status";
            // Create token header as a JSON string
            $jwt_header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
            $encodeData = array(
                "iss" => "CHAIPAY",
                "sub" => $publicKey,
                "iat" => time(),
                "exp" => time() + 500
            );
            $payload = json_encode($encodeData);

            $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($jwt_header));
            $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
            $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $privateKey, true);
            $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
            // Create JWT
            $token = $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;

            $header = array(
                'headers' => array(
                    'X-Chaipay-Client-Key' => $publicKey,
                    'Authorization' => "Bearer " . $token
                )
            );

            $response = wp_remote_get($apiUrl, $header);
            $this->console_log("Existing PL: ", $response);
            if (wp_remote_retrieve_response_code($response) == 200) {
                $result = wp_remote_retrieve_body( $response );
                $data = json_decode($result, true);
                return $data['content']['link'];
            } else {
                return "";
            }
        }


        /**
         * Custom Process Payment Method
         * @param String $order_id WC Order Id
         */
        function process_payment($order_id)
        {

            global $woocommerce;
            $order = wc_get_order($order_id);
            $woocommerce->session->set(self::SESSION_KEY, get_bloginfo('name') . "-" . $order_id);
            //$woocommerce->cart->empty_cart();

            $orderKey = $this->getOrderKey($order);

            if (version_compare(WOOCOMMERCE_VERSION, '2.1', '>=')) {
                return array(
                    'result' => 'success',
                    'redirect' => add_query_arg('key', $orderKey, $order->get_checkout_payment_url(true))
                );
            } else if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                return array(
                    'result' => 'success',
                    'redirect' => add_query_arg(
                        'order',
                        $order->get_id(),
                        add_query_arg('key', $orderKey, $order->get_checkout_payment_url(true))
                    )
                );
            } else {
                return array(
                    'result' => 'success',
                    'redirect' => add_query_arg(
                        'order',
                        $order->get_id(),
                        add_query_arg('key', $orderKey, get_permalink(get_option('woocommerce_pay_page_id')))
                    )
                );
            }
        }


        /**
         * Generate Billing & Shipping Object
         * @param WC_Order woocommerce order
         * @return Array billing and shipping objects
         */
        public function getBillingShippingInfo($order)
        {

            if (version_compare(WOOCOMMERCE_VERSION, '2.7.0', '>=')) {
                $billingObj = array(
                    "billing_name" => $order->get_formatted_billing_full_name(),
                    "billing_email" => $order->get_billing_email(),
                    "billing_phone" => $order->get_billing_phone(),
                    "billing_address" => array(
                        "city" => $order->get_billing_city(),
                        "country_code" => $order->get_billing_country(),
                        "locale" => "en",
                        "line_1" => $order->get_billing_address_1(),
                        "line_2" => $order->get_billing_address_2(),
                        "postal_code" => $order->get_billing_postcode(),
                        "state" => $order->get_billing_state(),
                    )
                );

                $shippingObj = array(
                    "shipping_name" => $order->get_formatted_shipping_full_name(),
                    "shipping_email" => $order->get_billing_email(),
                    "shipping_phone" => $order->get_billing_phone(),
                    "shipping_address" => array(
                        "city" => $order->get_shipping_city(),
                        "country_code" => $order->get_shipping_country(),
                        "locale" => "en",
                        "line_1" => $order->get_shipping_address_1(),
                        "line_2" => $order->get_shipping_address_2(),
                        "postal_code" => $order->get_shipping_postcode(),
                        "state" => $order->get_shipping_state(),
                    )
                );
            } else {
                $billingObj = array(
                    "billing_name" => $order->billing_first_name . ' ' . $order->billing_last_name,
                    "billing_email" => $order->billing_email,
                    "billing_phone" => $order->billing_phone,
                    "billing_address" => array(
                        "city" => $order->billing_city,
                        "country_code" => $order->billing_country,
                        "locale" => "en",
                        "line_1" => $order->billing_address_1,
                        "line_2" => $order->billing_address_2,
                        "postal_code" => $order->billing_postcode,
                        "state" => $order->billing_state
                    )
                );

                $shippingObj = array(
                    "shipping_name" => $order->shipping_first_name . ' ' . $order->shipping_last_name,
                    "shipping_email" => $order->billing_email,
                    "shipping_phone" => $order->billing_email,
                    "shipping_address" => array(
                        "city" => $order->shipping_city,
                        "country_code" => $order->shipping_country,
                        "locale" => "en",
                        "line_1" => $order->shipping_address_1,
                        "line_2" => $order->shipping_address_2,
                        "postal_code" => $order->shipping_postcode,
                        "state" => $order->shipping_state
                    )
                );
            }

            return [$billingObj, $shippingObj];
        }


        /**
         * Gets the Order Key from the Order
         * for all WC versions that we support
         * @param WC_Order woocommerce order object
         * @return String woocommerce order key
         */
        protected function getOrderKey($order)
        {

            if (version_compare(WOOCOMMERCE_VERSION, '3.0.0', '>=')) {
                return $order->get_order_key();
            }

            return $order->order_key;
        }


        /**
         * Generate Signature for the request
         * @return String signature calculated from inputs
         */
        public function ChaiportGenerateSignature($requestObj, $secretKey)
        {

            $data = array(
                'amount' => (float)$requestObj['amount'],
                'currency' => $requestObj['currency'],
                'failure_url' => $requestObj['failure_url'],
                'merchant_order_id' => $requestObj['merchant_order_id'],
                'client_key' => $requestObj['client_key'],
                'success_url' => $requestObj['success_url']
            );

            ksort($data);
            $data = http_build_query($data);
            $message = $data;

            return base64_encode(hash_hmac('sha256', $message, $secretKey, true));
        }


        /**
         * Generate Bool value from options
         * @param String options name
         * @return Bool option value
         */
        public function getOptionBool($name)
        {
            $option = $this->get_option($name);

            if ($option == "yes") {
                return true;
            }
            return false;
        }


        /**
         * Generate JWT token for the request
         * @param String chaipay key
         * @return String token string
         */
        public function ChaiportGenerateJwtToken($chaipayKey, $secretKey)
        {
            // Create token header as a JSON string
            $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);

            // Create token payload as a JSON string
            $encodeData = array(
                "iss" => "CHAIPAY",
                "sub" => $chaipayKey,
                "iat" => time(),
                "exp" => time() + 100
            );
            $payload = json_encode($encodeData);

            // Encode Header to Base64Url String
            $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
            // Encode Payload to Base64Url String
            $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
            // Create Signature Hash
            $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secretKey, true);
            // Encode Signature to Base64Url String
            $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
            // Create JWT
            return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
        }


        /*
         * Method to print required data in console
         * PLEASE USE WHILE DEBUG ONLY OR IMPORTANT LOGGING
         */
        public function console_log($message, $data)
        {
            echo '<script>';
            echo 'console.log("' . esc_html($message) . '")';
            echo '</script>';
            echo '<script>';
            echo 'console.log(' . json_encode($data) . ')';
            echo '</script>';
        }


        /**
         * Uses random_int as core logic and generates a random string
         * random_int is a pseudorandom number generator
         *
         * @param int $length
         * @return string
         */
        function getRandomStringRandomInt($length = 16)
        {
            $stringSpace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $pieces = [];
            $max = mb_strlen($stringSpace, '8bit') - 1;
            for ($i = 0; $i < $length; ++ $i) {
                $pieces[] = $stringSpace[random_int(0, $max)];
            }
            return implode('', $pieces);
        }
    }
}

function portone_cart_checkout_blocks_compatibility() {

    if( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'cart_checkout_blocks',
            __FILE__,
            true // true (compatible, default) or false (not compatible)
        );
    }

}


function portone_gateway_block_support() {

    if( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
        return;
    }

    // here we're including our "gateway block support class"
    require_once plugin_dir_path( __FILE__ ) . 'includes/portoneBlocks.php';

    // registering the PHP class we have just included
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
            $payment_method_registry->register( new WC_Portone_Gateway_Blocks_Support );
        }
    );

}
