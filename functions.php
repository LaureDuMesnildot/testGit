<?php
//add_action('save_post', 'wext_clean_front_cache');
//add_action( 'after_setup_theme', 'wext_setup_theme');
//add_action( 'wpseo_opengraph', 'my_own_og_function', 29 );
//add_action( 'widgets_init', 'kaizen_widgets_init' );
global $wpdb, $wext_conf,$customernames;

$wext_conf['settings_pod_name']['name'] = 'reglage'; // name of the settings pod if applicable. Let empty if not.
$wext_conf['settings_pod_name']['fields'] = []; // list of identifiers of settings fields

function wext_clean_front_cache( $post_id ) {
    $files = glob(__DIR__.'/cache/*.cache');
    if($files) array_map('unlink', $files);
}
add_action('save_post', 'wext_clean_front_cache');

add_filter('pods_api_post_save_pod_item_reglage', 'wext_clean_front_cache', 10, 2);


function wext_caption($na, $attrs, $content){
    #var_dump($na);
    #var_dump($attrs);
    #var_dump($content);
    return $content.'<figcaption class="legend"><div class="small_wrap">'.$attrs['caption'].'</div></figcaption>';
}
add_filter( 'img_caption_shortcode', 'wext_caption', 999, 3);


function wext_embed($return, $url, $attr){
    var_dump($return);
    var_dump($url);
    var_dump($attr);
    return 'test';
    #return $content.'<figcaption class="legend"><div class="small_wrap">'.$attrs['caption'].'</div></figcaption>';
}
add_filter( 'embed_handler_html', 'wext_embed', 999, 3);




function get_customer_name_by_id($customer_id){
    global $ps2wp,$customernames;
    if(!is_null($customernames) && key_exists($customer_id, $customernames)) return $customernames[$customer_id];
    
    $psObjects = array('customernames');
    try {
        $ps = $ps2wp->getFromPS($psObjects, ['ids' => $customer_id]);
    }
    catch(Exception $e){
        return 'Inconnu';
    }

    $customernames = $ps['customernames'];
    return $customernames[$customer_id];
}

function wext_setup_theme(){
    #show_admin_bar(true);
    // Add your theme support ( cf :  http://codex.wordpress.org/Function_Reference/add_theme_support )
    add_theme_support( 'menus' );
    add_theme_support( 'title-tag' );
    // Register menus, use wp_nav_menu() to display menu to your template ( cf : http://codex.wordpress.org/Function_Reference/wp_nav_menu )
    register_nav_menus( array(
        'headerMenuMain' => 'Menu header principal',
        'headerMenuSecondSubscriber' => 'Menu header secondaire abonné',
        'headerMenuSecondNotSubscriber' => 'Menu header secondaire non abonné ou non connecté',
        'headerMenuRubriques' => 'Menu header rubriques',
        'footer_menu_rs' => 'Menu footer réseaux sociaux',
        'footer_menu' => 'Menu footer liens',
        'account_menu' => 'Menu compte client',
    ) );
    
    $widgets = glob(__DIR__."/widgets/*.php");
    foreach($widgets as $widget){
        require_once($widget);
    }
    /*add_theme_support('post-thumbnails'); 
    add_image_size('thumbnail-sample', 740 * 2, 484 * 2);*/
}
add_action( 'after_setup_theme', 'wext_setup_theme');

function wext_cpt_search( $query ) {
 
    if ( is_search() && $query->is_main_query() && $query->get( 's' ) ){
        $query->set('post_type', array('diy', 'article', 'sound', 'video'));
    }
 
    return $query;
};

add_filter('pre_get_posts', 'wext_cpt_search');

// Function to remove version numbers
function wext_remove_ver_css_js( $src ) {
    if ( strpos( $src, 'ver=' ) )
        $src = remove_query_arg( 'ver', $src );
    return $src;
}
add_filter( 'style_loader_src', 'wext_remove_ver_css_js', 9999 ); // Remove WP Version From Styles
add_filter( 'script_loader_src', 'wext_remove_ver_css_js', 9999 ); // Remove WP Version From Scripts



function getSettings($key){
    global $wext_conf;
    $pod = pods('reglage');
    return $pod->field($key);
    // todo next caching
    if(!isset($_SESSION[$wext_conf['settings_pod_name']['name']]) || empty($_SESSION[$wext_conf['settings_pod_name']['name']])){
        $_SESSION[$wext_conf['settings_pod_name']['name']] = [];
    }

    $returnCacheOnly = true;
    foreach($wext_conf['settings_pod_name']['fields'] as $key){
        if(!in_array($key, $_SESSION['reglages'])){
            $returnCacheOnly = false;
        }
    }
    
    if($returnCacheOnly){
        return $_SESSION[$wext_conf['settings_pod_name']['name']][$key];
    }
    
    $returns = array();
    $pod = pods($wext_conf['settings_pod_name']['name']);
    foreach($keys as $key){
        $returns[$key] = $pod->field($key);
        $_SESSION[$wext_conf['settings_pod_name']['name']][$key] = $pod->field($key);
    }
    return $_SESSION[$wext_conf['settings_pod_name']['name']][$key];
}

function get_post_content_wext($post){
    $content_post = get_post($post->ID);
    $content = $content_post->post_content;
    $content = apply_filters('the_content', $content);
    return $content;
}


function mytheme_tinymce_config( $init ) {
 $valid_iframe = 'iframe[id|class|title|style|align|frameborder|height|longdesc|marginheight|marginwidth|name|scrolling|src|width]';
 if ( isset( $init['extended_valid_elements'] ) ) {
  $init['extended_valid_elements'] .= ',' . $valid_iframe;
 } else {
  $init['extended_valid_elements'] = $valid_iframe;
 }
 return $init;
}
add_filter('tiny_mce_before_init', 'mytheme_tinymce_config');

/* Post URLs to IDs function, supports custom post types - borrowed and modified from url_to_postid() in wp-includes/rewrite.php */
function wext_url_to_postid($url)
{
    global $wp_rewrite;

    $url = apply_filters('url_to_postid', $url);

    // First, check to see if there is a 'p=N' or 'page_id=N' to match against
    if ( preg_match('#[?&](p|page_id|attachment_id)=(\d+)#', $url, $values) )   {
        $id = absint($values[2]);
        if ( $id )
            return $id;
    }

    // Check to see if we are using rewrite rules
    $rewrite = $wp_rewrite->wp_rewrite_rules();

    // Not using rewrite rules, and 'p=N' and 'page_id=N' methods failed, so we're out of options
    if ( empty($rewrite) )
        return 0;

    // Get rid of the #anchor
    $url_split = explode('#', $url);
    $url = $url_split[0];

    // Get rid of URL ?query=string
    $url_split = explode('?', $url);
    $url = $url_split[0];

    // Add 'www.' if it is absent and should be there
    if ( false !== strpos(home_url(), '://www.') && false === strpos($url, '://www.') )
        $url = str_replace('://', '://www.', $url);

    // Strip 'www.' if it is present and shouldn't be
    if ( false === strpos(home_url(), '://www.') )
        $url = str_replace('://www.', '://', $url);

    // Strip 'index.php/' if we're not using path info permalinks
    if ( !$wp_rewrite->using_index_permalinks() )
        $url = str_replace('index.php/', '', $url);

    if ( false !== strpos($url, home_url()) ) {
        // Chop off http://domain.com
        $url = str_replace(home_url(), '', $url);
    } else {
        // Chop off /path/to/blog
        $home_path = parse_url(home_url());
        $home_path = isset( $home_path['path'] ) ? $home_path['path'] : '' ;
        $url = str_replace($home_path, '', $url);
    }

    // Trim leading and lagging slashes
    $url = trim($url, '/');

    $request = $url;
    // Look for matches.
    $request_match = $request;
    foreach ( (array)$rewrite as $match => $query) {
        // If the requesting file is the anchor of the match, prepend it
        // to the path info.
        if ( !empty($url) && ($url != $request) && (strpos($match, $url) === 0) )
            $request_match = $url . '/' . $request;

        if ( preg_match("!^$match!", $request_match, $matches) ) {
            // Got a match.
            // Trim the query of everything up to the '?'.
            $query = preg_replace("!^.+\?!", '', $query);

            // Substitute the substring matches into the query.
            $query = addslashes(WP_MatchesMapRegex::apply($query, $matches));

            // Filter out non-public query vars
            global $wp;
            parse_str($query, $query_vars);
            $query = array();
            foreach ( (array) $query_vars as $key => $value ) {
                if ( in_array($key, $wp->public_query_vars) )
                    $query[$key] = $value;
            }

        // Taken from class-wp.php
        foreach ( $GLOBALS['wp_post_types'] as $post_type => $t )
            if ( $t->query_var )
                $post_type_query_vars[$t->query_var] = $post_type;

        foreach ( $wp->public_query_vars as $wpvar ) {
            if ( isset( $wp->extra_query_vars[$wpvar] ) )
                $query[$wpvar] = $wp->extra_query_vars[$wpvar];
            elseif ( isset( $_POST[$wpvar] ) )
                $query[$wpvar] = $_POST[$wpvar];
            elseif ( isset( $_GET[$wpvar] ) )
                $query[$wpvar] = $_GET[$wpvar];
            elseif ( isset( $query_vars[$wpvar] ) )
                $query[$wpvar] = $query_vars[$wpvar];

            if ( !empty( $query[$wpvar] ) ) {
                if ( ! is_array( $query[$wpvar] ) ) {
                    $query[$wpvar] = (string) $query[$wpvar];
                } else {
                    foreach ( $query[$wpvar] as $vkey => $v ) {
                        if ( !is_object( $v ) ) {
                            $query[$wpvar][$vkey] = (string) $v;
                        }
                    }
                }

                if ( isset($post_type_query_vars[$wpvar] ) ) {
                    $query['post_type'] = $post_type_query_vars[$wpvar];
                    $query['name'] = $query[$wpvar];
                }
            }
        }

            // Do the query
            $query = new WP_Query($query);
            if ( !empty($query->posts) && $query->is_singular )
                return $query->post->ID;
            else
                return 0;
        }
    }
    return 0;
}

function my_own_og_function() {
    global $post;
    $postMeta = get_post_meta($post->ID, 'image_principale'); 
    //$ret = clear_open_graph_cache(get_permalink());
    if ( is_array($postMeta) && isset($postMeta[0]['guid']) ) { 
        $GLOBALS['wpseo_og']->image_output( $postMeta[0]['guid'] );
   }
}
add_action( 'wpseo_opengraph', 'my_own_og_function', 29 );

/*    
function clear_open_graph_cache($url) {
  $vars = array('id' => $url, 'scrape' => 'true');
  $body = http_build_query($vars);

  $fp = fsockopen('ssl://graph.facebook.com', 443);
  fwrite($fp, "POST / HTTP/1.1\r\n");
  fwrite($fp, "Host: graph.facebook.com\r\n");
  fwrite($fp, "Content-Type: application/x-www-form-urlencoded\r\n");
  fwrite($fp, "Content-Length: ".strlen($body)."\r\n");
  fwrite($fp, "Connection: close\r\n");
  fwrite($fp, "\r\n");
  fwrite($fp, $body);
  fclose($fp);
}*/

function kaizen_widgets_init(){
for ($i=0;$i<3;$i++) {
		register_sidebar( array(
			'name'          => sprintf(__( 'Footer Col %d', 'yawpt' ),($i+1)).' ',
			'id'            => 'footer-widgets-'.($i+1),
			'description'   => sprintf(__( 'Add widgets here to appear in your footer column %d', 'yawpt' ),($i+1)),
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
			'before_widget' => '<div id="%1$s" class="widget footer-widgets %2$s">',
			'after_widget'  => '</div>'
		));
    } 
}
    
add_action( 'widgets_init', 'kaizen_widgets_init' );

add_action('init', 'customRSS');
function customRSS(){
        add_feed('feed-lilo', 'customRSSFunc');
}
function customRSSFunc(){
        get_template_part('child/rss', 'feed-lilo');
}


// Add the Style Dropdown Menu to the second row of visual editor buttons
function my_mce_buttons_2($buttons) {
    array_unshift($buttons, 'styleselect');
    //echo "<script>console.log('my_mce_buttons_2' );</script>";
    //die();
    return $buttons;
}
add_filter('mce_buttons_2', 'my_mce_buttons_2', 1000);

// Add new custom styles in Formats dropdown menu
function my_mce_before_init_insert_formats( $init_array ) {

    //echo "<script>console.log('my_mce_before_init' );</script>";
    $style_formats = array(
    // These are the custom styles
        array(
        'title' => 'Je m\'abonne',
        'inline' => 'span',
        'classes' => 'bandeau-abo-articles',
        'wrapper' => true,
        'cmd' => 'abo-comand',
        ),
    );
    // Insert the array, JSON ENCODED, into 'style_formats'
    $init_array['style_formats'] = json_encode( $style_formats );
    
    return $init_array;
    
}
// Attach callback to 'tiny_mce_before_init'
add_filter('tiny_mce_before_init', 'my_mce_before_init_insert_formats');

// function that runs when shortcode is called
function wpb_demo_shortcode() { 
 
    // Things that you want to do. 
    $message = '<a href="https://boutique.kaizen-magazine.com"><div class="bandeau-abo-articles"><p>Je m\'abonne pour 1 an et 6 numéros à partir de 28€</p></div></a>'; 
     
    // Output needs to be return
    return $message;
} 
// register shortcode
add_shortcode('abo', 'wpb_demo_shortcode'); 

	
/*
function custom_button_shortcode( $atts, $content = null ) {
   
    // shortcode attributes
    extract( shortcode_atts( array(
        'url'    => '',
        'title'  => '',
        'target' => '',
        'text'   => '',
    ), $atts ) );
 
    $content = $text ? $text : $content;
 
    // Returns the button with a link
    if ( $url ) {
 
        $link_attr = array(
            'href'   => esc_url( $url ),
            'title'  => esc_attr( $title ),
            'target' => ( 'blank' == $target ) ? '_blank' : '',
            'class'  => 'custombutton'
        );
 
        $link_attrs_str = '';
 
        foreach ( $link_attr as $key => $val ) {
 
            if ( $val ) {
 
                $link_attrs_str .= ' ' . $key . '="' . $val . '"';
 
            }
 
        }
 
 
        return '<a' . $link_attrs_str . '><span>' . do_shortcode( $content ) . '</span></a>';
 
    }
 
    // Return as span when no link defined
    else {
 
        return '<span class="custombutton"><span>' . do_shortcode( $content ) . '</span></span>';
 
    }
 
}

add_shortcode( 'custombutton', 'custom_button_shortcode' );*/