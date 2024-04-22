<?php
/*
Plugin Name: Bulk Export Import Images SEO Attributes 
Description: Export media image attributes and import them after update
Version:1.0
Author: Simple Intelligent Systems
Author URI: https://simpleintelligentsystems.com
Requires at least: 4.4
Tested up to: 6.5.2
Requires PHP: 7.1 or higher
License: GPL3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}


$wp_version = get_bloginfo('version');
if ( $wp_version < 4.5 ) {
    add_action( 'admin_init', 'deactivate_plugin_now' );
    add_action( 'admin_notices', 'errormsg' );
}

function deactivate_plugin_now() {
    $plugin=plugin_basename(__FILE__);
    if ( is_plugin_active($plugin) ) {
        deactivate_plugins($plugin);
        unset($_GET['activate']);
    }
}

function errormsg () {
    $class = 'notice notice-error';
    $message = __( 'Error you did not meet the WP minimum version', 'text-domain' );
    printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );
}



add_action( 'admin_notices', 'print_plugin_admin_notices' );

function print_plugin_admin_notices() 
{              
    if ( ! empty( $_GET['mediametaexpimp_admin_notice'] ) ) // phpcs:ignore
    {
        $classnm='';
        if( $_GET['mediametaexpimp_notice_mode'] === "success") // phpcs:ignore
            $classnm='notice-success';
        else
            $classnm='notice-error';
        

        $html =  '<div class="notice '.$classnm.' is-dismissible">';
        $html .= '<p><strong>' . $_GET['mediametaexpimp_admin_notice'] . '</strong></p>';// phpcs:ignore
        $html .= '</div>';
        echo $html;

        
    }
}


// Add a menu item for the plugin page
function my_plugin_menu() {
    add_menu_page(
        'Bulk Export Import Images SEO Attributes',
        'Export Import Images SEO Attributes',
        'manage_options',
        'mediametaexpimp-page',
        'mediametaexpimp_page_content',
        'dashicons-admin-generic',
        20
    );
}

add_action('admin_menu', 'my_plugin_menu');

// Content of the plugin page
function mediametaexpimp_page_content() 
{
    if( current_user_can( 'edit_users' ) ) { //check capability
?>
    <div class="wrap">
        <h2>Bulk Export Import Images SEO Attributes</h2>
        
        <section id="section1" class="plugin-section">
            <div class="plugin">
                <h3>Export</h3>
                <p>Click the button below to download latest 50 images' meta data in CSV format.</p>
                <form id="downloadForm" method="POST" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="mediametaexpimp_generate_csv_file">
                    <?php wp_nonce_field('mediametaexpimp_exp_action', 'mediametaexpimp_exp_nonce'); ?>
                    <button type="submit" name="submit" id="downloadButton">Download CSV File</button>
                </form>
            </div>
        </section>

        <section id="section2" class="plugin-section">
            <div class="plugin">
                <h3>Import</h3>
                <p>Please read the instructions below.</p>
                <ul>
                    <li>Download the CSV file from the above section and update the required values. </li>
                    <li>Do not remove the Heading row.</li>
                    <li>Do not remove any columns from the CSV file. You should not make chnages to the ID and URL columns as they will be ignored.</li>
                    <li>Do not change any ID values as it will result in updating wrong image's meta data or leaving the image if no records are found against the modified ID value.</li>
                    <li>Keep only those records for which you  want to update the meta data.</li>
                    <li>Upload the CSV file once you are done with the updates. Click the button below to upload your file in CSV format.</li>
                </ul>

                <form id="uploadForm" method="POST" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data" >
                    <input type="hidden" name="action" value="mediametaexpimp_import_csv_file">
                    <?php wp_nonce_field('mediametaexpimp_imp_action', 'mediametaexpimp_imp_nonce'); ?>
                    <input type="file" name="csv_file" id="csv_file">
                    <input type="submit" name="submit" value="Import CSV">
                </form>
            </div>
        </section>

        <style>
            ul {margin: 0;padding: 0;}
            ul li {display: block;}
            section {padding: 20px;}
            .plugin-section {margin-bottom: 30px;}
            .plugin-section h3 {border-bottom: 2px solid #333;padding-bottom: 10px;margin-bottom: 20px;}
            .plugin {background-color: #f9f9f9;border: 1px solid #ddd;padding: 10px;margin-bottom: 10px;}
        </style>

    </div>
<?php
    } //end of checking capability
}

// Callback function 
function mediametaexpimp_imp_action() {
    
    if(isset($_POST['submit'])) 
    {
        // Verify nonce
        if ( !isset( $_POST['mediametaexpimp_imp_nonce'] ) || !wp_verify_nonce( $_POST['mediametaexpimp_imp_nonce'], 'mediametaexpimp_imp_action' ) ) {
            die( 'Unauthorized request' );
        }
        if(isset($_FILES['csv_file'])) 
        {
            $file = $_FILES['csv_file'];
            if($file['error'] == UPLOAD_ERR_OK && $file['type'] == 'text/csv') 
            {
                $csv_data = array_map('str_getcsv', file($file['tmp_name']));

                // Process $csv_data to update WordPress data
                foreach ($csv_data as $ind=>$row) 
                {
                    if($ind>0) //leave the header row
                    {
                        $post_id = (int) $row[0];
                        // Change basic fields on attachment post
                        wp_update_post(array(
                           'ID'           => $post_id,
                           'post_title'   => sanitize_text_field($row[4]),
                           'post_content' => sanitize_text_field($row[2]),
                           'post_excerpt' => sanitize_text_field($row[3]),
                       ));
                        // Change ALT Text
                        update_post_meta($post_id, '_wp_attachment_image_alt', sanitize_text_field($row[5]));

                        
                    }
                    
                }
                // redirect the user to the appropriate page with message
                wp_redirect( esc_url_raw( add_query_arg( 
                    array(
                    'mediametaexpimp_admin_notice' => 'CSV file imported successfully.',
                    'mediametaexpimp_notice_mode' => 'success',
                    ),
                    admin_url('admin.php?page=mediametaexpimp-page') 
                    ) ) );
                
            } 
            else {
                // redirect the user to the appropriate page with message
                wp_redirect( esc_url_raw( add_query_arg( 
                    array(
                    'mediametaexpimp_admin_notice' => 'Invalid file format. Please upload a CSV file.',
                    'mediametaexpimp_notice_mode' => 'error',
                    ),
                    admin_url('admin.php?page=mediametaexpimp-page') 
                    ) ) );
            }
        }
    }


}
// Register the Import form submission action
add_action('admin_post_mediametaexpimp_import_csv_file', 'mediametaexpimp_imp_action');


// Callback function 
function mediametaexpimp_exp_action() {

    // Verify nonce
    if ( !isset( $_POST['mediametaexpimp_exp_nonce'] ) || !wp_verify_nonce( $_POST['mediametaexpimp_exp_nonce'], 'mediametaexpimp_exp_action' ) ) {
        die( 'Unauthorized request' );
    }
    
    $args = array(
    'post_type' => 'attachment',
    'numberposts' => 50,
    'post_mime_type' => 'image',
    ); 

    $attachments = get_posts($args);
    if ($attachments) 
    {
        $props =array();
        $csvFileName = 'output.csv';
        $tempFile = tmpfile();
        // Set CSV file header
        fputcsv($tempFile, array('id','url','descr','caption','title','alt'));
        $i=0;
        foreach ($attachments as $post) 
        {
            $props[$i]['id'] = $post -> ID;
            $props[$i]['url']  = wp_get_attachment_image_url($post->ID, 'full'); //for direct path to image
            $props[$i]['descr'] = $post -> post_content;
            $props[$i]['caption'] = $post -> post_excerpt;
            $props[$i]['title'] = $post -> post_title;
            $alt_text = get_post_meta($post ->ID, '_wp_attachment_image_alt', true);
            $props[$i]['alt'] = $alt_text;
            fputcsv($tempFile, $props[$i]);
            $i++;
        }

        
        // Set appropriate headers to force download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $csvFileName . '"');

        rewind($tempFile);

        // Output the temporary file's content
        fpassthru($tempFile);

        // Close the temporary file handle
        fclose($tempFile); // phpcs:ignore
    }
    exit;
}

// Register the export form submission action
add_action('admin_post_mediametaexpimp_generate_csv_file', 'mediametaexpimp_exp_action');
