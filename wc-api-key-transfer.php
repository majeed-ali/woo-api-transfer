<?php
/**
 * Plugin Name: WooCommerce API Key Transfer
 * Description: Export and import WooCommerce REST API keys as JSON.
 * Version:     1.5.0
 * Author:      Abdul Majeed
 * Author URI:  https://abdulmajeedali.cin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register the Tools submenu page
 */
add_action( 'admin_menu', function() {
    add_management_page(
        'WC API Key Transfer',      // page title
        'WC API Key Transfer',      // menu title
        'manage_woocommerce',       // capability
        'wc-api-key-transfer',      // slug
        'wc_api_key_transfer_page'  // callback
    );
} );

/**
 * Handle the export via admin-post.php?action=wc_api_key_export
 */
add_action( 'admin_post_wc_api_key_export', 'wc_api_key_handle_export' );
function wc_api_key_handle_export() {
    // Permission & nonce check
    if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'wc_api_key_export' ) ) {
        wp_die( 'Permission denied.' );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'woocommerce_api_keys';
    $rows  = $wpdb->get_results( "SELECT * FROM `{$table}`", ARRAY_A );

    if ( empty( $rows ) ) {
        wp_die( 'No API keys found.' );
    }

    // Send JSON and exit cleanly
    wp_send_json( $rows );
}

/**
 * Render the Tools → WC API Key Transfer page
 */
function wc_api_key_transfer_page() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( 'Insufficient permissions.' );
    }

    // Process an import if one was submitted
    if ( isset( $_POST['wc_api_key_import'] ) && check_admin_referer( 'wc_api_key_import' ) ) {
        wc_api_key_do_import();
    }

    // Build the export URL
    $export_url = wp_nonce_url(
        admin_url( 'admin-post.php?action=wc_api_key_export' ),
        'wc_api_key_export'
    );
    ?>
    <div class="wrap">
        <h1>WC API Key Transfer</h1>

        <!-- EXPORT -->
        <h2>Export Keys</h2>
        <p>
            <a href="<?php echo esc_url( $export_url ); ?>" class="button button-primary">
                Download JSON of All API Keys
            </a>
        </p>

        <hr>

        <!-- IMPORT -->
        <h2>Import Keys</h2>
        <p>Upload a JSON file (previously exported) to import new keys into this site.</p>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field( 'wc_api_key_import' ); ?>
            <input type="file" name="api_key_file" accept=".json" required>
            <input type="hidden" name="wc_api_key_import" value="1">
            <br><br>
            <button class="button">Import Keys</button>
        </form>
    </div>
    <?php
}

/**
 * Import API keys from the uploaded JSON
 */
function wc_api_key_do_import() {
    global $wpdb;
    $table = $wpdb->prefix . 'woocommerce_api_keys';

    if ( empty( $_FILES['api_key_file']['tmp_name'] ) ) {
        echo '<div class="notice notice-error"><p>No file uploaded.</p></div>';
        return;
    }

    $data = file_get_contents( $_FILES['api_key_file']['tmp_name'] );
    $keys = json_decode( $data, true );

    if ( json_last_error() || ! is_array( $keys ) ) {
        echo '<div class="notice notice-error"><p>Invalid JSON file.</p></div>';
        return;
    }

    $imported = 0;
    foreach ( $keys as $row ) {
        // Skip if key already exists
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM `{$table}` WHERE consumer_key = %s",
            $row['consumer_key']
        ) );
        if ( $exists ) {
            continue;
        }

        // Remove the old primary key so MySQL will assign a new one
        unset( $row['key_id'] );

        // Insert the record
        $wpdb->insert( $table, $row );
        if ( $wpdb->insert_id ) {
            $imported++;
        }
    }

    echo '<div class="notice notice-success"><p>Imported ' . intval( $imported ) . ' new API key(s).</p></div>';
}
