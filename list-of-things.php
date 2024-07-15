<?php
/*
 * Plugin Name: List of things
 * Description: This is a plugin to add things to your list
 * Version: 1.0.0
 * Author: Jayakrishna Sidagam
 * Author URI: https://jksidagam.com
 * Text Domain: list-of-things
 * 
 * @package lot
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit(); // Exit if accessed directly
}

class ListOfThings {
    private static $instance = null;

    private $wpdb;

    private $table_name;

    private function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $this->wpdb->prefix . 'things';
        register_activation_hook( __FILE__, array( $this, 'lot_activate' ) );
        add_action( 'init', array( $this, 'lot_init' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'lot_scripts' ) );
        add_action( 'wp_ajax_insert_data_to_lot_table', array( $this, 'insert_data_to_lot_table' ) );
        add_action( 'wp_ajax_nopriv_insert_data_to_lot_table', array( $this, 'insert_data_to_lot_table' ) );
        add_action ( 'rest_api_init', array( $this, 'lot_register_routes' ) );
    }

    public static function get_instance() {
        if(!self::$instance){
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function lot_activate() {
        $this->maybe_create_lot_table();
    }

    public function maybe_create_lot_table() {
        if( $this->wpdb->get_var( "SHOW TABLES LIKE '$this->table_name'" ) != $this->table_name ){
            $charset_collate = $this->wpdb->get_charset_collate();
            
            $lot_sql_create_query = "CREATE TABLE $this->table_name (
                id INT(20) NOT NULL AUTO_INCREMENT,
                thing TINYTEXT NOT NULL,
                PRIMARY KEY (id)
            ) $charset_collate;";

            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

            dbDelta( $lot_sql_create_query );
        }
    }
    
    public function lot_init() {
        add_shortcode( 'lot_form', array( $this, 'lot_shortcode_form' ) );
        add_shortcode( 'lot_list', array( $this, 'lot_shortcode_list') );
    }

    public function lot_scripts() {
        wp_enqueue_style( 'lot_css', plugin_dir_url(__FILE__) . 'assets/css/style.css', array(), '1.0.0' );
        wp_register_script( 'lot_js', plugin_dir_url(__FILE__) . 'assets/js/scripts.js', array('jquery'), '1.0.0' );
        wp_localize_script( 'lot_js', 'lot', array('ajaxUrl' => admin_url( 'admin-ajax.php' )) );
        wp_enqueue_script( 'lot_js' );
    }

    public function lot_shortcode_form() {
        ob_start();
        ?>
        <div class="lot_insert_form_response"></div>
        
        <form method="POST" class="lot_insert_form" action="">
            <?php wp_nonce_field( 'lot_insert_form', 'lot_insert_form_nonce' ); ?>
            <input type="text" name="lot_thing" placeholder="Enter a thing" required>
            <button type="submit">Submit</button>
        </form>
        
        <?php
        return ob_get_clean();
    }

    public function lot_shortcode_list() {
        $page = isset( $_GET['page_num'] ) ? ($_GET['page_num']) : 1;
        $total_records = $this->get_lot_table_data_count();
        $per_page = ( isset( $_GET['per_page'] ) && !empty( $_GET['per_page'] ) ) ? $_GET['per_page'] : $total_records;
        $orderby = ( isset( $_GET['orderby'] ) && !empty( $_GET['orderby'] ) ) ? $_GET['orderby'] : 'id';
        $order = ( isset( $_GET['order'] ) && !empty( $_GET['order'] ) ) ? $_GET['order'] : 'ASC';
        $search = ( isset( $_GET['search'] ) && !empty( $_GET['search'] ) ) ? $_GET['search'] : '';

        $data = $this->get_lot_table_data( $page, $per_page, $orderby, $order, $search );

        $response = '';

        $response .= '<form method="GET" class="lot_search_form" action="">';
            $response .= '<input type="text" name="search" class="search" placeholder="Search" value="'.$search.'">';
            $response .= '<button type="submit">Search</button>';
        $response .= '</form>';

        $response .= '<table class="lot_data_table">';
            $response .= '<thead>';
                $response .= '<tr>';
                    $response .= '<th>ID</th>';
                    $response .= '<th>Thing</th>';
                $response .= '</tr>';
            $response .= '</thead>';
            $response .= '<tbody>';

            if( !empty($data) ) {
                foreach ( $data as $thing ) {
                    $response .= '<tr>';
                        $response .= '<td>'.$thing->id.'</td>';
                        $response .= '<td>'.$thing->thing.'</td>';
                    $response .= '</tr>';
                }
            } else {
                $response .= '<tr>';
                    $response .= '<td colspan="2" style="text-align:center">No data found</td>';
                $response .= '</tr>';
            }

            $response .= '</tbody>';
        $response .= '</table>';

        return $response;
    }

    public function get_lot_table_data( $page = 1, $per_page = 10, $orderby = 'id', $order = 'ASC', $search = '' ) {
        $lot_sql_get_query = "SELECT * FROM " . $this->table_name;
        
        // Search query
        if( !empty( $search ) ) {
            $search = sanitize_text_field( $search );
            $lot_sql_get_query .= $this->wpdb->prepare( " WHERE thing LIKE '%$search%'" );
        }

        // Orderby query
        $orderby = sanitize_sql_orderby( $orderby );
        $order = strtoupper( $order ) === 'DESC' ? 'DESC' : 'ASC';
        $lot_sql_get_query .= " ORDER BY " . $orderby . " " . $order;

        // Pagination query
        $page = absint( $page );
        $per_page = absint( $per_page );
        $offset = ( $page - 1 ) * $per_page;
        $lot_sql_get_query .= $this->wpdb->prepare( " LIMIT %d OFFSET %d", $per_page, $offset );

        $lot_table_data = $this->wpdb->get_results( $lot_sql_get_query );

        return $lot_table_data;
    }

    private function get_lot_table_data_count() {
        $lot_data_count = $this->wpdb->get_var( "SELECT COUNT(*) FROM $this->table_name" );
        return ($lot_data_count) ? $lot_data_count : 0;
    }

    public function insert_data_to_lot_table() {
        if (isset($_POST['lot_insert_form_nonce']) && wp_verify_nonce($_POST['lot_insert_form_nonce'], 'lot_insert_form')) {
            $thing = sanitize_text_field( $_POST['lot_thing'] );

            $lot_sql_insert_query = $this->wpdb->query(
                $this->wpdb->prepare(
                    "INSERT INTO $this->table_name ( thing ) VALUES ( %s )",
                    array( $thing )
                )
            );

            if( $this->wpdb->insert_id ) {
                $response = [
                    'success'   => true,
                    'message'   => 'Your entry has been submitted successfully',
                    'data'      => array('id' => $this->wpdb->insert_id, 'thing' => $thing)
                ];
            } else {
                $response = [
                    'success'   => false,
                    'message'   => 'Sorry, there was a problem in submission, please try again',
                    'data'      => array()
                ];
            }
            
            wp_send_json($response);
            wp_die();
        }
    }

    public function lot_register_routes() {
        $version = '1';
        $namespace = 'lot/v' . $version;
        
        register_rest_route( $namespace, '/things', array(
            'methods' => 'GET',
            'callback' => array( $this, 'lot_api_get_things_data' ),
            'permission_callback' => '__return_true'
        ));

        register_rest_route( $namespace, '/things/add', array(
            'methods' => 'POST',
            'callback' => array( $this, 'lot_api_insert_things' ),
            'permission_callback' => '__return_true'
        ));
    }

    public function lot_api_get_things_data() {
        $data = $this->get_lot_table_data();

        if( !empty($data) ) {
            $response = [
                'success' => true,
                'data' => $data
            ];

            $status = 200;
        } else {
            $response = [
                'success' => false,
                'data' => []
            ];

            $status   = 404;
        }

        return new WP_REST_Response( $response, $status );
    }

    public function lot_api_insert_things( WP_REST_Request $request ) {
        $thing = sanitize_text_field( $request['lot_thing'] );

        $lot_sql_insert_query = $this->wpdb->query(
            $this->wpdb->prepare(
                "INSERT INTO $this->table_name ( thing ) VALUES ( %s )",
                array( $thing )
            )
        );

        if( $this->wpdb->insert_id ) {
            $response = [
                'success' => true,
                'message' => 'Your entry has been submitted successfully'
            ];

            $status = 200;
        } else {
            $response = [
                'success' => false,
                'message' => 'Sorry, there was a problem in submission, please try again'
            ];

            $status   = 404;
        }
            
        return new WP_REST_Response( $response, $status );
    }
}

ListOfThings::get_instance();