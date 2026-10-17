<?php
/**
 * Plugin Name: KevCoachFit — atelier
 * Description: Réservations de démonstration, listes d’attente et demandes distinctes pour les deux producteurs.
 * Version: 0.15.0
 * Requires PHP: 8.0
 * Requires at least: 6.6
 */
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/engine.php';
require_once __DIR__ . '/views.php';
require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/installer.php';

register_activation_hook( __FILE__, 'kcf_install_workflows' );
add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style( 'kcf-workflows', plugins_url( 'ui.css', __FILE__ ), array(), filemtime( __DIR__ . '/ui.css' ) );
    wp_enqueue_script( 'kcf-workflows', plugins_url( 'app.js', __FILE__ ), array(), filemtime( __DIR__ . '/app.js' ), true );
    wp_localize_script( 'kcf-workflows', 'kcfApp', array(
        'endpoint' => esc_url_raw( rest_url( 'kevcoachfit/v1/' ) ),
        'nonce' => wp_create_nonce( 'kcf_guest_form' ),
        'restNonce' => wp_create_nonce( 'wp_rest' ),
    ) );
} );
add_action( 'send_headers', function () {
    if ( is_page( array( 'reserver', 'mes-reservations', 'terroir-loupiac', 'contact' ) ) ) {
        nocache_headers();
        header( 'Referrer-Policy: no-referrer' );
    }
} );
add_action( 'rest_api_init', function () {
    foreach ( array( 'book' => 'kcf_rest_book', 'manage' => 'kcf_rest_manage', 'request' => 'kcf_rest_request' ) as $route => $callback ) {
        $args=array();
        foreach(array('name','email','phone','note','date','time','address','seller','kind','key','operation','website') as $field){$args[$field]=array('type'=>'string');}
        foreach(array('session','id') as $field){$args[$field]=array('type'=>'integer','minimum'=>1);}
        if('request'===$route){$args['items']=array('type'=>'object');}
        register_rest_route( 'kevcoachfit/v1', '/' . $route, array(
            'methods' => 'POST', 'callback' => $callback, 'permission_callback' => 'kcf_form_permission',
            'args' => $args,
        ) );
    }
} );
function kcf_form_permission( $request ) {
    $host=wp_parse_url(home_url(),PHP_URL_HOST);
    if(!in_array($host,array('localhost','127.0.0.1','::1'),true)&&!current_user_can('kcf_access')){
        return new WP_Error('demo_private','Cet atelier de démonstration est réservé aux comptes autorisés.',array('status'=>403));
    }
    if ( ! wp_verify_nonce( $request->get_header( 'X-KCF-Nonce' ), 'kcf_guest_form' ) ) {
        return new WP_Error( 'nonce', 'La page a expiré. Rechargez-la puis réessayez.', array( 'status' => 403 ) );
    }
    if ( $request->get_param( 'website' ) ) {
        return new WP_Error( 'spam', 'Cette demande ne peut pas être traitée.', array( 'status' => 400 ) );
    }
    if ( false !== strpos( $request->get_route(), '/manage' ) ) {
        return true;
    }
    $key = 'kcf_rate_' . hash_hmac( 'sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown', wp_salt() );
    $count = (int) get_transient( $key );
    if ( $count >= 60 ) {
        return new WP_Error( 'rate', 'Trop de demandes. Réessayez dans une heure.', array( 'status' => 429 ) );
    }
    set_transient( $key, $count + 1, HOUR_IN_SECONDS );
    return true;
}
function kcf_rest_book( $request ) {
    return kcf_book_session( absint( $request['session'] ), $request->get_params() );
}
function kcf_rest_manage( $request ) {
    return kcf_manage_booking( absint( $request['id'] ), (string) $request['key'], (string) $request['operation'] );
}
function kcf_rest_request( $request ) {
    return kcf_save_request( $request->get_params() );
}
