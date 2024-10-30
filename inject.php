<?php
/*
Plugin Name: Inject
Plugin URI: http://inject.netcod.es
Description: Control Wordpress Dynamic Content
Version: 1.11
Author: Netcodes
Author URI: http://www.netcod.es/
*/

/**
 * Inject - A Wordpress module for dynamic content
 *
 * @package Inject
 * @since 1.0
 *
 * @version 1.11
 * @copyright Netcodes
 * @author Netcodes
 * @link http://cut.lu/inject
 * @license
 */


/**
 * Doesn't work if PHP version is not 4.0.6 or higher
 */
if ( version_compare( phpversion(), '5.2.4', '<' ) ) {
    exit( 'Inject plugin require PHP 5.2.4 or newer. Please update!' );
}
global $wp_version;
if ( version_compare( $wp_version, '3.1', '<' ) ) {
    exit( 'Inject plugin require Wordpress 3.0 or newer. Please update!' );
}


define( 'NC_INJECT_VERSION', '1.11' );
if ( strpos( __FILE__, WP_PLUGIN_DIR ) !== false )
    define( 'NC_INJECT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
else
    // default fallback if plugin dir is a symlink.
    define( 'NC_INJECT_PLUGIN_URL', WP_PLUGIN_URL . '/inject' );
define( 'NC_INJECT_DOMAIN', 'inject' );
define( 'NC_INJECT_DEBUG', false );
// default fields value
define( 'NC_INJECT_DEFAULT_FIELDS', 'object,id,date,status,type,permalink,author,title,content,excerpt,categories,tags,featured,images,attachments,metas' );
define( 'NC_INJECT_CSS_FILE', 'inject.css' );
define( 'NC_INJECT_JS_FILE', 'inject.js' );

define( 'NC_INJECT_SUPPORT', 'http://cut.lu/inject/support' );
define( 'NC_INJECT_SUPPORT_PLUGIN', 'http://cut.lu/inject' );
define( 'NC_INJECT_SUPPORT_DONATE', 'http://cut.lu/inject/donate' );
define( 'NC_INJECT_SUPPORT_HIRE', 'http://cut.lu/inject/hire' );
define( 'NC_INJECT_FOLLOW_EASID', 'https://easid.cc/Netcodes' );
define( 'NC_INJECT_FOLLOW_TWITTER', 'http://cut.lu/t' );
define( 'NC_INJECT_FOLLOW_FACEBOOK', 'http://cut.lu/fb' );
define( 'NC_INJECT_FOLLOW_GOOGLE', 'http://cut.lu/g+' );
define( 'NC_INJECT_FOLLOW_PINTEREST', 'http://cut.lu/p' );
define( 'NC_INJECT_NEWS_URL', 'http://inject.netcod.es/feed/' );


$inject = new NC_Inject();

/**
 * The Inject Class
 *
 * @package Inject
 * @since 1.0
 */
class NC_Inject {

    var $options;

    function __construct() {

        add_action( 'init', array(&$this, 'init_options'), 0 );
        add_action( 'init', array(&$this, 'register_post_type'), 1 );
        if ( is_admin() ) {
            add_action( 'admin_init', array( &$this, 'admin_init' ) );
            add_action( 'admin_init', array( &$this, 'initialize_plugin_options' ) );
            add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
            add_action( 'admin_enqueue_scripts', array( &$this, 'admin_scripts' ), 10 );
            add_action( 'add_meta_boxes', array( &$this, 'meta_box' ) );
            add_action( 'save_post', array( &$this, 'save_postdata' ) );
            add_action( 'media_buttons', array( &$this, 'add_media_buttons' ), 20 );
            add_action( 'wp_ajax_nc_inject_shortcode', array( &$this, 'get_shortcode_helper' ) );

        } else {
            add_action( 'wp_enqueue_scripts', array( &$this, 'enqueue_scripts' ), 10 );
            add_shortcode( 'inject', array( &$this, 'do_shortcode' ) );
            if ( $this->options['widget_shortcode'] )
                add_filter('widget_text', 'do_shortcode');
        }

    }


/* ------------------------------------------------------------------------ *
 * Initialization
 * ------------------------------------------------------------------------ */

    public function activation() {}

    public function deactivation() {}

    /**
      * Initialize module options.
      *
      * All module options are saved in wordpress option named `nc_inject_options`
      *
      * @since 1.0
      */
    public function init_options() {

        $options = get_option( 'nc_inject_options' );

        if ( get_option( 'nc_inject_updated' ) === false )
            add_option( 'nc_inject_updated', strtotime( current_time( 'mysql' ) ), '', 'no' );

        // options
        if ($options === false){
            $options = array();
        }

        // default values
        $default_options = array(
            'widget_shortcode' => 0,
            'cache' => 0,
            'css' => '',
            'js' => ''
        );

        $has_change = false;
        foreach ( $default_options as $key => $option ) {
            if ( ! array_key_exists( $key, $options ) ) {
                self::log( "no options " . $key );
                $options[ $key ] = $option;
                $has_change = true;
            }
        }

        if ( $has_change ) {
            update_option( 'nc_inject_options', $options );
        }

    }


/* ------------------------------------------------------------------------ *
 * Back-end
 * ------------------------------------------------------------------------ */

    public function admin_init() {

        // Locals
        load_plugin_textdomain(
            NC_INJECT_DOMAIN,
            WP_PLUGIN_DIR . '/' . dirname(plugin_basename(__FILE__)) . '/lang',
            dirname( plugin_basename( __FILE__ ) ) . '/lang'
        );

    }

    /**
      * register admin menu
      *
      * @since 1.0
      */
    public function admin_menu() {

        // Main menu
        add_menu_page(
            __('Inject', 'nc_inject'),
            __('Inject', 'nc_inject'),
            'manage_options',
            'nc_inject',
            '',
            NC_INJECT_PLUGIN_URL.'/img/inject.png'
        );
        // options page
        $ret = add_submenu_page(
            'nc_inject',
            __('Options', 'nc_inject'),
            __('Options', 'nc_inject'),
            'manage_options',
            'nc_inject_options',
            array($this, 'nc_inject_options_page')
        );

    }


/* ------------------------------------------------------------------------ *
 * Template Page
 * ------------------------------------------------------------------------ */

    /**
     * Register the nc_inject post type used for inject template.
     *
     * @since 1.0
     *
     */
    public function register_post_type() {

        $labels = array(
            'name'                => _x( 'Inject Templates', 'Post Type General Name', 'nc_inject' ),
            'singular_name'       => _x( 'Inject Template', 'Post Type Singular Name', 'nc_inject' ),
            'menu_name'           => __( 'Template', 'nc_inject' ),
            'parent_item_colon'   => __( 'Parent Template:', 'nc_inject' ),
            'all_items'           => __( 'All Templates', 'nc_inject' ),
            'view_item'           => __( 'View Template', 'nc_inject' ),
            'add_new_item'        => __( 'Add New Template', 'nc_inject' ),
            'add_new'             => __( 'New Template', 'nc_inject' ),
            'edit_item'           => __( 'Edit Template', 'nc_inject' ),
            'update_item'         => __( 'Update Template', 'nc_inject' ),
            'search_items'        => __( 'Search templates', 'nc_inject' ),
            'not_found'           => __( 'No template found', 'nc_inject' ),
            'not_found_in_trash'  => __( 'No template found in Trash', 'nc_inject' ),
        );

        $capabilities = array(
            'edit_post'           => 'manage_options',
            'read_post'           => 'manage_options',
            'delete_post'         => 'manage_options',
            'edit_posts'          => 'manage_options',
            'edit_others_posts'   => 'manage_options',
            'publish_posts'       => 'manage_options',
            'read_private_posts'  => 'manage_options',
        );

        $args = array(
            'label'               => __( 'nc_inject', 'nc_inject' ),
            'description'         => __( 'Inject ...', 'nc_inject' ),
            'labels'              => $labels,
            'supports'            => array( 'title', 'revisions', ),
            'hierarchical'        => false,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => 'nc_inject',
            'show_in_nav_menus'   => false,
            'show_in_admin_bar'   => false,
            'menu_position'       => 5,
            'menu_icon'           => NC_INJECT_PLUGIN_URL.'/img/inject.png',
            'can_export'          => true,
            'has_archive'         => false,
            'exclude_from_search' => true,
            'publicly_queryable'  => false,
            'query_var'           => 'nc_inject',
            'rewrite'             => false,
            'capabilities'        => $capabilities,
        );

        register_post_type( 'nc_inject', $args );
    }

    /**
     * Register custom metabox used by the nc_inject post type.
     *
     * @since 1.0
     */
    public function meta_box() {
        add_meta_box(
            'nc_inject_meta_box',
            __( 'Template Configuration', 'nc_inject' ),
            array( &$this, 'render_meta_box' ),
            'nc_inject',
            'normal',
            'high'
        );
        add_meta_box(
            'nc_inject_extra_meta_box',
            __( 'Extra Configuration', 'nc_inject' ),
            array( &$this, 'render_extra_meta_box' ),
            'nc_inject',
            'normal',
            'default'
        );
        add_meta_box(
            'nc_inject_support_meta_box',
            __( 'Support', 'nc_inject' ),
            array( &$this, 'render_support_meta_box' ),
            'nc_inject',
            'side',
            'default'
        );
        add_meta_box(
            'nc_inject_news_meta_box',
            __( 'Inject News', 'nc_inject' ),
            array( &$this, 'render_news_meta_box' ),
            'nc_inject',
            'side',
            'default'
        );
        add_meta_box(
            'nc_inject_follow_meta_box',
            __( 'Follow Netcodes', 'nc_inject' ),
            array( &$this, 'render_follow_meta_box' ),
            'nc_inject',
            'side',
            'low'
        );
    }


    /**
     * Render the custom metabox nc_inject_meta_box.
     *
     * @since 1.0
     */
    public function render_meta_box( $post ) {

        // Use nonce for verification
        wp_nonce_field( plugin_basename( __FILE__ ), 'nc_inject_meta_box' );

        echo '<table class="nc_inject_form form-table">';

        $field = 'id';
        $value = get_post_meta( $post->ID, $key = '_nc_inject_' . $field, $single = true );
        echo '<tr id="nc_inject_form_line_' . $field . '" class="nc_inject_form_row"><th scope="row">';
            echo '<label for="nc_inject_' . $field . '">';
                _e( 'Id', 'nc_inject' );
            echo '</label></th><td>';
            echo '<input type="text" id="nc_inject_' . $field . '" name="nc_inject_' . $field . '" class="regular-text" value="' . esc_attr( $value ) . '" size="25" />';
            if ( trim( $value == '' ) ) {
                echo ' <span class="nc_error">'.
                    __( 'required.', 'nc_inject' ) .
                '</span>';
            }
            echo '<p class="description">'.
                __( 'This  id of the template to indicate which template you\'re going to use : [inject id="myTemplate" ... ] ! ', 'nc_inject' ) .
                '</p>';
        echo '</td></tr>';

        $field = 'solid';
        $value = get_post_meta( $post->ID, $key = '_nc_inject_' . $field, $single = true );
        if ( $value == "" )
            $value = False;
        echo '<tr id="nc_inject_form_line_' . $field . '" class="nc_inject_form_row"><th scope="row">';
                _e( 'Solid', 'nc_inject' );
            echo '</th><td>';
            echo '<label><input type="radio" id="nc_inject_' . $field . '" name="nc_inject_' . $field . '" value="0" ' . ( ! $value ? ' checked="checked"' : '' ) . ' /> ' . __( 'No', 'nc_inject' ) . '</label>';            echo '<br /><label><input type="radio" id="nc_inject_' . $field . '" name="nc_inject_' . $field . '" value="1" ' . ( $value ? ' checked="checked"' : '' ) . ' /> ' . __( 'Yes', 'nc_inject' ) . '</label>';
            echo '<p class="description">'.
                __( 'A <em>solid</em> template just load the current post and not search for a post list ! ', 'nc_inject' ) .
                __( 'You can also use solid template for just displaying a raw text that will not have to be parsed.', 'nc_inject' ) .
                __( 'Generally used to stock information used in many pages like an address, social share, gallery, sub-menu, ...', 'nc_inject' ) .
                '</p>';
        echo '</td></tr>';

        $field = 'fields';
        $value = get_post_meta( $post->ID, $key = '_nc_inject_' . $field, $single = true );
        if ( $value == "" )
            $value = NC_INJECT_DEFAULT_FIELDS;
        echo '<tr id="nc_inject_form_line_' . $field . '" class="nc_inject_form_row"><th scope="row">';
        echo '<label for="nc_inject_' . $field . '">';
                _e( 'Fields', 'nc_inject' );
            echo '</label></th><td>';
            echo $this->get_fields_form();
            echo '<input type="hidden" id="nc_inject_' . $field . '" name="nc_inject_' . $field . '" value="' . esc_attr( $value ) . '" />';
            echo '<p class="description">'. __( 'Select the fields that will be passed to the Twig template engine. <strong>Remove unnecessary fields.</strong> (<span id="ifh_remove_all">remove all</span>)', 'nc_inject' ) . '</p>';
            echo '<div id="nc_inject_fields_tip"> </div>';
        echo '</td></tr>';

        $field = 'extra_fields';
        $value = get_post_meta( $post->ID, $key = '_nc_inject_' . $field, $single = true );
        if ( $value == "" )
            $value = False;
        echo '<tr id="nc_inject_form_line_' . $field . '" class="nc_inject_form_row"><th scope="row">';
        echo '<label for="nc_inject_' . $field . '">';
                _e( 'Extra Fields', 'nc_inject' );
            echo '</label></th><td>';
            echo '<input type="text" id="nc_inject_' . $field . '" name="nc_inject_' . $field . '" class="regular-text" value="' . esc_attr( $value ) . '" />';
            echo '<p class="description">'. __( 'Add custom fields values (separated with commas). Usefull for custom attachments, simple meta value.', 'nc_inject' ) . '</p>';
        echo '</td></tr>';

        $field = 'template';
        $value = get_post_meta( $post->ID, $key = '_nc_inject_' . $field, $single = true );
        echo '<tr id="nc_inject_form_line_' . $field . '" class="nc_inject_form_row"><th scope="row">';
            echo '<label for="nc_inject_' . $field . '">';
                _e( 'Template', 'nc_inject' );
            echo '</label></th><td>';
            echo '<textarea id="nc_inject_' . $field . '" name="nc_inject_' . $field . '"" class="large-text code" style="display: none;">' . esc_textarea( $value ) . '</textarea> ';
            // echo '<p class="description">'. __( '', 'nc_inject' ) . '</p>';
        echo '</td></tr><tr><td colspan="2"><div id="nc_inject_' . $field . '_toolbar">' . $this->get_template_toolbar() . '</div></td></tr>';
        echo '</td></tr><tr><td colspan="2"><div id="nc_inject_' . $field . '_editor" style="position: relative; width: 100%; height: 400px;"></div></td></tr>';

        $field = 'debug';
        $value = get_post_meta( $post->ID, $key = '_nc_inject_' . $field, $single = true );
        if ( $value == "" )
            $value = False;
        echo '<tr id="nc_inject_form_line_' . $field . '" class="nc_inject_form_row"><th scope="row">';
                _e( 'Debug', 'nc_inject' );
            echo '</th><td>';
            echo '<label><input type="radio" id="nc_inject_' . $field . '" name="nc_inject_' . $field . '" value="0" ' . ( ! $value ? ' checked="checked"' : '' ) . ' /> ' . __( 'No', 'nc_inject' ) . '</label>';
            echo '<br /><label><input type="radio" id="nc_inject_' . $field . '" name="nc_inject_' . $field . '" value="1" ' . ( $value ? ' checked="checked"' : '' ) . ' /> ' . __( 'Yes', 'nc_inject' ) . '</label>';
            echo '<p class="description">'. __( 'Activate the debug option (dont\'t use in production mode)', 'nc_inject' ) . '</p>';
        echo '</td></tr>';

        $field = 'wpautop';
        $value = get_post_meta( $post->ID, $key = '_nc_inject_' . $field, $single = true );
        if ( $value == "" )
            $value = True;
        echo '<tr id="nc_inject_form_line_' . $field . '" class="nc_inject_form_row"><th scope="row">';
                _e( 'Use Wordpress Auto P', 'nc_inject' );
            echo '</th><td>';
            echo '<label><input type="radio" id="nc_inject_' . $field . '" name="nc_inject_' . $field . '" value="0" ' . ( ! $value ? ' checked="checked"' : '' ) . ' /> ' . __( 'No', 'nc_inject' ) . '</label>';
            echo '<br /><label><input type="radio" id="nc_inject_' . $field . '" name="nc_inject_' . $field . '" value="1" ' . ( $value ? ' checked="checked"' : '' ) . ' /> ' . __( 'Yes', 'nc_inject' ) . '</label>';
            echo '<p class="description">'. __( 'Use the <a href="http://codex.wordpress.org/Function_Reference/wpautop" target="_blank">wpautop</a> function for the content field', 'nc_inject' ) . '</p>';
        echo '</td></tr>';

        $field = 'shortcode';
        $value = get_post_meta( $post->ID, $key = '_nc_inject_' . $field, $single = true );
        if ( $value == "" )
            $value = True;
        echo '<tr id="nc_inject_form_line_' . $field . '" class="nc_inject_form_row"><th scope="row">';
                _e( 'Do Shortcode', 'nc_inject' );
            echo '</th><td>';
            echo '<label><input type="radio" id="nc_inject_' . $field . '" name="nc_inject_' . $field . '" value="0" ' . ( ! $value ? ' checked="checked"' : '' ) . ' /> ' . __( 'No', 'nc_inject' ) . '</label>';
            echo '<br /><label><input type="radio" id="nc_inject_' . $field . '" name="nc_inject_' . $field . '" value="1" ' . ( $value ? ' checked="checked"' : '' ) . ' /> ' . __( 'Yes', 'nc_inject' ) . '</label>';
            echo '<p class="description">'. __( 'Apply the <a href="http://codex.wordpress.org/Function_Reference/do_shortcode" target="_blank">do_shortcode</a> function for the content and the excerpt fields', 'nc_inject' ) . '</p>';
        echo '</td></tr>';

        $field = 'excerpt_length';
        $value = get_post_meta( $post->ID, $key = '_nc_inject_' . $field, $single = true );
        if ( $value == "" )
            $value = 150;
        echo '<tr id="nc_inject_form_line_' . $field . '" class="nc_inject_form_row"><th scope="row">';
            echo '<label for="nc_inject_' . $field . '">';
                _e( 'Excerpt Length', 'nc_inject' );
            echo '</label></th><td>';
            echo '<input type="text" id="nc_inject_' . $field . '" name="nc_inject_' . $field . '" class="small-text" value="' . esc_attr( $value ) . '" size="25" />';
            echo '<p class="description">'.
                __( 'The number of characters for the excerpt field (use 0 for all the excerpt).', 'nc_inject' ) .
                '</p>';
        echo '</td></tr>';

        echo '<tr class="nc_inject_form_row"><th scope="row" colspan="2">';
            echo '<input type="button"  class="button button-primary button-large" onclick="jQuery(\'#publish\').click();" value="' . __( 'Save', 'nc_inject' ) . '" />';
        echo '</th></tr>';


        echo '</table>';
        echo '
            <script>
                var editor = ace.edit("nc_inject_template_editor");
                editor.setTheme("ace/theme/clouds");
                editor.getSession().setMode("ace/mode/django");
                editor.getSession().setTabSize(4);
                editor.getSession().setUseSoftTabs(true);
                document.getElementById("nc_inject_template_editor").style.fontSize="16px";
                editor.setValue( jQuery("#nc_inject_template").val() );
                editor.clearSelection();
                editor.getSession().on("change", function(e) {
                    jQuery("#nc_inject_template").val( editor.getValue() );
                });
            </script>';
    }

    /**
     * Render the custom metabox nc_inject_extra_meta_box.
     *
     * @since 1.1
     */
    public function render_extra_meta_box( $post ) {

        echo '<table class="nc_inject_form form-table">';

        $field = 'css';
        $value = get_post_meta( $post->ID, $key = '_nc_inject_' . $field, $single = true );
        echo '<tr id="nc_inject_form_line_' . $field . '" class="nc_inject_form_row"><th scope="row">';
            echo '<label for="nc_inject_' . $field . '">';
                _e( 'CSS', 'nc_inject' );
            echo '</label></th><td>';
            echo '<textarea id="nc_inject_' . $field . '" name="nc_inject_' . $field . '" rows="10" cols="50" class="large-text code">' . esc_textarea( $value ) . '</textarea>';
            echo '<p class="description">'.
                __( 'Add custom CSS styles for this template.', 'nc_inject' ) .
                '</p>';
        echo '</td></tr>';


        $field = 'js';
        $value = get_post_meta( $post->ID, $key = '_nc_inject_' . $field, $single = true );
        echo '<tr id="nc_inject_form_line_' . $field . '" class="nc_inject_form_row"><th scope="row">';
            echo '<label for="nc_inject_' . $field . '">';
                _e( 'Javascript', 'nc_inject' );
            echo '</label></th><td>';
            echo '<textarea id="nc_inject_' . $field . '" name="nc_inject_' . $field . '" rows="10" cols="50" class="large-text code">' . esc_textarea( $value ) . '</textarea>';
            echo '<p class="description">'.
                __( 'Add custom JavaScript code for this template.', 'nc_inject' ) .
                '</p>';
        echo '</td></tr>';

        $field = 'public';
        $value = get_post_meta( $post->ID, $key = '_nc_inject_' . $field, $single = true );
        if ( $value == "" )
            $value = True;
        echo '<tr id="nc_inject_form_line_' . $field . '" class="nc_inject_form_row"><th scope="row">';
                _e( 'Public', 'nc_inject' );
            echo '</th><td>';
            echo '<label><input type="radio" id="nc_inject_' . $field . '" name="nc_inject_' . $field . '" value="0" ' . ( ! $value ? ' checked="checked"' : '' ) . ' /> ' . __( 'No', 'nc_inject' ) . '</label>';
            echo '<br /><label><input type="radio" id="nc_inject_' . $field . '" name="nc_inject_' . $field . '" value="1" ' . ( $value ? ' checked="checked"' : '' ) . ' /> ' . __( 'Yes', 'nc_inject' ) . '</label>';
            echo '<p class="description">'. __( 'If public the template will be visible in the shortcode helper for everyone.', 'nc_inject' ) . '</p>';
        echo '</td></tr>';

        echo '<tr class="nc_inject_form_row"><th scope="row" colspan="2">';
            echo '<input type="button"  class="button button-primary button-large" onclick="jQuery(\'#publish\').click();" value="' . __( 'Save', 'nc_inject' ) . '" />';
        echo '</th></tr>';


        echo '</table>';

    }



    /**
     * When the post is saved, saves our custom data.
     *
     * @param int $post_id the current post id
     * @since 1.0
     */
    public function save_postdata( $post_id ) {
        // verify if this is an auto save routine.
        // If it is our form has not been submitted, so we dont want to do anything
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ){
            die('auto_save');
            return;
        }

        // verify this came from the our screen and with proper authorization,
        // because save_post can be triggered at other times
        if ( ! isset( $_POST['nc_inject_meta_box'] ) || ! wp_verify_nonce( $_POST['nc_inject_meta_box'], plugin_basename( __FILE__ ) ) ) {
            //die('wpnonce');
            return;
        }

        // Check permissions
        if ( !current_user_can( 'edit_post', $post_id ) ) {
            die('capability');
            return;
        }

        if ( !current_user_can( 'manage_options' ) ){
            die('capability');
            return;
        }

        $post_ID = $_POST['post_ID'];
        $data = sanitize_text_field( $_POST['nc_inject_id'] );
        update_post_meta($post_ID, '_nc_inject_id', $data);

        $data = sanitize_text_field( $_POST['nc_inject_fields'] );
        update_post_meta($post_ID, '_nc_inject_fields', $data);

        $data = sanitize_text_field( $_POST['nc_inject_extra_fields'] );
        $data = preg_replace( array( '/\s+/', '/,+/', '/^,/', '/,$/' ), array( '', ',', '', ''), $data);
        update_post_meta($post_ID, '_nc_inject_extra_fields', $data);

        $data = sanitize_text_field( $_POST['nc_inject_solid'] );
        update_post_meta($post_ID, '_nc_inject_solid', $data != "0" ? True : False );

        //$data = $this->sanitize_textarea( $_POST['nc_inject_template'] );
        $data = $_POST['nc_inject_template'];
        update_post_meta($post_ID, '_nc_inject_template', $data);

        $data = sanitize_text_field( $_POST['nc_inject_debug'] );
        update_post_meta($post_ID, '_nc_inject_debug', $data);

        $data = sanitize_text_field( $_POST['nc_inject_wpautop'] );
        update_post_meta($post_ID, '_nc_inject_wpautop', $data);

        $data = sanitize_text_field( $_POST['nc_inject_shortcode'] );
        update_post_meta($post_ID, '_nc_inject_shortcode', $data);

        $data = absint( sanitize_text_field( $_POST['nc_inject_excerpt_length'] ) );
        update_post_meta($post_ID, '_nc_inject_excerpt_length', $data);

        $data = $_POST['nc_inject_css'];
        update_post_meta($post_ID, '_nc_inject_css', $data);

        $data = $_POST['nc_inject_js'];
        update_post_meta($post_ID, '_nc_inject_js', $data);

        $data = sanitize_text_field( $_POST['nc_inject_public'] );
        update_post_meta($post_ID, '_nc_inject_public', $data);

        $this->update_script();

    }


    /**
     * Render the support metabox nc_inject_support_meta_box.
     *
     * @since 1.0
     */
    function render_support_meta_box( $post ){
        $out = array();
        $out[] = '<div class="misc-pub-section">';
        $out[] = __( 'Plugin Version: ', 'nc_inject' ) . NC_INJECT_VERSION;
        $out[] = '</div>';
        $out[] = '<div class="misc-pub-section">';
        $out[] = '<a href="' . NC_INJECT_SUPPORT_PLUGIN . '" target="_blank">' . __( 'Plugin URL', 'nc_inject' ) . '</a>';
        $out[] = '</div>';
        $out[] = '<div class="misc-pub-section misc-pub-section-donate">';
        $out[] = '<a href="' . NC_INJECT_SUPPORT_DONATE . '" target="_blank">' . __( 'Donate', 'nc_inject' ) . '</a>';
        $out[] = '</div>';
        $out[] = '<div class="misc-pub-section">';
        $out[] = '<a href="' . NC_INJECT_SUPPORT . '" target="_blank">' . __( 'Plugin Support', 'nc_inject' ) . '</a>';
        $out[] = '</div>';
        $out[] = '<div class="misc-pub-section">';
        $out[] = '<a href="' . NC_INJECT_SUPPORT_HIRE . '" target="_blank">' . __( 'Hire us to create your Inject templates', 'nc_inject' ) . '</a>';
        $out[] = '</div>';

        echo implode( "\n", $out );
    }


    /**
     * Render the follow metabox nc_inject_follow_meta_box.
     *
     * @since 1.0
     */
    function render_follow_meta_box( $post ){
        $out = array();
        $out[] = '<div>';
        $out[] = '<a href="' . NC_INJECT_FOLLOW_EASID . '" target="_blank"><img src="' . NC_INJECT_PLUGIN_URL . '/img/easid.png" alt="easID" /></a>';
        $out[] = '<a href="' . NC_INJECT_FOLLOW_TWITTER . '" target="_blank"><img src="' . NC_INJECT_PLUGIN_URL . '/img/twitter.png" alt="Twitter" /></a>';
        $out[] = '<a href="' . NC_INJECT_FOLLOW_FACEBOOK . '" target="_blank"><img src="' . NC_INJECT_PLUGIN_URL . '/img/facebook.png" alt="Facebook" /></a>';
        $out[] = '<a href="' . NC_INJECT_FOLLOW_GOOGLE . '" target="_blank"><img src="' . NC_INJECT_PLUGIN_URL . '/img/google.png" alt="Google +" /></a>';
        $out[] = '<a href="' . NC_INJECT_FOLLOW_PINTEREST . '" target="_blank"><img src="' . NC_INJECT_PLUGIN_URL . '/img/pinterest.png" alt="Pinterest" /></a>';
        $out[] = '</div>';

        echo implode( "\n", $out );
    }

    /**
     * Render the follow metabox nc_inject_follow_meta_box.
     *
     * @since 1.0
     */
    function render_news_meta_box( $post ){

        // RSS Dashboard Widget
        if ( function_exists( 'fetch_feed' ) ) {
            include_once( ABSPATH . WPINC . '/feed.php' );
        }

        $feed = fetch_feed( NC_INJECT_NEWS_URL );
        if ( is_wp_error( $feed ) ) {
            $out[] = '<div>';
            $out[] = sprintf( __( 'RSS unavailable : <a href="%s" target="_blank">Inject News</a>', 'nc_inject' ), NC_INJECT_PLUGIN_URL );
            $out[] = '<!-- ' . $feed->get_error_message() . ' -->';
            $out[] = '</div>';
        } else {
            $limit = $feed->get_item_quantity( 5 );
            $items = $feed->get_items( 0, $limit );
            $out = array();
            if ( $limit == 0 ) {
                $out[] = '<div>';
                $out[] = sprintf( __( 'RSS unavailable : <a href="%s" target="_blank">Inject News</a>', 'nc_inject' ), NC_INJECT_PLUGIN_URL );
                $out[] = '</div>';
            } else {
                foreach ($items as $item) {
                    $desc = wp_html_excerpt( $item->get_description(), 120 );
                    // $desc = strip_tags(
                    // $desc = trim( preg_replace( '/\s+/', ' ', $desc ) );
                    // $desc = ( strlen( $desc ) > 120 ) ? substr( $desc, 0, 120 ) . ' ...' : $desc;

                    $out[] = '<div class="misc-pub-section">';
                        $out[] = '<strong><a href="' . $item->get_permalink() . '" target="_blank">' . $item->get_title() . '</a></strong><br />';
                        $out[] = '<small>' . $item->get_date('j F Y') . '</small><br />';
                        $out[] = $desc;
                    $out[] = '</div>';
                    //substr($item->get_description(), 0, 200);
                }
            }
        }
        echo implode("\n", $out);

    }


    /**
     * Register and enqueue custom script and style used in back-end
     *
     * @since 1.0
     */
    public function admin_scripts( $hook ) {
        global $post;

        if ( isset($post) && $post->post_type == 'nc_inject' && ($hook == 'post.php' || $hook == 'post-new.php') ) {
            wp_register_script( 'nc-inject-ace', NC_INJECT_PLUGIN_URL . '/js/ace/ace.js' );
            wp_enqueue_script( 'nc-inject-ace' );
        }
        wp_register_script( 'nc-inject-script', NC_INJECT_PLUGIN_URL . '/js/admin.js' );
        wp_enqueue_script( 'nc-inject-script' );
        wp_register_style( 'nc-inject-styles', NC_INJECT_PLUGIN_URL . '/css/style.css' );
        wp_enqueue_style( 'nc-inject-styles' );
    }


/* ------------------------------------------------------------------------ *
 * Option Page
 * ------------------------------------------------------------------------ */

    /**
     * Initialize plugin options page
     *
     * @since 1.0
     */
    public function initialize_plugin_options() {

        register_setting( 'nc_inject_options', 'nc_inject_options', array( &$this, 'nc_inject_options_validate' ) );

        add_settings_section(
            'inject_settings_section',
            __('General Options', 'nc_inject'),
            array( &$this, 'settings_section_callback' ),
            'nc_inject_options'
        );

        add_settings_field(
            'widget_shortcode',
            __('Allow Shortcode In Widget', 'nc_inject'),
            array( &$this, 'settings_widget_shortcode_callback' ),
            'nc_inject_options',
            'inject_settings_section',
            array(
                'help' => __( 'Allow shortcode in widget areas.', 'nc_inject' )
            )
        );

        add_settings_field(
            'cache',
            __('Cache', 'nc_inject'),
            array( &$this, 'settings_cache_callback' ),
            'nc_inject_options',
            'inject_settings_section',
            array(
                'help' => __( 'Activate the caching TTL in seconds. Set to 0 for no caching.', 'nc_inject' ) .
                    __( ' <br />Inject relays on WordPress cache and is <strong>non persitent by default</strong>.' , 'nc_inject' ) .
                    __( ' <br />To use it, you have to install <a href="http://codex.wordpress.org/Class_Reference/WP_Object_Cache#Persistent_Cache_Plugins" target="_blank">a persistent cache plugin</a>.', 'nc_inject' )
            )
        );
        add_settings_field(
            'css',
            __( 'Custom CSS', 'nc_inject' ),
            array( &$this, 'settings_css_callback' ),
            'nc_inject_options',
            'inject_settings_section',
            array(
                'help' => __( 'Add custom CSS.', 'nc_inject' )
            )
        );
        add_settings_field(
            'js',
            __( 'Custom Javascript', 'nc_inject' ),
            array( &$this, 'settings_js_callback' ),
            'nc_inject_options',
            'inject_settings_section',
            array(
                'help' => __( 'Add custom Javascript.', 'nc_inject' )
            )
        );

    }

    /**
     * Display the options page
     *
     * @since 1.0
     */
    public function nc_inject_options_page() {
        $this->update_script();
        ?>
        <div class="wrap">
            <h2><?php _e( 'Inject Options', 'nc_inject' ); ?></h2>
            <form action="options.php" method="POST">
                <?php settings_fields( 'nc_inject_options' ); ?>
                <?php do_settings_sections( 'nc_inject_options' ); ?>
                <?php submit_button(); ?>
            </form>
            <?php if ( NC_INJECT_DEBUG ) { ?>
            <div>
                <?php echo __('Plugin Version: ', 'nc_inject') . NC_INJECT_VERSION ?><br />
                <?php echo __('Plugin Url: ', 'nc_inject') . NC_INJECT_PLUGIN_URL ?><br />
            </div>
            <?php } ?>
        </div>
        <?php
    }

    /**
     * Validate options
     *
     * @since 1.0
     */
    public function nc_inject_options_validate( $input ) {

        $output = get_option( 'nc_inject_options' );

        $output['cache'] = absint( $input['cache'] );
        $output['widget_shortcode'] = (absint( $input['widget_shortcode'] ) != 0);
        $output['css'] = $input['css'];
        $output['js'] = $input['js'];

        return $output;

    }

    public function settings_section_callback() {
    }

    /**
     * Display the widget_shortcode option
     *
     * @since 1.0
     */
    public function settings_widget_shortcode_callback($args) {
        $settings = (array) get_option( 'nc_inject_options' );
        $value = ( $settings['widget_shortcode'] ) ? 'checked="checked" ' : "";
        echo "<label><input type='checkbox' name='nc_inject_options[widget_shortcode]' value='1' $value/> ";
        echo $args['help'] . "</label>";
    }

    /**
     * Display the cache option
     *
     * @since 1.0
     */
    public function settings_cache_callback($args) {
        $settings = (array) get_option( 'nc_inject_options' );
        $value = esc_attr( $settings['cache'] );
        echo "<input type='text' name='nc_inject_options[cache]' value='$value' class='small-text' />";
        echo '<p>' . $args['help'] . '</p>' ;
    }

    /**
     * Display the css option
     *
     * @since 1.0
     */
    public function settings_css_callback($args) {
        $settings = (array) get_option( 'nc_inject_options' );
        $value = esc_textarea( $settings['css'] );
        echo "<textarea name='nc_inject_options[css]' rows='10' cols='50' class='large-text code'>$value</textarea>";
        echo "<p>" . $args['help'] . "</p>";
    }

    /**
     * Display the js option
     *
     * @since 1.0
     */
    public function settings_js_callback($args) {
        $settings = (array) get_option( 'nc_inject_options' );
        $value = esc_textarea( $settings['js'] );
        echo "<textarea name='nc_inject_options[js]' rows='10' cols='50' class='large-text code'>$value</textarea>";
        echo "<p>" . $args['help'] . "</p>";
    }

/*------------------------------------------------------------------------------
    Media Button : Shortcode Helper
------------------------------------------------------------------------------*/

    /**
    * Add a media button load the shortcode helper
    *
    * @since 1.0
    */
    public function add_media_buttons($editor_id = 'content') {

        // global $post;
        $post_id = 0;

        $out = '<a href="' . add_query_arg( array( 'action' => 'nc_inject_shortcode', 'id' => $post_id ), admin_url( 'admin-ajax.php' ) ) .'" ';
        $out .= 'id="add_nc_inject_shortcode" class="add_inject button" data-editor="' . esc_attr( $editor_id ) . '" title="' . esc_attr( __( 'Add inject shortcode', 'nc_inject' ) ) . '" ';
        $out .= 'alt="' . __( 'inject', 'nc_inject' ) . '" >';
        $out .= '<span class="wp-media-buttons-icon"></span>' . __( 'Add Inject', 'nc_inject' );
        $out .= '</a>';

        printf("%s", $out);
    }

    /**
    * Generate code use in the shortcode helper. This function is call by
    * an ajax request.
    *
    * @since 1.0
    */
    public function get_shortcode_helper() {

        $out = '';

        $fields_list = array(
            'template' => array(
                'label' => __( 'Template', 'nc_inject' ),
                'type' => 'select',
                'fields' => array(),
            ),
            'debug' => array(
                'label' => __( 'Debug', 'nc_inject' ),
                'type' => 'content',
                'content' => '<input type="checkbox" id="ish_debug" name="debug" value="1" />',
            ),
            'cache' => array(
                'label' => __( 'Cache', 'nc_inject' ),
                'type' => 'content',
                'content' => '<input type="text" id="ish_cache" class="small-text" name="cache" value="" /> <em>' . __( 'in second (0: no cache)', 'nc_inject' ) . '</em>'
            ),
            'type' => array(
                'label' => __( 'Type', 'nc_inject' ),
                'type' => 'select',
                'fields' => array(
                    '' => __( 'Any', 'nc_inject' ),
                    'post' => __( 'Post', 'nc_inject' ),
                    'page' => __( 'Page', 'nc_inject' ),
                    //'attachment' => __( 'Attachment', 'nc_inject' ),
                ),
            ),
            'status' => array(
                'label' => __( 'Status', 'nc_inject' ),
                'type' => 'select',
                'fields' => array(
                    'publish' => __( 'Publish', 'nc_inject' ),
                    'pending' => __( 'Pending', 'nc_inject' ),
                    'draft' => __( 'Draft', 'nc_inject' ),
                    'auto' => __( 'Auto', 'nc_inject' ),
                    'future' => __( 'Future', 'nc_inject' ),
                    'private' => __( 'Private', 'nc_inject' ),
                    'trash' => __( 'Trash', 'nc_inject' ),
                    'any' => __( 'Any', 'nc_inject' ),
                ),
            ),
            // for posts type
            'sticky' => array(
                'label' => __( 'Sticky', 'nc_inject' ),
                'type' => 'select',
                'fields' => array(
                    '0' => __( 'Any', 'nc_inject' ),
                    '1' => __( 'Only sticky posts', 'nc_inject' ),
                    '-1' => __( 'No sticky posts', 'nc_inject' ),
                 ),
            ),
            'category' => array(
                'label' => __( 'Categories', 'nc_inject' ),
                'type' => 'content',
                'content' => wp_dropdown_categories( 'id=ish_category&name=ish_category&hierarchical=1&echo=0' ) . '
                    <button id="ish_category_include" class="button">' . __('include', 'nc_inject') . '</button>
                    <button id="ish_category_exclude" class="button">' . __('exclude', 'nc_inject') . '</button>
                    <div id="ish_category_list"></div>'
            ),
            'tag' => array(
                'label' => __( 'Tags', 'nc_inject' ),
                'type' => 'content',
                'content' => wp_dropdown_categories( 'id=ish_tag&name=ish_tag&taxonomy=post_tag&hierarchical=1&echo=0' ) . '
                    <button id="ish_tag_include" class="button">' . __('include', 'nc_inject') . '</button>
                    <button id="ish_tag_exclude" class="button">' . __('exclude', 'nc_inject') . '</button>
                    <div id="ish_tag_list"></div>'
            ),
            // pages
            'page' => array(
                'label' => __( 'Parent page', 'nc_inject' ),
                'type' => 'content',
                'content' => ''
            ),
            // attachements
            // 'attachment' => array(
            //     'label' => __( 'Attachments', 'nc_inject' ),
            //     'type' => 'select',
            //     'fields' => array(
            //         'attachments' => __( 'All', 'nc_inject' ),
            //         'video' => __( 'Video', 'nc_inject' ),
            //         'audio' => __( 'Audio', 'nc_inject' ),
            //         'application/pdf' => __( 'PDF', 'nc_inject' ),
            //         'application/zip' => __( 'ZIP', 'nc_inject' ),
            //     ),
            // ),
            'number' => array(
                'label' => __( 'Number', 'nc_inject' ),
                'type' => 'content',
                'content' => '<input type="text" id="ish_number" class="small-text" name="number" value="" /> <em>' . __( 'number of posts (-1: no limits, default: 5)', 'nc_inject' ) . '</em>'
            ),
            'order' => array(
                'label' => __( 'Order', 'nc_inject' ),
                'type' => 'content',
                'content' => '<select id="ish_orderby" name="order_orderby">
                    <option value="ID">' .__( 'ID', 'nc_inject' ) . '</option>
                    <option value="author">' .__( 'Author', 'nc_inject' ) . '</option>
                    <option value="title">' .__( 'Title', 'nc_inject' ) . '</option>
                    <option value="name">' .__( 'Name', 'nc_inject' ) . '</option>
                    <option value="date">' .__( 'Date', 'nc_inject' ) . '</option>
                    <option value="modified">' .__( 'Modified', 'nc_inject' ) . '</option>
                    <option value="parent">' .__( 'Parent', 'nc_inject' ) . '</option>
                    <option value="rand">' .__( 'Random', 'nc_inject' ) . '</option>
                    <option value="comment_count">' .__( 'Comment Count', 'nc_inject' ) . '</option>
                    <option value="menu_order">' .__( 'Menu Order', 'nc_inject' ) . '</option>
                    </select>
                    <select id="ish_order" name="order_order">
                        <option value="ASC">' .__( 'Ascending', 'nc_inject' ) . '</option>
                        <option value="DESC">' .__( 'Descending', 'nc_inject' ) . '</option>
                    </select>
                '
            ),

        );

        // Load Pages - Could be better :-/
        $pages = wp_dropdown_pages( array(
            'id' => 'ish_page',
            'name' => 'ish_page',
            'echo' => '0',
            'show_option_none' => 'ALLPAGES',
            'option_none_value' => '-1'
        ) );
        $pages = str_replace( 'ALLPAGES',
            __( '-- All Pages', 'nc_inject' ) . '</option>' . "\n" . '<option value="0">' . __( '-- Top Level', 'nc_inject' ),
            $pages );
        $fields_list['page']['content'] =$pages;

        // Load template
        $templates = get_posts( array( 'posts_per_page' => -1, 'post_type' => 'nc_inject', 'order' => 'ASC' ) );
        $admin = current_user_can( 'manage_options' );
        foreach( $templates as $template ) {
            $title = $template->post_title;
            $title .= ( get_post_meta( $template->ID, '_nc_inject_solid', true ) ) ? ' (s)' : '';
            // public template ?
            if ( ! $admin ) {
                $public = get_post_meta( $template->ID, '_nc_inject_public', true );
                $public = ( $public === '' || $public == "1" );
                if ( ! $public ) continue;
            }
            $fields_list['template']['fields'][ get_post_meta( $template->ID, '_nc_inject_id', true ) ] = $title;
        }

        // add custom post type
        $args=array(
          'public'   => true,
          '_builtin' => false
        );
        $post_types = get_post_types( $args,'names', 'and' );
        foreach ( $post_types as $name => $value ) {
             $fields_list['type']['fields'][ $name ] = $value;
        };

        // general
        $out = '<div class="wrap ish"><table>';
        foreach ($fields_list as $element => $element_data) {
            $out .= '
                <!-- ' . $element . ' -->
                <tr id="ish_row_' . $element . '" class="ish_row">
                    <th scope="row">
                        <label for="ish_' . $element . '">' . $element_data['label'] . '</label>
                    </th>';
            $out .= "
                <td>";
            switch ( $element_data['type'] ) {
                case "content":
                    $out .= $element_data['content'];
                    break;
                case "select":
                    $out .= '<select id="ish_' . $element . '">';
                    foreach ($element_data['fields'] as $id => $value) {
                        $out .= "\n".'<option value="' . esc_attr( $id ) . '">' . esc_html( $value ) . '</option>';
                    }
                    $out .= '</select>';
                    break;
                default:
                    break;
            }
            $out .= "
                </td>
            </tr>";
        }

        $out .= '
            <tr>
                <td colspan="2">
                    <div>
                        <span id="ish_reset" class="button">' . __( 'Close', 'nc_inject' ) . '</span>
                        <span id="ish_insert" class="button button-primary">' . __( 'Insert', 'nc_inject' ) . '</span>
                    </div>
                </td>
            </tr>
        </table>
        </div>
<script>
jQuery(".ish").injectHelper();
</script>
        ';

        echo $out;

        die();

    }



/* ------------------------------------------------------------------------ *
 * Front-end
 * ------------------------------------------------------------------------ */

    /**
    * insert extra css and/or js in header
    *
    * Not use anymore, replaced by enqueue_script
    *
    * @since 1.0
    */
    function wp_head(){

        $settings = (array) get_option( 'nc_inject_options' );
        $data = $settings['css'];
        echo("<!-- inject -->\n");
        if( $data ){
            echo( "<style type='text/css'>\n" );
            echo( $data );
            echo( "\n</style>\n" );
        }
        $data = $settings['js'];
        if( $data ){
            echo( "<script type='text/javascript'>\n" );
            echo( $data );
            echo( "\n</script>\n" );
        }
        echo("<!-- //inject -->\n");

    }

    /**
     * The heart of the plugin. This function is call from the shortcode
     * [inject ...] it render the template and retrun the generated code.
     *
     * @param    array    $args         values from the shortcode passed to this routine
     * @param    string   $template     the content of an inline template
     * @return   string   the rendered template
     * @since 1.0
     */
    function do_shortcode($args, $template = '') {

        global $post;

        $log = array();

        $log[] = '[DEBUG] shortcode on posts [' . $post->ID . '] args : ' . print_r( $args, true );
        // no default args
        $default_args = array( );
        $r = array_merge( $default_args, $args );



        $log[] = '[DEBUG] shortcode args : ' . print_r( $r, true );
        // get the template
        if ($template == ''){
            if ( ! array_key_exists( 'id', $r) )
                return;
            // template in post meta ?
            $template = get_post_meta($post->ID, $r['id'], true);
            if ($template == '') {
                // search in the nc_inject post type
                $posts_template = get_posts( array(
                    'posts_per_page'  => 1,
                    'post_type' => 'nc_inject',
                    'meta_key' => '_nc_inject_id',
                    'meta_value' => $r['id']
                ) );
                if( $posts_template ) {
                    $template_object = $posts_template[0];
                    if ( ! array_key_exists( 'solid', $r ) )
                        $r['solid'] = get_post_meta( $posts_template[0]->ID, '_nc_inject_solid', true );
                    if ( ! array_key_exists( 'debug', $r ) )
                        $r['debug'] = get_post_meta( $posts_template[0]->ID, '_nc_inject_debug', true );
                    if ( ! array_key_exists( 'fields', $r ) )
                        $r['fields'] = get_post_meta( $posts_template[0]->ID, '_nc_inject_fields', true );
                    if ( ! array_key_exists( 'wpautop', $r ) )
                        $r['wpautop'] = get_post_meta( $posts_template[0]->ID, '_nc_inject_wpautop', true );
                    if ( ! array_key_exists( 'shortcode', $r ) )
                        $r['shortcode'] = get_post_meta( $posts_template[0]->ID, '_nc_inject_shortcode', true );
                    if ( ! array_key_exists( 'excerpt_length', $r ) )
                        $r['excerpt_length'] = get_post_meta( $posts_template[0]->ID, '_nc_inject_excerpt_length', true );
                    $r['extra_fields'] = get_post_meta( $posts_template[0]->ID, '_nc_inject_extra_fields', true );
                    $template = stripslashes( get_post_meta( $posts_template[0]->ID, '_nc_inject_template', true ) );
                    $log[] = '[DEBUG] use template "' . $r['id'] . '"' ;
                } else {
                    // TODO: default template or nothing
                    $log[] = '[ERROR] Template "' . $r['id'] . '" not found.';
                    $template = '';
                    return '';
                }
            } else {
                $log[] = '[DEBUG] use inline template';
            }
        }

        // sanitize args
        $r['solid'] = (bool) $this->get_key( 'solid', $r, 0 );
        $r['debug'] = (bool) $this->get_key( 'debug', $r, 0 );
        $r['cache'] = absint( $this->get_key( 'cache', $r, 0 ) );
        $r['wpautop'] = (bool) $this->get_key( 'wpautop', $r, 1 );
        $r['shortcode'] = (bool) $this->get_key( 'shortcode', $r, 1 );
        $r['excerpt_length'] = absint( $this->get_key( 'excerpt_length', $r, 150 ) );
        $r['fields'] = $this->get_key( 'fields', $r, '' );
        if ( $r['fields'] == '' and ! $r['solid'] ) $r['fields'] = NC_INJECT_DEFAULT_FIELDS;
        if ( $this->get_key( 'extra_fields', $r, '') != '' )
            $r['fields'] .= ',' . $r['extra_fields'];
        $fields_list = $this->sanitize_fields_list( $r['fields'] );
        // try to auto discover
        $this->auto_discover( $r );
        // parse custom var
        $custom_vars = $this->parse_variables_args( $r );

        $log[] = '[DEBUG] template "' . $r['id'] . '" (solid: ' . (int) $r['solid'] . ', debug: ' . (int) $r['debug'] . ', cache: ' . $r['cache'] .
                 ', wpautop: ' . (int) $r['wpautop'] . ', shortcode: ' . (int) $r['shortcode'] . ', excerpt length: ' . $r['excerpt_length'] . ') ';
        $log[] = '[DEBUG] fields: ' . $r['fields'];
        $log[] = '[DEBUG] variables: ' . print_r( $custom_vars, true );


        // solid template
        if ( $r['solid'] ){
            $log[] = '[DEBUG] solid template';
        }

        // cache
        $cache_key = '';
        if ( $r['cache'] > 0 ){
            if ( $r['solid'] )
                $r['current_post_id'] = $post->ID;
            if ( is_user_logged_in() )
                $r['current_user_id'] = get_current_user_id();
            $cache_key = md5( serialize( $r ) . serialize( $custom_vars ) );
            $cache_content = wp_cache_get( $cache_key, 'nc_inject' );
            if ( $cache_content !== false ) {
                $log[] = '[DEBUG] return cache for "' . $cache_key . '"';
                self::log( $log );
                return $cache_content;
            } else {
                $log[] = '[DEBUG] no cache for "' . $cache_key . '"';
            }
        }

        // backup current post
        $tmp_post = $post;

        $posts = array();
        if ( $r['solid'] ){
            $log[] = '[DEBUG] solid template : get current post with id ' . $post->ID;
            $posts[] = $post;
        } else {
            $query_args = $this->parse_query_args($r);
            $log[] = '[DEBUG] get_posts args : ' . print_r( $query_args, true );
            $posts = get_posts($query_args);
        }

        // Load Twig
        if ( ! class_exists( 'Twig_Autoloader', false ) )
            include_once(dirname (__FILE__) . '/Twig/Autoloader.php');
        Twig_Autoloader::register();
        $loader = new Twig_Loader_String();
        // Twig configuration
        $twig_options = array();
        $twig_options['debug'] = $r['debug'];
        $twig_options['strict_variables'] = $twig_options['debug'];

        $twig = new Twig_Environment($loader, $twig_options);

        $stop = false;
        try {
            $template = $twig->loadTemplate($template);
        } catch (Twig_Error $e) {
            $log[] = '[ERROR] Twig loadTemplate (Twig_Error) : ' . $e->getMessage();
            $stop = true;
        } catch (Exception $e) {
            $log[] = '[ERROR] Twig loadTemplate: ' . $e->getMessage();
            $stop = true;
        }

        $i = 0;
        $out = "";

        if ( ! $stop ) {

            $data = array();
            foreach( $posts as $inject_post ) {
                $log[] = '[DEBUG] current post ' . $inject_post->ID;
                if ( ! $r['solid'] ){
                    setup_postdata($inject_post);
                    $post = $inject_post;
                } else {
                    // we don't need this fields in a solid template
                    unset( $fields_list['content'] );
                    unset( $fields_list['excerpt'] );
                }
                $post_data = array();
                $post_id = $inject_post->ID;
                foreach ( $fields_list as $afield ) {
                    $field = $this->map_field( $afield );
                    $value = '';
                    switch ( $field['type'] ) {
                        case "object":
                            // see : http://codex.wordpress.org/Function_Reference/get_post#Return
                            $value = $inject_post;
                            break;

                        case "id":
                            $value = $post_id; break;

                        case "date":
                            $value = get_the_date(); break;

                        case "sticky":
                            $value = is_sticky( $post_id ); break;

                        case "author":
                            $value = array();
                            $value['login'] = get_the_author_meta( 'user_login', $inject_post->post_author );
                            $value['nicename'] = get_the_author_meta( 'user_nicename', $inject_post->post_author );
                            $value['email'] = get_the_author_meta( 'user_email', $inject_post->post_author );
                            // add ?s=32 for custom size
                            $value['avatar'] = 'http://www.gravatar.com/avatar/' . md5($value['email']);
                            $value['url'] = get_the_author_meta( 'user_url', $inject_post->post_author );
                            $value['display_name'] = get_the_author_meta( 'display_name', $inject_post->post_author );
                            $value['nickname'] = get_the_author_meta( 'nickname', $inject_post->post_author );
                            $value['first_name'] = get_the_author_meta( 'first_name', $inject_post->post_author );
                            $value['last_name'] = get_the_author_meta( 'last_name', $inject_post->post_author );
                            $value['description'] = get_the_author_meta( 'description', $inject_post->post_author );
                            $value['link'] = get_the_author_link();
                            $value['posts_url'] = get_author_posts_url($inject_post->post_author );

                            break;

                        case "title":
                            $value = get_the_title( $post_id ); break;

                        case "content":
                            $value = get_the_content();
                            if ( $r['wpautop'] )
                                $value = wpautop( $value, true );
                            if ( $r['shortcode'] )
                                $value = do_shortcode( $value );
                            break;

                        case "excerpt":
                            // can't use get_the_excerpt, conflict with some other plugins :(
                            $value = $inject_post->post_excerpt;
                            if ( empty( $value ) ) {
                                $value = $inject_post->post_content;
                                if ( $r['shortcode'] )
                                    $value = do_shortcode( $value );
                                $value = $this->clean_content( $value );
                            }
                            if ( $r['excerpt_length'] > 0 )
                                if ( strlen( $value ) > $r['excerpt_length'] )
                                    $value = substr( $value, 0, $r['excerpt_length'] ) . ' ...';
                            break;

                        case "permalink":
                            $value = get_permalink(); break;

                        case "status":
                            $value = get_post_status(); break;

                        case "type":
                            $value = get_post_type(); break;

                        case "featured":
                            if ( has_post_thumbnail() ) {
                                $image_src = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), ( $field['arg'] != '' ) ? $field['arg'] : 'thumbnail' );
                                if ( $image_src ){
                                    $value = array(
                                        'src' => $image_src[0],
                                        'width' => $image_src[1],
                                        'height' => $image_src[2]
                                    );
                                }
                            }
                            if ( $value == '' )
                                continue 2;
                            break;

                        case "attachments":
                            $files = array();
                            $files_args = array( 'post_parent' => $post_id, 'post_type' => 'attachment', 'order'=> 'ASC' );
                            if ( $field['arg'] ) $files_args['post_mime_type'] = str_replace( '_', '/', $field['arg'] );
                            if ( $attachments = get_children( $files_args ) ){
                                foreach( $attachments as $attachment ) {
                                    $src = wp_get_attachment_url( $attachment->ID );
                                    // type
                                    $file_type = ( preg_match( '/^.*?\.(\w+)$/', $src, $matches ) )
                                        ? $matches[1]
                                        : str_replace( 'image/', '', $post->post_mime_type );
                                    $files[] = array(
                                        'id' => $attachment->ID,
                                        'src' => $src,
                                        'title' => $attachment->post_title,
                                        'excerpt' => $attachment->post_excerpt,
                                        'description' => $attachment->post_content,
                                        'mime_type' => $attachment->post_mime_type,
                                        'type' => $file_type
                                    );
                                }
                            }
                            $value = $files;
                            break;

                        case "images":
                            $files = array();
                            $files_args = array( 'post_parent' => $post_id, 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order'=> 'ASC' );
                            if ( $images = get_children( $files_args ) ){
                                foreach( $images as $image ) {
                                    $image_src = wp_get_attachment_image_src( $image->ID, ( $field['arg'] != '' ) ? $field['arg'] : 'thumbnail' );
                                    if ( $image_src ){
                                        $files[] = array(
                                            'id' => $image->ID,
                                            'src' => $image_src[0],
                                            'link' => wp_get_attachment_url( $image->ID ),
                                            'width' => $image_src[1],
                                            'height' => $image_src[2],
                                            'title' => $image->post_title,
                                            'alt' => get_post_meta( $image->ID, '_wp_attachment_image_alt', true ),
                                            'caption' => $image->post_excerpt,
                                            'description' => $image->post_content,
                                            'href' => get_permalink( $image->ID )
                                        );
                                    }
                                }
                            }
                            $value = $files;
                            break;

                        case "metas":
                            // all meta
                            $metas = get_post_meta( $post_id, '', true );
                            foreach ( $metas as $key => $meta ) {
                                $value[ $key ] = $meta[0];
                            }
                            break;

                        case "meta":
                            $value = get_post_meta( $post_id, $field[ 'arg'] , true );
                            break;

                        case "terms":
                            $tax_terms = wp_get_object_terms( $post_id, $field['arg'] );
                            $terms = array();
                            if ( $tax_terms && ! is_wp_error( $tax_terms ) ) {
                                foreach ( $tax_terms as $term ) {
                                    $term = (array) $term;
                                    $term['id'] = $term['term_id'];
                                    $term['link'] = get_term_link( $term['slug'], $field['arg'] );
                                    $terms[] = $term;
                                }
                            }
                            $value = $terms;
                            break;
                        default:
                            $log[] = '[WARN] Unknow field : ' . $field['name'];
                    }
                    $post_data[ $field['name'] ] = $value;
                }
                $post_data["count"] = $i;
                $i++;
                $data[] = $post_data;
            }
            wp_reset_postdata();

            // Global variables
            $global_data = array();
            $global_data['name'] = get_bloginfo( 'name' );
            $global_data['site_url'] = site_url();
            $global_data['home_url'] = home_url();
            $global_data['stylesheet_url'] = get_stylesheet_uri();
            $global_data['stylesheet_directory'] = get_stylesheet_directory_uri();
            $global_data['template_directory'] = get_template_directory_uri();
            $log[] = '[DEBUG] data ' . print_r($data, true);

            try {
                $variables = array(
                    "globals" => $global_data,
                    "posts" => $data,
                    "vars" => $custom_vars
                );
                if ( $r['solid'] ){
                    $variables['post'] = ( count( $data ) > 0 ) ? $data[0] : false;
                }
                // Render the template
                $out = $template->render( $variables );
                if ( $r['cache'] ){
                    $log[] = '[DEBUG] set cache : ' . $cache_key . ' for ' . $r['cache'] . ' seconds';
                    wp_cache_set( $cache_key, $out, 'nc_inject', $r['cache'] );
                }
            } catch (Twig_Error $e) {
                $log[] = '[ERROR] Twig render (Twig_Error) : ' . $e->getMessage();
            } catch (Exception $e) {
                $log[] = '[ERROR] Twig render: ' . $e->getMessage();
            }

        }

        if ( $r['debug'] ){
            // concat
            $out .= '<pre>' . esc_html( implode( "\n", $log ) ). '</pre>';
        }

        // restore current post
        $post = $tmp_post;
        $log[] = '[DEBUG] shortcode on posts [' . $post->ID . '] finished';

        self::log( $log );

        return $out;
    }



/* ------------------------------------------------------------------------ *
 * CSS & JS
 * ------------------------------------------------------------------------ */


    /**
     * Register and enqueue custom script and style
     *
     * @since 1.1
     */
    public function enqueue_scripts( $hook ) {

        $uploads = wp_upload_dir();
        $uploads_dir = $uploads['basedir'];
        $uploads_url = $uploads['baseurl'];
        $version = get_option( 'nc_inject_updated' );

        self::log( "enqueue_scripts: " . print_r( $version, true ) );

        // css
        if ( file_exists( trailingslashit( $uploads_dir ) . NC_INJECT_CSS_FILE ) ){
            wp_register_style( 'nc-inject-styles', trailingslashit( $uploads_url ) . NC_INJECT_CSS_FILE, array(), $version );
            wp_enqueue_style( 'nc-inject-styles' );
        }
        if ( file_exists( trailingslashit( $uploads_dir ) . NC_INJECT_JS_FILE ) ){
            wp_register_script( 'nc-inject-script', trailingslashit( $uploads_url ) . NC_INJECT_JS_FILE, array(), $version );
            wp_enqueue_script( 'nc-inject-script' );
        }

    }

    /**
     * Update custom script and style used in template
     *
     * @since 1.1
     */
    protected function update_script() {

        $uploads = wp_upload_dir();
        $uploads_dir = $uploads['basedir'];

        $settings = (array) get_option( 'nc_inject_options' );

        $css = array();
        $js = array();

        // globals
        if ( ! empty( $settings['css'] ) )
            $css[] = $settings['css'];
        if ( ! empty( $settings['js'] ) )
            $js[] = $settings['js'];

        // templates
        $templates = get_posts( array( 'posts_per_page' => -1, 'post_type' => 'nc_inject', 'order' => 'ASC' ) );
        foreach( $templates as $template ) {
            $template_css = get_post_meta( $template->ID, '_nc_inject_css', true );
            $template_js = get_post_meta( $template->ID, '_nc_inject_js', true );
            $template_id = get_post_meta( $template->ID, '_nc_inject_id', true );

            if ( $template_css != '' ) {
                $css[] = '/* template: ' . $template_id . ' */';
                $css[] = $template_css;
            }

            if ( $template_js != '' ) {
                $js[] = '/* template: ' . $template_id . ' */';
                $js[] = $template_js;
            }

        }

        // save
        $updated = strtotime( current_time( 'mysql' ) );
        delete_option( 'nc_inject_updated' );
        add_option( 'nc_inject_updated', $updated, '', 'no' );

        // saving new file in upload dir
        if ( count( $css ) > 0 ) {
            file_put_contents( trailingslashit( $uploads_dir ) . NC_INJECT_CSS_FILE, implode( "\n", $css ) );
        } else {
            if ( file_exists( trailingslashit( $uploads_dir ) . NC_INJECT_CSS_FILE ) )
                unlink( trailingslashit( $uploads_dir ) . NC_INJECT_CSS_FILE );
        }
        if ( count( $js ) > 0 ) {
            file_put_contents( trailingslashit( $uploads_dir ) . NC_INJECT_JS_FILE, implode( "\n", $js ) );
        } else {
            if ( file_exists( trailingslashit( $uploads_dir ) . NC_INJECT_JS_FILE ) )
                unlink( trailingslashit( $uploads_dir ) . NC_INJECT_JS_FILE );
        }

    }

/* ------------------------------------------------------------------------ *
 * Miscelanius
 * ------------------------------------------------------------------------ */

    /**
     * Print a log message.
     *
     * @param   string/array    $message The message(s) to print
     * @return  void
     * @see error_log
     * @since 1.0
     */
    public static function log( $message ) {
        if ( NC_INJECT_DEBUG ) {
            if (is_array($message)) {
                foreach ($message as $m) {
                    error_log( '[' . NC_INJECT_DOMAIN . '] ' . $m );
                }
            } else {
                error_log( '[' . NC_INJECT_DOMAIN . '] ' . $message );
            }
        }
    }


    /**
     * get the value of an array associated to a key. If the key is not found
     * then $else is return.
     *
     * @param   string    $key the key
     * @param   array     $search the array in which we search the key
     * @param   mixed     $else the value return if not found
     * @return  mixed     the value if found, $else or null if not found
     * @since 1.0
     */
    protected function get_key( $key, $search, $else=null ){
        return ( array_key_exists( $key, $search ) ? $search[ $key ] : $else );
    }


    /**
     * clean the content to use as excerpt
     *
     * - cut before more
     * - strip tags
     * - strip shortcodes, but keep the content
     * - remove duplicate spaces
     *
     * @param   string    $content  the value to clean
     * @return  string    the content cleaned
     */
    protected function clean_content( $content ) {

        if ( empty( $content ) )
            return '';

        $out = $content;
        // cut before more
        $pos = strpos( $out, '<!--more-->' );
        if ( $pos !== false ) {
            $out = substr( $out, 0, $pos );
        }
        $out = strip_tags( $out );
        $out = $this->strip_shortcodes( $out );
        $out = trim( preg_replace( '/\s+/', ' ', $out ) );
        return $out;
    }

    /**
     * remove shortcode tags but keep the content inside.
     * keep shortcode if they are escape [[shortcode]]
     * only replace registered shortcode
     *
     * @param   string    $content  the value to clean
     * @return  string    the content cleaned
     */
    function strip_shortcodes( $content ) {
        global $shortcode_tags;

        if (empty($shortcode_tags) || !is_array($shortcode_tags))
            return $content;

        $tagnames = array_keys($shortcode_tags);
        $tagregexp = join( '|', array_map('preg_quote', $tagnames) );

        $pattern = '\\['
            . '(\\[?)'
            . '\\/?'
            . "(?:$tagregexp)"
            //. '(?![\\w-])'
            . '(?:\s[^\\]\\/]*)?'
            . '\\/?'
            . '\\]'
            . '(\\]?)';
        return preg_replace_callback( "/$pattern/s", array( $this, 'strip_shortcode_tag' ), $content );
    }

    function strip_shortcode_tag( $m ) {
            // allow [[foo]] syntax for escaping a tag
            if ( $m[1] == '[' && $m[2] == ']' ) {
                return substr($m[0], 1, -1);
            }
            return ' ';
    }

    /**
     * Sanitize the fields list, remove extra spaces and duplicate seperator characters
     *
     * @since 1.0
     */
    protected function sanitize_fields_list( $value ) {
        $data = preg_replace( array( '/\s+/', '/,+/', '/^,/', '/,$/' ), array( '', ',', '', ''), $value);
        return explode( ",", $data );
    }


    /**
    * Display the toolbar for the ace editor
    *
    * @since 1.0
    */
    protected function get_template_toolbar() {

        $out = __( 'Insert: ', 'nc_include' );
        $out .= '<a href="#tags" id="itt_twig_tags" class="button button-small">' . __( 'tags', 'nc_include' ) . '</a>';
        $out .= ' <a href="#filters" id="itt_twig_filters" class="button button-small">' . __( 'filters', 'nc_include' ) . '</a>';
        $out .= ' <a href="#functions" id="itt_twig_functions" class="button button-small">' . __( 'functions', 'nc_include' ) . '</a>';
        $out .= ' <a href="#variables" id="itt_inject_variables" class="button button-primary button-small">' . __( 'variables', 'nc_include' ) . '</a>';
        $out .= ' <a href="#examples" id="itt_inject_examples" class="button button-primary button-small">' . __( 'examples', 'nc_include' ) . '</a>';

        $twig_url = 'http://twig.sensiolabs.org';

        $config = array(
            "tags" => array(
                array( 'autoescape', "{% autoescape %}\n[sel]\n{% endautoescape %}", $twig_url . '/doc/tags/autoescape.html' ),
                array( 'do', "{% do  %}", $twig_url . '/doc/tags/do.html' ),
                array( 'filter', "{% filter  %}\n[sel]\n{% endfilter %}", $twig_url . '/doc/tags/filter.html' ),
                array( 'for', "{% for  in  %}\n[sel]\n{% else %}\n{% endfor %}", $twig_url . '/doc/tags/for.html' ),
                array( 'if', "{% if  %}\n[sel]\n{% elseif  %}\n{% else %}\n{% endif %}", $twig_url . '/doc/tags/if.html' ),
                array( 'set', "{% set  %}", $twig_url . '/doc/tags/set.html' ),
                array( 'spaceless', "{% spaceless %}\n[sel]\n{% endspaceless %}", $twig_url . '/doc/tags/spaceless.html' ),
                array( 'verbatim', "{% verbatim %}\n[sel]\n{% endverbatim %}", $twig_url . '/doc/tags/verbatim.html' ),
            ),
            "filters" => array(
                array( 'abs', '|abs', $twig_url . '/doc/filters/abs.html' ),
                array( 'capitalize', '|capitalize', $twig_url . '/doc/filters/capitalize.html' ),
                array( 'convert_encoding', '|convert_encoding', $twig_url . '/doc/filters/convert_encoding.html' ),
                array( 'date', '|date', $twig_url . '/doc/filters/date.html' ),
                array( 'date_modify', '|date_modify', $twig_url . '/doc/filters/date_modify.html' ),
                array( 'default', '|default', $twig_url . '/doc/filters/default.html' ),
                array( 'escape', '|escape', $twig_url . '/doc/filters/escape.html' ),
                array( 'first', '|first', $twig_url . '/doc/filters/first.html' ),
                array( 'format', '|format', $twig_url . '/doc/filters/format.html' ),
                array( 'join', '|join', $twig_url . '/doc/filters/join.html' ),
                array( 'json_encode', '|json_encode', $twig_url . '/doc/filters/json_encode.html' ),
                array( 'keys', '|keys', $twig_url . '/doc/filters/keys.html' ),
                array( 'last', '|last', $twig_url . '/doc/filters/last.html' ),
                array( 'length', '|length', $twig_url . '/doc/filters/length.html' ),
                array( 'lower', '|lower', $twig_url . '/doc/filters/lower.html' ),
                array( 'merge', '|merge', $twig_url . '/doc/filters/merge.html' ),
                array( 'nl2br', '|nl2br', $twig_url . '/doc/filters/nl2br.html' ),
                array( 'number_format', '|number_format', $twig_url . '/doc/filters/number_format.html' ),
                array( 'raw', '|raw', $twig_url . '/doc/filters/raw.html' ),
                array( 'replace', '|replace', $twig_url . '/doc/filters/replace.html' ),
                array( 'reverse', '|reverse', $twig_url . '/doc/filters/reverse.html' ),
                array( 'slice', '|slice', $twig_url . '/doc/filters/slice.html' ),
                array( 'sort', '|sort', $twig_url . '/doc/filters/sort.html' ),
                array( 'split', '|split', $twig_url . '/doc/filters/split.html' ),
                array( 'striptags', '|striptags', $twig_url . '/doc/filters/striptags.html' ),
                array( 'title', '|title', $twig_url . '/doc/filters/title.html' ),
                array( 'trim', '|trim', $twig_url . '/doc/filters/trim.html' ),
                array( 'upper', '|upper', $twig_url . '/doc/filters/upper.html' ),
                array( 'url_encode', '|url_encode', $twig_url . '/doc/filters/url_encode.html' ),
            ),
            'functions' => array(
                array( 'constant', "constant('')", $twig_url . '/doc/functions/constant.html' ),
                array( 'cycle', "cycle(, )", $twig_url . '/doc/functions/cycle.html' ),
                array( 'date', "date()", $twig_url . '/doc/functions/date.html' ),
                array( 'dump', "dump()", $twig_url . '/doc/functions/dump.html' ),
                array( 'random', "random()", $twig_url . '/doc/functions/random.html' ),
                array( 'range', "range(,)", $twig_url . '/doc/functions/range.html' ),
            ),
            'variables' => array(
                __( 'Template Examples with variables usages', 'nc_inject' ),
                array( __( 'posts loop', 'nc_inject' ), 'posts-loop.html', NC_INJECT_PLUGIN_URL . '/templates/posts-loop.html' ),
                array( __( 'post object', 'nc_inject' ), 'post-object.html', NC_INJECT_PLUGIN_URL . '/templates/post-object.html' ),
                array( __( 'post fields', 'nc_inject' ), 'post-fields.html', NC_INJECT_PLUGIN_URL . '/templates/post-fields.html' ),
                array( __( 'post author', 'nc_inject' ), 'post-author.html', NC_INJECT_PLUGIN_URL . '/templates/post-author.html' ),
                array( __( 'post featured image', 'nc_inject' ), 'post-featured.html', NC_INJECT_PLUGIN_URL . '/templates/post-featured.html' ),
                array( __( 'post categories', 'nc_inject' ), 'post-categories.html', NC_INJECT_PLUGIN_URL . '/templates/post-categories.html' ),
                array( __( 'post tags', 'nc_inject' ), 'post-tags.html', NC_INJECT_PLUGIN_URL . '/templates/post-tags.html' ),
                array( __( 'post terms', 'nc_inject' ), 'post-terms.html', NC_INJECT_PLUGIN_URL . '/templates/post-terms.html' ),
                array( __( 'post custom fields', 'nc_inject' ), 'post-metas.html', NC_INJECT_PLUGIN_URL . '/templates/post-metas.html' ),
                array( __( 'post images', 'nc_inject' ), 'post-images.html', NC_INJECT_PLUGIN_URL . '/templates/post-images.html' ),
                array( __( 'post attachments', 'nc_inject' ), 'post-attachments.html', NC_INJECT_PLUGIN_URL . '/templates/post-attachments.html' ),
            ),
            'examples' => array(
                __( 'Ready to use examples for common task', 'nc_inject' ),
                array( __( 'posts lists', 'nc_inject' ), 'ex-posts-list.html', NC_INJECT_PLUGIN_URL . '/templates/ex-posts-list.html' ),
                array( __( 'navigation', 'nc_inject' ), 'ex-navigation.html', NC_INJECT_PLUGIN_URL . '/templates/ex-navigation.html' ),
                array( __( 'gallery', 'nc_inject' ), 'ex-gallery.html', NC_INJECT_PLUGIN_URL . '/templates/ex-gallery.html' ),
            )
        );

        $counter = 0;
        $content = array();
        foreach ( $config as $key => $value ) {
            $out .= '<div id="itt_' . $key . '" class="itt_menu">';
            foreach ( $value as $element ) {
                $counter++;
                $content[ 'itt_i_' . $counter ] = $element[1];
                if ( $key == 'variables' || $key == 'examples' ){
                    if ( is_array( $element ) )
                        $out .= '<div id="itt_i_' . $counter . '" class="itt_item itt_item_template" data-template="' . esc_attr( $element[2] ) . '">' . esc_html( $element[0] ) . '</div>';
                    else
                        $out .= '<div id="itt_i_' . $counter . '" class="itt_item_title itt_item_template">' . esc_html( $element ) . '</div>';

                } else {
                    $out .= '<div id="itt_i_' . $counter . '" class="itt_item">';
                    $out .= esc_html( $element[0] );
                    if ( trim( $element[2] ) != '' )
                        $out .= '<a href="' . $element[2] . '" target="_blank" class="help">?</a>';
                    $out .= '</div>';
                }
            }
            $out .= '</div>';
        }
        $out .= '
        <script>
        var ITT = ' . json_encode( $content ) . ';
        </script>';

        return $out;
    }


    /**
    * Display the fields selector when editing a template
    *
    * @since 1.0
    */
    protected function get_fields_form() {

        $out = '';

        $fields_list = array(
            'general' => array(
                'label' => __( 'General', 'nc_inject' ),
                'fields' => array(
                    'object' => __( 'Wordpress Object', 'nc_inject' ),
                    'id' => __( 'Id', 'nc_inject' ),
                    'type' => __( 'Type', 'nc_inject' ),
                    'status' => __( 'Status', 'nc_inject' ),
                    'date' => __( 'Date', 'nc_inject' ),
                    'permalink' => __( 'Permalink', 'nc_inject' ),
                    'author' => __( 'Author', 'nc_inject' ),
                    'metas' => __( 'Custom Fields', 'nc_inject' ),
                    'sticky' => __( 'Sticky', 'nc_inject')
                ),
            ),
            'content' => array(
                'label' => __( 'Content', 'nc_inject' ),
                'fields' => array(
                    'title' => __( 'Title', 'nc_inject' ),
                    'content' => __( 'Content', 'nc_inject' ),
                    'excerpt' => __( 'Excerpt', 'nc_inject' ),
                ),
            ),
            'featured' => array(
                'label' => __( 'Featured image', 'nc_inject' ),
                'fields' => array(
                    'featured' => __( 'Thumbnail', 'nc_inject' ),
                    'featured_medium' => __( 'Medium', 'nc_inject' ),
                    'featured_large' => __( 'Large', 'nc_inject' ),
                    'featured_full' => __( 'Full', 'nc_inject' ),
                ),
            ),
            'image' => array(
                'label' => __( 'Images', 'nc_inject' ),
                'fields' => array(
                    'images' => __( 'Thumbnail', 'nc_inject' ),
                    'images_medium' => __( 'Medium', 'nc_inject' ),
                    'images_large' => __( 'Large', 'nc_inject' ),
                    'images_full' => __( 'Full', 'nc_inject' ),
                ),
            ),
            'taxonomy' => array(
                'label' => __( 'Taxonomies', 'nc_inject' ),
                'fields' => array(
                    'categories' => __( 'Categories', 'nc_inject' ),
                    'tags' => __( 'Tags', 'nc_inject' ),
                ),
            ),
            'attachment' => array(
                'label' => __( 'Attachments', 'nc_inject' ),
                'fields' => array(
                    'attachments' => __( 'All', 'nc_inject' ),
                    'attachments_video' => __( 'Video', 'nc_inject' ),
                    'attachments_audio' => __( 'Audio', 'nc_inject' ),
                    'attachments_application_pdf' => __( 'PDF', 'nc_inject' ),
                    'attachments_application_zip' => __( 'ZIP', 'nc_inject' ),
                ),
            ),
        );

        // add custom taxonomies
        $args=array(
          'public'   => true,
          '_builtin' => false
        );
        $taxonomies = get_taxonomies( $args,'names', 'and' );
        foreach ( $taxonomies as $name => $value ) {
             $fields_list['taxonomy']['fields'][ 'terms_' . $name ] = $value;
        };

        // add custom image size
        global $_wp_additional_image_sizes;
        if ( isset( $_wp_additional_image_sizes ) && count( $_wp_additional_image_sizes ) ) {
            foreach ( array_keys( $_wp_additional_image_sizes ) as $image_size ) {
                 $fields_list['featured']['fields'][ 'featured_' . $image_size ] = $image_size;
                 $fields_list['image']['fields'][ 'image_' . $image_size ] = $image_size;
            } ;
        }

        // general
        $out = '<div id="inject_fields_helper">';
        foreach ($fields_list as $element => $element_data) {
            $out .= "\n<!-- ". $element . "-->";
            $out .= "\n".'<div id="ifh_' . $element . '">';
            $out .= "\n".'<span class="label">' . esc_html( $element_data['label'] ) . '</span>';
            $out .= "\n".'<div>';
            foreach ($element_data['fields'] as $id => $label) {
                $out .= "\n".'<span id="ifh_' . $id . '" class="field button" title="' . __( 'in template use: ', 'nc_inject' ) . esc_attr( "'" . $id . "'" ) . ' ">' . esc_html( $label ) . '</span>';
            }
            $out .= "\n".'</div>';
            $out .= "\n".'</div>';
        }

        return $out;
    }



    /**
    * sanitize the textarea for the template field.
    *
    * @since 1.0
    */
    protected function sanitize_textarea( $input, $option ) {

        global $allowedposttags;

        $my_tags = $allowedposttags; //duplicate $allowed tags

        $my_tags["iframe"] = array( //add new allowed tags
            "src"             => array(),
            "height"          => array(),
            "width"           => array(),
            "frameborder"     => array(),
            "allowfullscreen" => array()
        );

        $my_tags["object"] = array(
            "height" => array(),
            "width"  => array()
        );

        $my_tags["param"] = array(
            "name"  => array(),
            "value" => array()
        );

        $my_tags["embed"] = array(
            "src"               => array(),
            "type"              => array(),
            "allowfullscreen"   => array(),
            "allowscriptaccess" => array(),
            "height"            => array(),
            "width"             => array()
        );

        $my_tags["script"] = array(
            "src"      => array(),
            "type"     => array(),
            "language" => array(),
        );
        return wp_kses( $input, $my_tags );

    }



    /**
     * Try to complete the arguments list.
     *
     * @since 1.0
     */
    protected function auto_discover( &$r ) {

        global $post;

        // sticky post
        $sticky = $this->get_key( 'sticky', $r );
        if ( $sticky !== null ) {
            $sticky_posts = get_option( 'sticky_posts' );
            if ( count( $sticky_posts ) > 0 ) {
                if ( $sticky == '1' ) {
                    $r['post__in'] = implode( ',', $sticky_posts );
                } elseif ( $sticky == '-1' ) {
                    $r['post__not_in'] = implode( ',', $sticky_posts );
                }
            }
        }
        // pages
        if ( $this->get_key( 'post_type', $r ) == 'page' && $post->post_type == 'page' ) {
            if ( $this->get_key( 'post_parent', $r ) === null )
                $r['post_parent'] = $post->ID;
        }
        // page adjustements
        if ( $this->get_key( 'post_type', $r ) == 'page') {
            // for all pages use post-parent == -1
            if ( $this->get_key( 'post_parent', $r ) == '-1' )
                unset( $r['post_parent'] );
            // change default order
            if ( ! $this->get_key( 'orderby', $r ) ){
                $r['orderby'] = 'menu_order';
                if ( ! $this->get_key( 'order', $r ) )
                    $r['order'] = 'ASC';
            }
        }
        // attachments
        if ( $this->get_key( 'post_type', $r ) == 'attachment') {
            if ( ! $this->get_key( 'post_status', $r ) )
                $r['post_status'] = 'any';
        }

    }


    /**
     * map a field parameter for using the right switch case in the render loop
     * and get the right key to put in the twig variable.
     *
     * @param  string  $field  the field to map
     * @return array   the array corresponding to the field.
     *                 type: the loop context
     *                 name: the key used in twig post variable
     *                 arg: optional contextual argument (taxonomy, image size,
     *                      attachment type, meta name)
     * @since 1.0
     */
    protected function map_field( $field ){

        $field_arg = '';
        $field_name = $field;

        if ( strpos( $field, 'meta_' ) !== false ) {
            $field_name = $field;
            $field = 'meta';
            $field_arg = $this->extract_field_arg( $field_name, 'meta_' );
        } elseif ( $field == 'categories' ) {
            $field = 'terms';
            $field_name = 'categories';
            $field_arg = 'category';
        } elseif ( $field == 'tags' ) {
            $field = 'terms';
            $field_name = 'tags';
            $field_arg = 'post_tag';
        } elseif ( strpos( $field, 'terms_' ) !== false ) {
            $field_name = $field;
            $field = 'terms';
            $field_arg = $this->extract_field_arg( $field_name, 'terms_' );
        } elseif ( strpos( $field, 'featured_' ) !== false ) {
            $field_name = $field;
            $field = 'featured';
            // featured size
            $field_arg = $this->extract_field_arg( $field_name, 'featured_' );
        } elseif ( strpos( $field, 'attachments_' ) !== false ) {
            $field_name = $field;
            $field = 'attachments';
            // attachments type
            $field_arg = $this->extract_field_arg( $field_name, 'attachments_' );
        } elseif ( strpos( $field, 'images_' ) !== false ) {
            $field_name = $field;
            $field = 'images';
            // images size
            $field_arg = $this->extract_field_arg( $field, 'images_' );
        };

        return array(
            'type' => $field,
            'name' => $field_name,
            'arg'  => $field_arg
        );

    }


    /**
    * extract the field arg from a field name.
    *
    * @param  string  $field  the field name
    * @param  string  $arg    the argument prefixe
    * @return  string  the arg corresponding
    * @see map_field
    * @since 1.0
    */
    protected function extract_field_arg( $field, $arg ){
        $out = false;
        if ( strpos( $field, $arg ) !== false ) {
            $out = substr( $field, strlen($arg) );
        }
        return $out;
    }


    /**
    * Parse the query args from the shortcode and generate correct arguments
    * used by get_posts (WP_Query)
    *
    * @param  array  $args  the query arguments from the shortcode
    * @return  array  the query arguments for get_posts
    * @since 1.0
    */
    protected function parse_query_args( $args ){

        $query_args = array();
        $tax_query = array();
        $meta_query = array();
        $exclude_args = array(
            'id',
            'fields',
            'solid',
            'debug',
            'cache',
        );
        $need_array_args = array(
            'category__and',
            'category__in',
            'category__not_in',
            'tag__and',
            'tag__in',
            'tag__not_in',
            'tag_slug__and',
            'tag_slug__in',
            'post__in',
            'post__not_in',
        );
        //reset( $args );

        $log = array();

        foreach ( $args as $key => $value ) {
            if ( in_array( $key, $exclude_args ) ) {
                continue;
            }
            if ( in_array( $key, $need_array_args ) ) {
                if ( ! empty( $value ) ) {
                    $value = explode( ',', $value );
                    $value = array_map( 'sanitize_key', $value );
                }
            }
            // taxonomy
            if ( substr($key, 0, 5) == '_tax_' ) {
                $tax_param = explode( '|', $value );
                $tax_query_param = array();
                if ( count( $tax_param ) >= 2 ) {
                    $tax_query_param['taxonomy'] = substr( $key, 5 );
                    $tax_query_param['field'] = $tax_param[0];
                    $tax_query_param['terms'] = explode( ',', $tax_param[1] );
                    if ( count( $tax_param ) > 2 )
                        $tax_query_param['operator'] = $tax_param[3];
                    if ( count( $tax_param ) > 3 )
                        $tax_query_param['include_children'] = (bool) $tax_param[2];
                    $tax_query[] = $tax_query_param;
                } else {
                    $log[] = '[WARN] tax args ' . $key . ' malformed !';
                }
                continue;
            }
            // taxonomy relation
            if ( $key == '_taxrelation' ) {
                $tax_query['relation'] = $value;
                continue;
            }
            // meta
            if ( substr($key, 0, 6) == '_meta_' ) {
                $meta_param = explode( '|', $value );
                $meta_query_param = array();
                if ( count( $meta_param ) >= 1 ) {
                    $meta_query_param['key'] = substr( $key, 6 );
                    $meta_query_param['value'] = ( strrpos($meta_param[0], ",") === false ) ? $meta_param[0] : explode( ',', $meta_param[0] );
                    if ( count( $meta_param ) > 1 )
                        $meta_query_param['compare'] = $meta_param[1];
                    if ( count( $meta_param ) > 2 )
                        $meta_query_param['type'] = $meta_param[2];
                    $meta_query[] = $meta_query_param;
                } else {
                    $log[] = '[WARN] meta args ' . $key . ' malformed !';
                }
                continue;
            }
            // taxonomy relation
            if ( $key == '_metarelation' ) {
                $meta_query['relation'] = $value;
                continue;
            }
            // others
            $query_args[ $key ] = $value;
        }

        //self::log( $log );

        if ( count( $tax_query ) > 0 )
            $query_args[ 'tax_query' ] = $tax_query;

        if ( count( $meta_query ) > 0 )
            $query_args[ 'meta_query' ] = $meta_query;

        return $query_args;

    }

    /**
    * Parse the args from the shortcode and extract custom variables. By convention,
    * variables have to start with a double underscore : __var1="right"
    *
    * @param  array  $args  the shortcode arguments
    * @return  array  the custom variables
    * @since 1.0
    */
    protected function parse_variables_args( $args ){

        $var_args = array();

        foreach ( $args as $key => $value ) {
            if ( substr( $key, 0, 2 ) == '__' )
                $var_args[ substr( $key, 2 ) ] = $value;
        }

        return $var_args;
    }


}

/**
 * a shortcut function to call the shortcode function and render a template
 * from a theme template.
 *
 * @param   array    $args   the arguments passed to the shortcode function
 * @return  string   the rendered content
 */
function inject_render($args){
    global $inject;
    return $inject->do_shortcode( $args );
}

