<?php
/*
 * Plugin Name: Contact Form 7 to Authorize.net
 * Plugin URI: https://github.com/macbookandrew/cf7-authorize-net/
 * Description: Handles payment from Contact Form 7 forms through Authorize.net
 * Version: 1.3.0
 * Author: AndrewRMinion Design
 * Author URI: https://andrewrminion.com
 * GitHub Plugin URI: https://github.com/macbookandrew/cf7-authorize-net/
 */


/* prevent this file from being accessed directly */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// load Authorize.net SDK
require( 'authorize-sdk/vendor/autoload.php' );
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;

/* register scripts */
add_action( 'admin_enqueue_scripts', 'cf7_authorize_net_scripts_backend' );
function cf7_authorize_net_scripts_backend() {
    wp_register_script( 'chosen', plugins_url( 'js/chosen.jquery.min.js', __FILE__ ), array( 'jquery' ) );
    wp_register_style( 'chosen', plugins_url( 'css/chosen.min.css', __FILE__ ) );

    wp_register_script( 'cf7-authorize-backend', plugins_url( 'js/cf7-authorize-backend.min.js', __FILE__ ), array( 'jquery', 'chosen' ) );
    wp_register_style( 'cf7-authorize', plugins_url( 'css/cf7-authorize.min.css', __FILE__ ), array( 'chosen' ) );
}

add_action( 'wp_enqueue_scripts', 'cf7_authorize_net_scripts' );
function cf7_authorize_net_scripts() {
    wp_enqueue_script( 'cf7-authorize-format', plugins_url( 'js/format-card-number.min.js', __FILE__ ), array( 'jquery' ), NULL, true );
}

/* add settings page */
add_action( 'admin_menu', 'cf7_authorize_add_admin_menu' );
add_action( 'admin_init', 'cf7_authorize_settings_init' );

// add to menu
function cf7_authorize_add_admin_menu() {
    add_options_page( 'Contact Form 7 to Authorize.net', 'CF7&rarr;Authorize.net', 'manage_options', 'cf7_authorize_net', 'cf7_authorize_options_page' );
}

// add settings section and fields
function cf7_authorize_settings_init() {
    register_setting( 'cf7_authorize_options', 'cf7_authorize_settings' );

    // API settings
    add_settings_section(
        'cf7_authorize_options_keys_section',
        __( 'Add your API Keys', 'cf7_authorize' ),
        'cf7_authorize_api_settings_section_callback',
        'cf7_authorize_options'
    );

    add_settings_field(
        'cf7_authorize_api_login_id',
        __( 'API Login ID', 'cf7_authorize' ),
        'cf7_authorize_api_login_id_render',
        'cf7_authorize_options',
        'cf7_authorize_options_keys_section'
    );

    add_settings_field(
        'cf7_authorize_api_transaction_key',
        __( 'API Transaction Key', 'cf7_authorize' ),
        'cf7_authorize_api_transaction_key_render',
        'cf7_authorize_options',
        'cf7_authorize_options_keys_section'
    );

    add_settings_field(
        'cf7_authorize_environment',
        __( 'Environment', 'cf7_authorize' ),
        'cf7_authorize_environment_render',
        'cf7_authorize_options',
        'cf7_authorize_options_keys_section'
    );
}

// print API ID field
function cf7_authorize_api_login_id_render() {
    $options = get_option( 'cf7_authorize_settings' ); ?>
    <input type="text" name="cf7_authorize_settings[cf7_authorize_api_login_id]" placeholder="5NaF7aL34G" size="20" value="<?php echo ( ( is_array( $options ) && array_key_exists( 'cf7_authorize_api_login_id', $options ) ) ? $options['cf7_authorize_api_login_id'] : NULL ); ?>">
    <?php
}

// print API Key field
function cf7_authorize_api_transaction_key_render() {
    $options = get_option( 'cf7_authorize_settings' ); ?>
    <input type="text" name="cf7_authorize_settings[cf7_authorize_api_transaction_key]" placeholder="2z1aGdL1534fbG2c" size="20" value="<?php echo ( ( is_array( $options ) && array_key_exists( 'cf7_authorize_api_transaction_key', $options ) ) ? $options['cf7_authorize_api_transaction_key'] : NULL ); ?>">
    <?php
}

// print environment field
function cf7_authorize_environment_render() {
    $environment = get_option( 'cf7_authorize_settings' )['cf7_authorize_environment'];
    if ( ! isset( $environment ) ) {
        $environment = 'production';
    }
    ?>
    <label><input type="radio" name="cf7_authorize_settings[cf7_authorize_environment]" value="production" <?php checked( $environment, 'production' ); ?>> Production</label>
    <label><input type="radio" name="cf7_authorize_settings[cf7_authorize_environment]" value="sandbox" <?php checked( $environment, 'sandbox' ); ?>> Sandbox</label>
    <?php
}

// print API settings description
function cf7_authorize_api_settings_section_callback() {
    echo __( 'Enter your API Login ID and API Transaction Key below. <a href="https://support.authorize.net/authkb/index?page=content&id=A682" target="_blank">Instructions to locate them</a>.', 'cf7_authorize' );
}

// print form
function cf7_authorize_options_page() { ?>
    <div class="wrap">
       <h2>Contact Form 7 to Authorize.net</h2>
        <form action="options.php" method="post">

            <?php
            settings_fields( 'cf7_authorize_options' );
            do_settings_sections( 'cf7_authorize_options' );
            submit_button();
            ?>

        </form>
    </div>
    <?php
}

// add WPCF7 metabox
add_action( 'wpcf7_add_meta_boxes', 'cf7_authorize_wpcf7_add_meta_boxes' );
function cf7_authorize_wpcf7_add_meta_boxes() {
    add_meta_box(
        'cf7s-subject',
        'Authorize.net Settings',
        'cf7_authorize_wpcf7_metabox',
        NULL,
        'form',
        'low'
    );
}

// print WPCF7 metabox
function cf7_authorize_wpcf7_metabox( $cf7 ) {
    $post_id = $cf7->id();
    $settings = cf7_authorize_get_form_settings( $post_id );

    // prevent undefined index issues
    $all_submissions = isset( $settings['all-submissions'] ) ? $settings['all-submissions'] : NULL;
    $saved_fields = isset( $settings['fields'] ) ? $settings['fields'] : NULL;
    $ignore_form = isset( $settings['ignore-form'] ) ? $settings['ignore-form'] : NULL;
    $authorization_type = isset( $settings['authorization-type'] ) ? $settings['authorization-type'] : 'capture';
    $cf7_authorize_settings = get_option( 'cf7_authorize_settings' );

    // check for API and transaction keys
    if ( ! is_array( $cf7_authorize_settings ) || ( array_key_exists( 'cf7_authorize_api_login_id', $cf7_authorize_settings ) && ! isset( $cf7_authorize_settings['cf7_authorize_api_login_id'] ) ) || ( array_key_exists( 'cf7_authorize_api_transaction_key', $cf7_authorize_settings ) && ! isset( $cf7_authorize_settings['cf7_authorize_api_transaction_key'] ) ) ) {
        $message = 'Note: you <strong>must</strong> add your API Login ID and API Transaction Key on the <a href="' . get_admin_url() . '/options-general.php?page=cf7_authorize_net">settings page</a>.';
    }

    wp_enqueue_script( 'chosen' );
    wp_enqueue_style( 'chosen' );
    wp_enqueue_script( 'cf7-authorize-backend' );
    wp_enqueue_style( 'cf7-authorize' );

    // get all WPCF7 fields
    $wpcf7_shortcodes = WPCF7_ShortcodeManager::get_instance();
    $field_types_to_ignore = array( 'recaptcha', 'clear', 'submit' );
    $form_fields = array();
    foreach ( $wpcf7_shortcodes->get_scanned_tags() as $this_field ) {
        if ( ! in_array( $this_field['type'], $field_types_to_ignore ) ) {
            $form_fields[] = $this_field['name'];
        }
    }

    // get saved fields and combine with WPCF7
    if ( $saved_fields ) {
        $all_fields = array_merge( $form_fields, array_keys( $saved_fields ) );
    } else {
        $all_fields = $form_fields;
    }

    // list of supported Authorize.net fields
    $authorize_fields = array(
        'Card Info'     => array(
            'cardnumber'    => 'Credit Card Number',
            'expmonth'      => 'Expiration Month',
            'expyear'       => 'Expiration Year',
            'expcombined'   => 'Expiration Date (combined)',
            'cvv'           => 'CVV Code',
        ),
        'Billing Info'  => array(
            'billing_fname'         => 'First Name',
            'billing_lname'         => 'Last Name',
            'billing_company'       => 'Company',
            'billing_address'       => 'Street Address',
            'billing_city'          => 'City',
            'billing_state'         => 'State',
            'billing_postalcode'    => 'Zip Code',
            'billing_country'       => 'Country',
            'billing_email'         => 'Email Address',
            'billing_phone'         => 'Phone Number',
            'billing_fax'           => 'Fax Number',
        ),
        'Shipping Info'  => array(
            'shipping_fname'         => 'First Name',
            'shipping_lname'         => 'Last Name',
            'shipping_company'       => 'Company',
            'shipping_address'       => 'Street Address',
            'shipping_city'          => 'City',
            'shipping_state'         => 'State',
            'shipping_postalcode'    => 'Zip Code',
            'shipping_country'       => 'Country',
        ),
        'Order Info'    => array(
            'description'       => 'Order Description',
            'invoicenum'        => 'Invoice Number',
            'shipping'          => 'Shipping Amount',
            'taxamount'         => 'Tax Amount',
            'ordertotal'        => 'Total Purchase/Subscription Amount',
            'ordertotal_other'  => 'Total Purchase/Subscription Amount (custom amount; overrides main total amount field)',
        ),
        'Subscription Info'    => array(
            'description'       => 'Subscription Description',
            'interval_length'   => 'Interval Length (1–12 months or 7–365 days)',
            'interval_unit'     => 'Interval Unit (must be either “days” or “months”)',
            'start_date'        => 'Subscription Start Date (valid PHP datetime format)',
            'total_occurrences' => 'Total Occurrences (1–9,999)',
            'trial_occurrences' => 'Trial Occurrences (1–99)',
            'trial_amount'      => 'Trial Amount',
            'invoicenum'        => 'Invoice Number',
        ),
    );

    // HTML string of Authorize.net fields
    $fields_options = '';
    foreach ( $authorize_fields as $key => $group ) {
        $fields_options .= '<optgroup label="' . $key . '">';
        foreach ( $group as $id => $label ) {
            $fields_options .= '<option value="' . $id . '">' . $label . '</option>';
        }
        $fields_options .= '</optgroup>';
    }

    // start setting up Authorize.net settings fields
    $fields = array(
        'ignore-field' => array(
            'label'     => 'Ignore this Contact Form',
            'docs_url'  => 'http://andrewrminion.com/2017/02/contact-form-7-to-authorize-net/',
            'field'     => sprintf(
                '<input id="ignore-form" name="cf7-authorize[ignore-form]" value="1" %s type="checkbox" />
                <span class="desc"><label for="ignore-form">%s</ignore></span>',
                checked( $ignore_form, true, false ),
                'Don&rsquo;t send anything from this form to Authorize.net.'
            ),
        ),
        'authorization-type' => array(
            'label'     => 'Authorization Type',
            'docs_url'  => 'http://andrewrminion.com/2017/02/contact-form-7-to-authorize-net/',
            'field'     => sprintf(
                '<label><input id="capture" name="cf7-authorize[authorization-type]" value="capture" %1$s type="radio" %4$s /> Authorize and Capture </label>
                <label><input id="authorize" name="cf7-authorize[authorization-type]" value="authorize" %2$s type="radio" %4$s /> Authorize Only </label>
                <label><input id="subscription" name="cf7-authorize[authorization-type]" value="subscription" %3$s type="radio" %4$s /> Create a Recurring Subscription </label> ',
                checked( $authorization_type, 'capture', false ),
                checked( $authorization_type, 'authorize', false ),
                checked( $authorization_type, 'subscription', false ),
                $ignore_form ? 'disabled' : ''
            ),
        ),
    );

    // add all CF7 fields to Authorize.net settings fields
    foreach ( $all_fields as $this_field ) {
        $this_authorize_field_list = $settings['fields'][$this_field];
        $this_field_options = $fields_options;
        if ( is_array( $this_authorize_field_list ) ) {
            foreach ( $this_authorize_field_list as $this_authorize_field ) {
                $this_field_options = str_replace( $this_authorize_field . '">', $this_authorize_field . '" selected="selected">', $fields_options );
            }
        }

        $fields[$this_field] = array(
            'label'     => '<code>' . esc_html( $this_field ) . '</code> Field',
            'docs_url'  => 'http://andrewrminion.com/2017/02/contact-form-7-to-authorize-net/',
            'field'     => sprintf(
                '<label>
                    <select name="cf7-authorize[fields][%1$s][]" multiple %3$s>
                        %2$s
                    </select>
                </label>
                <p class="desc">Add contents of the <code>%1$s</code> field to these Authorize.net field(s) or leave blank to ignore this field.</p>',
                $this_field,
                $this_field_options,
                $ignore_form ? 'disabled' : ''
            )
        );
    }

    // add a hidden row to use for cloning
    $fields['custom-field-template'] = array(
        'label'     => '<input type="text" placeholder="Custom Field Name" name="custom-field-name" /> Field',
        'docs_url'  => 'http://andrewrminion.com/2017/02/contact-form-7-to-authorize-net/',
        'field'     => sprintf(
            '<label>
                <select name="cf7-authorize[fields][%1$s][]" multiple>
                    %2$s
                </select>
            </label>
            <p class="desc">Add contents of the <code><span class="name">%1$s</span></code> field to these Authorize.net field(s)</p>',
            'custom-field-template-name',
            str_replace( 'selected="selected"', '', $fields_options )
        )
    );

    $rows = array();

    foreach ( $fields as $field_id => $field )
        $rows[] = sprintf(
            '<tr class="cf7-authorize-field-%1$s">
                <th>
                    <label for="%1$s">%2$s</label><br/>
                </th>
                <td>%3$s</td>
            </tr>',
            esc_attr( $field_id ),
            $field['label'],
            $field['field']
        );

    printf(
        '<p class="cf7-authorize-message">%3$s</p>
        <table class="form-table cf7-authorize-table">
            %1$s
        </table>
        <p><button class="cf7-authorize-add-custom-field button-secondary" %2$s>Add a custom field</button></p>',
        implode( '', $rows ),
        $ignore_form ? 'disabled' : '',
        $message
    );

}

// register WPCF7 Authorize.net Settings panel
add_filter( 'wpcf7_editor_panels', 'cf7_authorize_register_wpcf7_panel' );
function cf7_authorize_register_wpcf7_panel( $panels ) {
    $form = WPCF7_ContactForm::get_current();
    $post_id = $form->id();

    $panels['cf7-authorize-panel'] = array(
        'title' => 'Authorize.net Settings',
        'callback' => 'cf7_authorize_wpcf7_metabox',
    );

    return $panels;
}

// save WPCF7 Authorize.net settings
add_action( 'wpcf7_save_contact_form', 'cf7_authorize_wpcf7_save_contact_form' );
function cf7_authorize_wpcf7_save_contact_form( $cf7 ) {
    if ( ! isset( $_POST ) || empty( $_POST ) || ! isset( $_POST['cf7-authorize'] ) || ! is_array( $_POST['cf7-authorize'] ) ) {
        return;
    }

    $post_id = $cf7->id();

    if ( ! $post_id ) {
        return;
    }

    if ( $_POST['cf7-authorize'] ) {
        update_post_meta( $post_id, '_cf7_authorize', $_POST['cf7-authorize'] );
    }
}

// retrieve WPCF7 Authorize.net settings
function cf7_authorize_get_form_settings( $form_id, $field = null, $fresh = false ) {
    $form_settings = array();

    if ( isset( $form_settings[ $form_id ] ) && ! $fresh ) {
        $settings = $form_settings[ $form_id ];
    } else {
        $settings = get_post_meta( $form_id, '_cf7_authorize', true );
    }

    $settings = wp_parse_args(
        $settings,
        array(
            '_cf7_authorize' => NULL,
        )
    );

    // Cache it for re-use
    $form_settings[ $form_id ] = $settings;

    // Return a specific field value
    if ( isset( $field ) ) {
        if ( isset( $settings[ $field ] ) ) {
            return $settings[ $field ];
        } else {
            return null;
        }
    }

    return $settings;
}

/* hook into WPCF7 submission */
add_action( 'wpcf7_before_send_mail', 'cf7_authorize_submit_to_authorize', 10, 1 );
function cf7_authorize_submit_to_authorize( $form ) {
    global $wpdb;
    $one_time_transaction_types = array( 'capture', 'authorize' );

    // get API keys
    $options = get_option( 'cf7_authorize_settings' );

    // get posted data
    $submission = WPCF7_Submission::get_instance();
    if ( $submission ) {
        $posted_data = $submission->get_posted_data();
    }
    $settings = cf7_authorize_get_form_settings( $posted_data['_wpcf7'], NULL, true );

    // get array keys for form data
    if ( $settings['fields'] ) {
        $field_matches = array();
        foreach( $settings['fields'] as $id => $field ) {
            foreach ( $field as $this_field ) {
                $field_matches[$this_field] = $id;
            }
        }
    }

    // check if we should process this form and if matching fields are set
    if ( $settings['ignore-form'] !== '1' && $field_matches ) {
        $form_title = get_the_title($posted_data['_wpcf7']);

        // set transaction type
        if ( 'capture' == $settings['authorization-type'] ) {
            $transaction_type = 'authCaptureTransaction';
        } elseif ( 'authorize' == $settings['authorization-type'] ) {
            $transaction_type = 'authOnlyTransaction';
        } elseif ( 'subscription' == $settings['authorization-type'] ) {
            $transaction_type = 'subscription';
        }

        // set expiration date
        if ($posted_data[$field_matches['expcombined']]) {
            $expiration_date = str_replace( ' / ', '-', $posted_data[$field_matches['expcombined']] );
        } else {
            $expiration_date = $posted_data[$field_matches['expmonth']] . '-' . $posted_data[$field_matches['expyear']];
        }

        // set up API credentials
        $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
        $merchantAuthentication->setName( $options['cf7_authorize_api_login_id'] );
        $merchantAuthentication->setTransactionKey( $options['cf7_authorize_api_transaction_key'] );

        // create the payment data for a credit card
        $creditCard = new AnetAPI\CreditCardType();
        $creditCard->setCardNumber( str_replace(' ', '', $posted_data[$field_matches['cardnumber']] ) );
        $creditCard->setCardCode( $posted_data[$field_matches['cvv']] );
        $creditCard->setExpirationDate( $expiration_date );
        $paymentType = new AnetAPI\PaymentType();
        $paymentType->setCreditCard( $creditCard );

        // add billing info
        $customerAddress = new AnetAPI\CustomerAddressType();
        $customerData = new AnetAPI\CustomerDataType();
        $customer = new AnetAPI\CustomerType();
        $customerAddress->setFirstName( $posted_data[$field_matches['billing_fname']] );
        $customerAddress->setLastName( $posted_data[$field_matches['billing_lname']] );
        $customerAddress->setCompany( $posted_data[$field_matches['billing_company']] );
        $customerAddress->setAddress( $posted_data[$field_matches['billing_address']] );
        $customerAddress->setCity( $posted_data[$field_matches['billing_city']] );
        $customerAddress->setState( $posted_data[$field_matches['billing_state']] );
        $customerAddress->setZip( $posted_data[$field_matches['billing_postalcode']] );
        $customerAddress->setCountry( ( array_key_exists( 'billing_country', $field_matches ) ? $posted_data[$field_matches['country']] : 'US' ) );
        $customerData->setEmail( $posted_data[$field_matches['billing_email']] );
        $customer->setEmail( $posted_data[$field_matches['billing_email']] );
        $customerAddress->setPhoneNumber( $posted_data[$field_matches['billing_phone']] );
        $customer->setPhoneNumber( $posted_data[$field_matches['billing_phone']] );
        $customerAddress->setFaxNumber( $posted_data[$field_matches['billing_fax']] );
        $customer->setFaxNumber( $posted_data[$field_matches['billing_fax']] );

        // add shipping info
        $shippingAddress = new AnetAPI\NameAndAddressType();
        $shippingAddress->setFirstName( $posted_data[$field_matches['shipping_fname']] );
        $shippingAddress->setLastName( $posted_data[$field_matches['shipping_lname']] );
        $shippingAddress->setCompany( $posted_data[$field_matches['shipping_company']] );
        $shippingAddress->setAddress( $posted_data[$field_matches['shipping_address']] );
        $shippingAddress->setCity( $posted_data[$field_matches['shipping_city']] );
        $shippingAddress->setState( $posted_data[$field_matches['shipping_state']] );
        $shippingAddress->setZip( $posted_data[$field_matches['shipping_postalcode']] );
        $shippingAddress->setCountry( ( array_key_exists( 'shipping_country', $field_matches ) ? $posted_data[$field_matches['country']] : 'US' ) );

        // add order info
        $order = new AnetAPI\OrderType();
        $paymentDetails = new AnetAPI\PaymentDetailsType();
        $order->setInvoiceNumber( $posted_data[$field_matches['invoicenum']] );
        $order->setDescription( ( array_key_exists( 'description', $field_matches ) ? $posted_data[$field_matches['description']] : $form_title ) );
        $paymentDetails->setShippingHandling( $posted_data[$field_matches['shipping']] );
        $paymentDetails->setTax( $posted_data[$field_matches['taxamount']] );

        // set order total amount
        $ordertotal = $posted_data[$field_matches['ordertotal_other']] ? $posted_data[$field_matches['ordertotal_other']] : $posted_data[$field_matches['ordertotal']];
        if ( ! is_float( $ordertotal ) && is_int( $ordertotal ) ) {
            $ordertotal .= '.00';
        }

        if ( in_array( $settings['authorization-type'], $one_time_transaction_types ) ) {
            // create a transaction
            $transactionRequest = new AnetAPI\TransactionRequestType();
            $transactionRequest->setTransactionType( $transaction_type );
            $transactionRequest->setAmount( $ordertotal );
            $transactionRequest->setOrder( $order );
            $transactionRequest->setCustomer( $customerData );
            $transactionRequest->setBillTo( $customerAddress );
            $transactionRequest->setShipTo( $shippingAddress );
            $transactionRequest->setPayment( $paymentType );

            // set up transaction request
            $request = new AnetAPI\CreateTransactionRequest();
            $request->setMerchantAuthentication( $merchantAuthentication );
            $request->setTransactionRequest( $transactionRequest );
            $controller = new AnetController\CreateTransactionController( $request );

            // set up request to send
            $request = new AnetAPI\CreateTransactionRequest();
            $request->setMerchantAuthentication( $merchantAuthentication );
            $request->setTransactionRequest( $transactionRequest );
            $controller = new AnetController\CreateTransactionController( $request );
        } elseif ( 'subscription' == $settings['authorization-type'] ) {
            // create a subscription
            $subscription = new AnetAPI\ARBSubscriptionType();
            $subscription->setName( ( array_key_exists( 'description', $field_matches ) ? $posted_data[$field_matches['description']] : $form_title ) );

            // set subscription data variables
            $interval_length = $posted_data[$field_matches['interval_length']];
            $interval_unit = $posted_data[$field_matches['interval_unit']];
            $start_date = $posted_data[$field_matches['start_date']];
            $total_occurrences = $posted_data[$field_matches['total_occurrences']];
            $trial_occurrences = $posted_data[$field_matches['trial_occurrences']];
            $trial_amount = $posted_data[$field_matches['trial_amount']];

            // validate data and set defaults
            if ( ! in_array( $interval_unit, array( 'days', 'months' ) ) ) {
                $interval_unit = 'days';
            }
            if ( $interval_unit == 'months' && ( $interval_length < 1 || $interval_length > 12 ) ) {
                $interval_length = 1;
            } elseif ( $interval_unit == 'days' && ( $interval_length < 7 || $interval_length > 365 ) ) {
                $interval_length = '7';
            }
            if ( date( 'U', $start_date ) < time() ) {
                $start_date = date( 'Y-m-d' );
            }
            if ( ! is_int( (integer) $total_occurrences ) || $total_occurrences < 1 || $total_occurrences > 9999 ) {
                $total_occurrences = 1;
            }

            $interval = new AnetAPI\PaymentScheduleType\IntervalAType();
            $interval->setLength( $interval_length );
            $interval->setUnit( $interval_unit );

            $paymentSchedule = new AnetAPI\PaymentScheduleType();
            $paymentSchedule->setInterval($interval);
            $paymentSchedule->setStartDate(new DateTime( $start_date ));
            $paymentSchedule->setTotalOccurrences( $total_occurrences );
            $paymentSchedule->setTrialOccurrences( $trial_occurrences );

            $subscription->setPaymentSchedule( $paymentSchedule );
            $subscription->setAmount( $ordertotal );
            $subscription->setTrialAmount( $trial_amount );

            // set customer and card info
            $subscription->setBillTo( $customerAddress );
            $subscription->setShipTo( $shippingAddress );
            $subscription->setCustomer( $customer );
            $subscription->setPayment( $paymentType );

            // set up request to send
            $request = new AnetAPI\ARBCreateSubscriptionRequest();
            $request->setMerchantAuthentication( $merchantAuthentication );
            $request->setSubscription( $subscription );
            $controller = new AnetController\ARBCreateSubscriptionController( $request );
        }

        // send request
        if ( 'sandbox' == $options['cf7_authorize_environment'] ) {
            $api_response = $controller->executeWithApiResponse( \net\authorize\api\constants\ANetEnvironment::SANDBOX);
        } else {
            $api_response = $controller->executeWithApiResponse( \net\authorize\api\constants\ANetEnvironment::PRODUCTION);
        }

        // notify user
        cf7_authorize_net_parse_response( $api_response, $settings['authorization-type'], $one_time_transaction_types );
    }
}

/**
 * Handle transaction and subscription responsens
 * @param  array    $api_response               response from Authorize.net API
 * @param  string   $authorization_type         type of authorization
 * @param  array    $one_time_transaction_types array of one-time transaction types
 */
function cf7_authorize_net_parse_response( $api_response, $authorization_type, $one_time_transaction_types ) {
    if ( in_array( $authorization_type, $one_time_transaction_types ) ) {
        $transaction_response = $api_response->getTransactionResponse();
        if ( $transaction_response->getResponseCode() == '1' ) {
            $modified_items['message'] = '<br/>Approval code: ' . $transaction_response->getAuthCode() . '<br/>Transaction ID: ' . $transaction_response->getTransId();
        } elseif ( $transaction_response->getResponseCode() == '2' ) {
            $modified_items['message'] = 'Error: Declined. Please contact us or your card issuer for more information.<br/>';
            foreach ( $transaction_response->getErrors() as $error ) {
                $items['message'] .= 'Error code ' . $error->getErrorCode() . ': ' . $error->getErrorText();
            }
            $modified_items['mailSent'] = false;
        } elseif ( $transaction_response->getResponseCode() == '3' ) {
            $modified_items['message'] = 'Error. Please contact us or your card issuer for more information.<br/>';
            foreach ( $transaction_response->getErrors() as $error ) {
                $items['message'] .= 'Error code ' . $error->getErrorCode() . ': ' . $error->getErrorText();
            }
            $modified_items['mailSent'] = false;
        }
    } else {
        if ( ( $api_response != NULL ) && ( $api_response->getMessages()->getResultCode() == "Ok" ) ) {
            $modified_items['message'] = '<br/>Success: created subscription ID ' . $api_response->getSubscriptionId() . "\n";
        } else {
            $modified_items['message'] = 'Error: ';
            foreach ( $api_response->getMessages()->getMessage() as $message ) {
                $modified_items['message'] .= $message->getCode() . ' ' . $message->getText() . '<br/>';
            }
            $modified_items['mailSent'] = false;
        }
    }

    add_filter( 'wpcf7_form_response_output', function( $output, $class, $content ) use ( $modified_items ) {
        return cf7_authorize_response( $output, $class, $content, $modified_items );
    }, 10, 3 );

    add_filter( 'wpcf7_ajax_json_echo', function( $items, $result ) use ( $modified_items ) {
        return cf7_authorize_response_json( $items, $result, $modified_items );
    }, 10, 2 );
}

/**
 * Add custom content to WPCF7 output response
 * @param  string $output         HTML string of output
 * @param  string $class          string with HTML classes for the wrapper
 * @param  string $content        string with response from WPCF7 plugin
 * @param  array  $modified_items modified responses
 * @return string HTML string of output
 */
function cf7_authorize_response( $output, $class, $content, $modified_items ) {
    if ( $modified_items['mailSent'] !== false ) {
        $output .= str_replace( '</div>', '<br/>' . $modified_items['message'] . '</div>', $output );
    } else {
        $output = '<div class="wpcf7-response-output wpcf7-validation-errors">' . $modified_items['message'] . '</div>';
    }

    return $output;
}

/**
 * Add custom content to WPCF7 output JSON response
 * @param  array $items          array of response info
 * @param  array $result         array of results
 * @param  array $modified_items modified responses
 * @return array array of responses to show
 */
function cf7_authorize_response_json( $items, $result, $modified_items ) {
    if ( $modified_items['mailSent'] !== false ) {
        $items['message'] .= $modified_items['message'];
    } else {
        $items['message'] = $modified_items['message'];
        $items['mailSent'] = $modified_items['mailSent'];
        $items['status'] = 'validation_failed';
    }

    return $items;
}
