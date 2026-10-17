<?php
/** Local-first assets. No remote font or tracking request. */
add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style( 'kevcoachfit', get_stylesheet_uri(), array(), filemtime( get_stylesheet_directory() . '/style.css' ) );
    wp_enqueue_script( 'kevcoachfit-effects', get_stylesheet_directory_uri() . '/theme.js', array(), filemtime( get_stylesheet_directory() . '/theme.js' ), true );
} );
add_action( 'after_setup_theme', function () {
    add_theme_support( 'editor-styles' );
    add_editor_style( 'style.css' );
} );

require_once __DIR__ . '/seo.php';

require_once __DIR__ . '/sections.php';

require_once __DIR__ . '/service-map.php';
add_action('wp_enqueue_scripts',function(){
    if(is_singular('page')&&in_array(kcf_section_key(),array('sport','protection'),true)){
        wp_enqueue_script('kevcoachfit-map',get_stylesheet_directory_uri().'/map.js',array(),filemtime(__DIR__.'/map.js'),true);
    }
});
