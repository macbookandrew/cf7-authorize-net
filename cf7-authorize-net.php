<?php
/*
 * Plugin Name: Contact Form 7 to Authorize.net
 * Plugin URI: https://github.com/macbookandrew/cf7-authorize-net/
 * Description: Handles payment from Contact Form 7 forms through Authorize.net
 * Version: 1.1.3
 * Author: AndrewRMinion Design
 * Author URI: https://andrewrminion.com
 * GitHub Plugin URI: https://github.com/macbookandrew/cf7-authorize-net/
 */


/* prevent this file from being accessed directly */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// load Authorize.net SDK
require( 'authorize-sdk/autoload.php' );
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
            'ordertotal'        => 'Total Purchase Amount',
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
                <p class="desc"><label for="ignore-form">%s</ignore></p>',
                checked( $ignore_form, true, false ),
                'Don&rsquo;t send anything from this form to Authorize.net.'
            ),
        ),
        'authorization-type' => array(
            'label'     => 'Authorization Type',
            'docs_url'  => 'http://andrewrminion.com/2017/02/contact-form-7-to-authorize-net/',
            'field'     => sprintf(
                '<label><input id="capture" name="cf7-authorize[authorization-type]" value="capture" %1$s type="radio" %4$s /> Authorize and Capture</label>
                <label><input id="authorize" name="cf7-authorize[authorization-type]" value="authorize" %2$s type="radio" %4$s /> Authorize Only</label>
                <p class="desc"><label for="cf7-authorize[authorization-type]">%3$s</ignore></p>',
                checked( $authorization_type, 'capture', false ),
                checked( $authorization_type, 'authorize', false ),
                'Type of transaction',
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
        }

        // set up API credentials
        $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
        $merchantAuthentication->setName( $options['cf7_authorize_api_login_id'] );
        $merchantAuthentication->setTransactionKey( $options['cf7_authorize_api_transaction_key'] );

        // create the payment data for a credit card
        $creditCard = new AnetAPI\CreditCardType();
        $creditCard->setCardNumber( str_replace(' ', '', $posted_data[$field_matches['cardnumber']] ) );
        $creditCard->setCardCode( $posted_data[$field_matches['cvv']] );
        $creditCard->setExpirationDate( $posted_data[$field_matches['expmonth']] . '-' . $posted_data[$field_matches['expyear']] );
        $paymentType = new AnetAPI\PaymentType();
        $paymentType->setCreditCard( $creditCard );

        // add billing info
        $customerAddress = new AnetAPI\CustomerAddressType();
        $customerData = new AnetAPI\CustomerDataType();
        $customerAddress->setFirstName( $posted_data[$field_matches['billing_fname']] );
        $customerAddress->setLastName( $posted_data[$field_matches['billing_lname']] );
        $customerAddress->setCompany( $posted_data[$field_matches['billing_company']] );
        $customerAddress->setAddress( $posted_data[$field_matches['billing_address']] );
        $customerAddress->setCity( $posted_data[$field_matches['billing_city']] );
        $customerAddress->setState( $posted_data[$field_matches['billing_state']] );
        $customerAddress->setZip( $posted_data[$field_matches['billing_postalcode']] );
        $customerAddress->setCountry( ( array_key_exists( 'billing_country', $field_matches ) ? $posted_data[$field_matches['country']] : 'US' ) );
        $customerAddress->setEmail( $posted_data[$field_matches['billing_email']] );
        $customerData->setEmail( $posted_data[$field_matches['billing_email']] );
        $customerAddress->setPhoneNumber( $posted_data[$field_matches['billing_phone']] );
        $customerAddress->setFaxNumber( $posted_data[$field_matches['billing_fax']] );

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

        // create a transaction
        $transactionRequest = new AnetAPI\TransactionRequestType();
        $transactionRequest->setTransactionType( $transaction_type );
        $transactionRequest->setAmount( $posted_data[$field_matches['ordertotal']] );
        $transactionRequest->setOrder( $order );
        $transactionRequest->setCustomer( $customerData );
        $transactionRequest->setBillTo( $customerAddress );
        $transactionRequest->setShipTo( $shippingAddress );
        $transactionRequest->setPayment( $paymentType );

        // send transaction
        $request = new AnetAPI\CreateTransactionRequest();
        $request->setMerchantAuthentication( $merchantAuthentication );
        $request->setTransactionRequest( $transactionRequest );
        $controller = new AnetController\CreateTransactionController( $request );
        if ( 'sandbox' == $options['cf7_authorize_environment'] ) {
            $response = $controller->executeWithApiResponse( \net\authorize\api\constants\ANetEnvironment::SANDBOX);
        } else {
            $response = $controller->executeWithApiResponse( \net\authorize\api\constants\ANetEnvironment::PRODUCTION);
        }

        // handle the response
        if ( $response != NULL ) {
            $tresponse = $response->getTransactionResponse();
        }

        add_filter( 'wpcf7_form_response_output', function( $output, $class, $content ) use ( $tresponse ) {
            return cf7_authorize_response( $output, $class, $content, $tresponse );
        }, 10, 3 );

        add_filter( 'wpcf7_ajax_json_echo', function( $items, $result ) use ( $tresponse ) {
            return cf7_authorize_response_json( $items, $result, $tresponse );
        }, 10, 2 );
    }
}

/**
 * Add custom content to WPCF7 output response
 * @param  string $output  HTML string of output
 * @param  string $class   string with HTML classes for the wrapper
 * @param  string $content string with response from WPCF7 plugin
 * @return string HTML string of output
 */
function cf7_authorize_response( $output, $class, $content, $tresponse ) {
    if ( $tresponse->getResponseCode() == '1' ) {
        $output .= str_replace( '</div>', '<br/>Approval code: ' . $tresponse->getAuthCode() . '<br/>Transaction ID: ' . $tresponse->getTransId() . '</div>', $output );
    } elseif ( $tresponse->getResponseCode() == '2' ) {
        $output = '<div class="wpcf7-response-output wpcf7-validation-errors">Error: Declined. Please contact us or your card issuer for more information.<br/>';
        foreach ( $tresponse->getErrors() as $error ) {
            $output .= 'Error code ' . $error->getErrorCode() . ': ' . $error->getErrorText();
        }
        $output .= '</div>';
    } elseif ( $tresponse->getResponseCode() == '3' ) {
        $output = '<div class="wpcf7-response-output wpcf7-validation-errors">Error. Please contact us or your card issuer for more information.<br/>';
        foreach ( $tresponse->getErrors() as $error ) {
            $output .= 'Error code ' . $error->getErrorCode() . ': ' . $error->getErrorText();
        }
        $output .= '</div>';
    }

    return $output;
}

/**
 * Add custom content to WPCF7 output JSON response
 * @param  array  $items  array of response info
 * @param  array  $result array of results
 * @return array array of responses to show
 */
function cf7_authorize_response_json( $items, $result, $tresponse ) {
    if ( $tresponse->getResponseCode() == '1' ) {
        $items['message'] .= '<br/>Approval code: ' . $tresponse->getAuthCode() . '<br/>Transaction ID: ' . $tresponse->getTransId();
    } elseif ( $tresponse->getResponseCode() == '2' ) {
        $items['message'] = 'Error: Declined. Please contact us or your card issuer for more information.<br/>';
        foreach ( $tresponse->getErrors() as $error ) {
            $items['message'] .= 'Error code ' . $error->getErrorCode() . ': ' . $error->getErrorText();
        }
        $items['mailSent'] = false;
    } elseif ( $tresponse->getResponseCode() == '3' ) {
        $items['message'] = 'Error. Please contact us or your card issuer for more information.<br/>';
        foreach ( $tresponse->getErrors() as $error ) {
            $items['message'] .= 'Error code ' . $error->getErrorCode() . ': ' . $error->getErrorText();
        }
        $items['mailSent'] = false;
    }

    return $items;
}
