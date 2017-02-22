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
    <input type="text" name="cf7_authorize_settings[cf7_authorize_api_login_id]" placeholder="5NaF7aL34G" size="20" value="<?php echo $options['cf7_authorize_api_login_id']; ?>">
    <?php
}

// print API Key field
function cf7_authorize_api_transaction_key_render() {
    $options = get_option( 'cf7_authorize_settings' ); ?>
    <input type="text" name="cf7_authorize_settings[cf7_authorize_api_transaction_key]" placeholder="2z1aGdL1534fbG2c" size="20" value="<?php echo $options['cf7_authorize_api_transaction_key']; ?>">
    <?php
}

// print environment email field
function cf7_authorize_environment_render() {
    $environment = get_option( 'cf7_authorize_settings' )['cf7_authorize_environment']; ?>
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
