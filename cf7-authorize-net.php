<?php
/*
 * Plugin Name: Contact Form 7 to Authorize.net
 * Plugin URI: https://github.com/macbookandrew/cf7-authorize-net/
 * Description: Handles payment from Contact Form 7 forms through Authorize.net
 * Version: 1.0.0
 * Author: AndrewRMinion Design
 * Author URI: https://andrewrminion.com
 * GitHub Plugin URI: https://github.com/macbookandrew/cf7-authorize-net/
 */


/* prevent this file from being accessed directly */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* register scripts */
add_action( 'admin_enqueue_scripts', 'cf7_robly_scripts' );
function cf7_robly_scripts() {
    wp_register_script( 'chosen', plugins_url( 'js/chosen.jquery.min.js', __FILE__ ), array( 'jquery' ) );
    wp_register_style( 'chosen', plugins_url( 'css/chosen.min.css', __FILE__ ) );

    wp_register_script( 'cf7-authorize-backend', plugins_url( 'js/cf7-authorize-backend.min.js', __FILE__ ), array( 'jquery', 'chosen' ) );
    wp_register_style( 'cf7-authorize', plugins_url( 'css/cf7-authorize.min.css', __FILE__ ), array( 'chosen' ) );
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
        'amount'        => 'Total Purchase Amount',
        'cardnumber'    => 'Credit Card Number',
        'expmonth'      => 'Expiration Month',
        'expyear'       => 'Expiration Year',
        'cvv'           => 'CVV Code',
        'fname'         => 'First Name',
        'lname'         => 'Last Name',
        'address1'      => 'Street Address 1',
        'address2'      => 'Street Address 2',
        'city'          => 'City',
        'state'         => 'State',
        'postalcode'    => 'Zip Code',
        'country'       => 'Country',
        'email'         => 'Email Address',
        'phone'         => 'Phone Number',
    );

    // HTML string of Authorize.net fields
    $fields_options = '';
    foreach ( $authorize_fields as $id => $label ) {
        $fields_options .= '<option value="' . $id . '">' . $label . '</option>';
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
                '<label><input id="capture" name="cf7-authorize[authorization-type]" value="capture" %1$s type="radio" /> Authorize and Capture</label>
                <label><input id="authorize" name="cf7-authorize[authorization-type]" value="authorize" %2$s type="radio" /> Authorize Only</label>
                <p class="desc"><label for="cf7-authorize[authorization-type]">%3$s</ignore></p>',
                checked( $authorization_type, 'capture', false ),
                checked( $authorization_type, 'authorize', false ),
                'Authorize-and-capture payment or authorize-only.'
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

