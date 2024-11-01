<?php
/*
Plugin Name: Skribit
Plugin URI: http://skribit.com/wordpress
Description: Help cure writer's block by getting suggestions from your readers with this plugin.
Version: 1.0.1
Author: Calvin Yu
Author URI: http://codeeg.com
*/

/*  Copyright 2010  Calvin Yu (email : calvin@skribit.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

define( 'SKRIBIT_HOST'         , 'http://skribit.com' );
define( 'SKRIBIT_ASSET_HOST'   , 'http://assets.skribit.com' );
define( 'SKRIBIT_WIDGET_DOM_ID', 'skribitWidgetContainer' );

function skribit_init() {
  skribit_setup();
  add_action( 'wp_head'   , 'skribit_render_lightbox' );
  add_action( 'admin_menu', 'skribit_add_config_page' );
}
add_action( 'init', 'skribit_init' );

function skribit_admin_init() {
  register_setting( 'skribit_options', 'skribit_display', 'skribit_sanitize_options' );
  if ( !skribit_is_config_page() )
    skribit_admin_warnings();
}
add_action( 'admin_init', 'skribit_admin_init' );

function skribit_loaded() {
  if ( skribit_can_render_sidebar_widget() ) {
    register_sidebar_widget( array('Skribit', 'widgets'), 'skribit_render_sidebar_widget' );
    register_widget_control( array('Skribit', 'widgets'), 'skribit_render_widget_control' );
  }
}
add_action( 'plugins_loaded', 'skribit_loaded' );

function skribit_can_render_sidebar_widget() {
  return get_option( 'skribit_bloginfo' ) || get_option( 'widget_skribit' );
}

/** Add the configuration page */
function skribit_add_config_page() {
  if ( function_exists( 'add_submenu_page' ) ) {
    add_submenu_page( 'plugins.php', __('Skribit Configuration'),
        __('Skribit Configuration'), 'manage_options',
        'skribit-config', 'skribit_render_config_page' );
  }
}

/** Show any Skribit warnings */
function skribit_admin_warnings() {
  if ( !get_option( 'skribit_bloginfo' ) && !isset( $_GET['updated'] ) ) {
    function skribit_warning() {
      echo "
      <div id='skribit-warning' class='updated fade'><p><strong>".__('Thanks for installing the Skribit Plugin!')."</strong> ".sprintf(__('You must <a href="%1$s">connect to your Skribit.com account</a> for it to work.'), "plugins.php?page=skribit-config")."</p></div>
      ";
    }
    add_action( 'admin_notices', 'skribit_warning' );
    return;
  }
}

/** Pair admin user to the Skribit site */
function skribit_setup() {
  if ( !skribit_is_config_page() || !is_admin() ) return;

  $setup_opt = get_option( 'skribit_setup', array() );

  if ( isset( $_GET['setup'] ) ) {
    $host = constant( 'SKRIBIT_HOST' );

    $success = true;
    if ( $success && !isset($setup_opt['oauth_consumer']) )
      $success = skribit_setup_get_oauth_consumer_pair( $setup_opt );

    if ( $success && !isset($setup_opt['oauth_request_token']) )
      $success = skribit_setup_get_oauth_request_token( $setup_opt );

    if ( $success && isset($setup_opt['oauth_request_token']) )
      skribit_setup_redirect_oauth_authorize( $setup_opt );

  } else if ( isset( $_GET['oauth_verifier'] ) ) {
    skribit_setup_get_access_token( $setup_opt );
    skribit_get_bloginfo( $setup_opt );

    wp_redirect(get_bloginfo('wpurl').'/wp-admin/plugins.php?page=skribit-config');
  }
}

/** Returns true if the current request is the Skribit configuration page */
function skribit_is_config_page() {
  return preg_match( '/plugins.php/', $_SERVER['REQUEST_URI'] ) && $_GET['page'] == 'skribit-config';
}

/** Retrieve OAuth consumer key/secret from Skribit */
function skribit_setup_get_oauth_consumer_pair( &$setup_opt ) {
  $host = constant( 'SKRIBIT_HOST' );
  
  $blog_name    = get_bloginfo();
  $wp_url       = get_bloginfo('wpurl');
  $callback_url = "$wp_url/wp-admin/plugins.php?page=skribit-config";
  
  $params = array(
    'client_application[name]'         => urlencode( $blog_name ),
    'client_application[url]'          => urlencode( $wp_url ),
    'client_application[callback_url]' => urlencode( $callback_url ),
    'client'                           => 'wp-plugin');
  
  $auto_create_url = add_query_arg( $params, "$host/oauth_clients/auto_create" );

  $body = wp_remote_retrieve_body( wp_remote_post( $auto_create_url ) );
  $resp = skribit_parse_oauth_values( $body );

  if ( $resp['oauth_consumer_key'] ) {
    $setup_opt['oauth_consumer'] = array( $resp['oauth_consumer_key'], $resp['oauth_consumer_secret'] );

    if ( get_option('skribit_setup') )
      update_option( 'skribit_setup', $setup_opt );
    else
      add_option( 'skribit_setup', $setup_opt, ' ', 'no' );

    return true;
  }

  return false;
}

/** Retrieve OAuth request token */
function skribit_setup_get_oauth_request_token( &$setup_opt ) {
  skribit_require_oauth();
  
  $consumer = skribit_oauth_pair_from_option( $setup_opt, 'oauth_consumer' );

  $resp = skribit_oauth_get_values( $consumer, NULL,
      'oauth/request_token', 'POST', array( 'oauth_callback' => 'oob' ) );
  
  if ( $resp['oauth_callback_confirmed'] == 'true' ) {
    $setup_opt['oauth_request_token'] = array( $resp['oauth_token'], $resp['oauth_token_secret'] );
    update_option( 'skribit_setup', $setup_opt );
    return true;
  }

  return false;
}

/** Do a OAuth redirect to the authorize URL */
function skribit_setup_redirect_oauth_authorize( &$setup_opt ) {
  $host = constant('SKRIBIT_HOST');
  $tok = $setup_opt['oauth_request_token'];
  wp_redirect( add_query_arg('oauth_token', $tok[0], "$host/oauth/authorize") );
  die();
}

/** Retreive OAuth access token */
function skribit_setup_get_access_token( &$setup_opt ) {
  skribit_require_oauth();
  
  $consumer = skribit_oauth_pair_from_option( $setup_opt, 'oauth_consumer' );
  $token    = skribit_oauth_pair_from_option( $setup_opt, 'oauth_request_token' );

  $resp = skribit_oauth_get_values( $consumer, $token, 'oauth/access_token',
      'POST', array( 'oauth_verifier' => $_GET['oauth_verifier'] ) );

  $setup_opt['oauth_access'] = array( $resp['oauth_token'], $resp['oauth_token_secret'] );
  unset( $setup_opt['oauth_request_token'] );
  
  update_option( 'skribit_setup', $setup_opt );
}

/** Parse the given body for OAuth formatted response */
function skribit_parse_oauth_values( $body ) {
  $resp = array();
  parse_str( $body, $resp );
  return $resp;
}

/** Ensure OAuth classes are loaded */
function skribit_require_oauth() {
  if ( !class_exists('OAuthConsumer') ) include_once( 'OAuth.php' );
}

/** Get a OAuth related pair from Skribit Setup options */
function skribit_oauth_pair_from_option( $setup_opt, $opt_key ) {
  $opt = $setup_opt[$opt_key];
  return new OAuthConsumer($opt[0], $opt[1]);
}

/** Make a OAuth access request */
function skribit_oauth_get_values( $consumer, $token, $endpoint,
    $http_method = 'GET', $parameters = NULL ) {
  
  $body = skribit_oauth_get( $consumer, $token, $endpoint, $http_method, $parameters );
  return skribit_parse_oauth_values( $body );
}

/** Make OAuth request for JSON data */
function skribit_oauth_get_json( $consumer, $token, $endpoint,
    $http_method = 'GET', $parameters = NULL ) {

  $body = skribit_oauth_get( $consumer, $token, $endpoint, $http_method, $parameters );
  return json_decode( $body, true );
}

/** Make a OAuth request to Skribit */
function skribit_oauth_get( $consumer, $token, $endpoint,
    $http_method = 'GET', $parameters = NULL, $debug = false ) {

  $host = constant( 'SKRIBIT_HOST' );
  $req = OAuthRequest::from_consumer_and_token( $consumer, $token,
      $http_method, "$host/$endpoint", $parameters );
  $req->sign_request( new OAuthSignatureMethod_HMAC_SHA1(), $consumer, $token );

  $http_opts = array( 'timeout' => 20 );
  $resp = ( $http_method == 'GET' )
      ? wp_remote_get( $req->to_url(), $http_opts ) : wp_remote_post( $req->to_url(), $http_opts );

  if ( $debug ) echo var_dump( $resp );

  return wp_remote_retrieve_body( $resp );
}

/** Retrieve the blog information form Skribit */
function skribit_get_bloginfo( $setup_opt ) {
  skribit_require_oauth();

  $consumer = skribit_oauth_pair_from_option( $setup_opt, 'oauth_consumer' );
  $token    = skribit_oauth_pair_from_option( $setup_opt, 'oauth_access' );
  
  $json = skribit_oauth_get_json( $consumer, $token, 'manage.json' );

  if ( sizeof( $json ) == 0 ) {
    $params = array();
    $params['blog[name]']        = get_bloginfo();
    $params['blog[display_url]'] = get_bloginfo( 'url' );
    $params['blog[description]'] = get_bloginfo( 'description' );
    
    $create_result = skribit_oauth_get_json( $consumer, $token, 'blogs.json', 'POST', $params );
    $json[] = $create_result;
  }

  $old_admin_opts  = get_option( 'SkribitPluginAdminOptions' );
  $old_widget_opts = get_option( 'widget_skribit' );

  if ( get_option( 'skribit_bloginfo' ) )
    update_option( 'skribit_bloginfo', $json );
  else
    add_option( 'skribit_bloginfo', $json, ' ', 'no' );

  // Clean up some old configs if necessary
  if ( $old_widget_opts ) delete_option( 'widget_skribit' );
  if ( $old_admin_opts  ) delete_option( 'SkribitPluginAdminOptions' );

  $display_opts = get_option( 'skribit_display', array() );
  if ( !isset( $display_opts['bloginfo'] ) ) {
    $display_opts['bloginfo'] = $json[0];

    if ( $old_admin_opts && $old_admin_opts['lightbox'] )
      $display_opts['lightbox'] = 'on';

    if ( $old_widget_opts )
      $display_opts['sidebar_widget'] = $old_widget_opts;

    update_option( 'skribit_display', $display_opts );
  }
}

function skribit_sanitize_options( $input ) {
  $newinput = array();
  $newinput['lightbox'] = $input['lightbox'];
  
  $slug = $input['bloginfo'];
  
  $bloginfo = get_option( 'skribit_bloginfo' );
  foreach ( $bloginfo as $item) {
    if ( $item['slug'] == $slug ) {
      $newinput['bloginfo'] = $item;
      break;
    }
  }

  if ( !isset( $newinput['bloginfo'] ) )
    $newinput['bloginfo'] = $bloginfo[0];
  
  return $newinput;
}


function skribit_render_lightbox() {
  $display_opts = get_option( 'skribit_display' );

  if ( !$display_opts ) {
    $old_opts     = get_option( 'SkribitPluginAdminOptions' );
    $display_opts = array(
        'lightbox' => ( $old_opts['lightbox'] ? 'on' : '' ),
        'bloginfo' => $old_opts );
  }

  if ( $display_opts && $display_opts['lightbox'] == 'on' ) {
    $host       = constant( 'SKRIBIT_HOST' );
    $asset_host = constant( 'SKRIBIT_ASSET_HOST' );

    $bloginfo = $display_opts['bloginfo'];

    echo "<style type='text/css'>@import url('$asset_host/stylesheets/SkribitSuggest.css');</style>";
    echo "<script src='$asset_host/javascripts/SkribitSuggest.js' type='text/javascript'></script>";
    echo "<script type='text/javascript' charset='utf-8'>";
    echo "var skribit_settings = {};";
    echo "skribit_settings.placement = 'right';";
    echo "skribit_settings.color = '#333333';";
    echo "skribit_settings.text_color = 'white';";
    echo "skribit_settings.distance_vert = '20%';";
    echo "SkribitSuggest.suggest('$host/lightbox/".$bloginfo['slug']."', skribit_settings);";
    echo "</script>";
  }
}

function skribit_render_sidebar_widget($args) {
  extract($args);

  $display_opts = get_option( 'skribit_display' );

  if ( $display_opts ) {
    $widget_opts = $display_opts['sidebar_widget'];
    $bloginfo    = $display_opts['bloginfo'];

  } else {
    $widget_opts = get_option( 'widget_skribit' );
    $bloginfo    = get_option( 'SkribitPluginAdminOptions' );
  }

  $defaults = array('title' => 'Skribit Suggestions', 'no_css' => false);
  foreach ( $defaults as $key => $value ) {
    if ( !isset($widget_opts[$key]) )
      $widget_opts[$key] = $defaults[$key];
  }

  $title  = $widget_opts['title'];
  $no_css = $widget_opts['no_css'];

  $widget_id  = constant( 'SKRIBIT_WIDGET_DOM_ID' );
  $asset_host = constant( 'SKRIBIT_ASSET_HOST' );

  echo $before_widget . $before_title . $title . $after_title;
  echo "<div id='$widget_id'></div>";
  echo "<script type='text/javascript' src='$asset_host/javascripts/SkribitWidget.js?renderTo=$widget_id&amp;blog=".$bloginfo['blog_code']."&amp;noCSS=$no_css'></script>";
  echo "<noscript>Sorry, but the Skribit widget only works on browsers that support JavaScript.  ";

  if ( $bloginfo['slug'] )
    echo "<a href=\"http://skribit.com/blogs/".$bloginfo['slug']."\">View suggestions for this blog here.</a>";

  echo "</noscript>";
  echo $after_widget;
}

function skribit_render_widget_control() {
  $display_opts = get_option( 'skribit_display' );

  if ( !$display_opts ) { ?>
    <p>
      Please <a href="plugins.php?page=skribit-config">connect your Skribit Plugin to your Skribit.com account</a>.
    </p>
    <?php if ( get_option( 'widget_skribit' ) ) {?>
    <p>
      <b>NOTE:</b> This widget should still continue to work, but customizing the widget will be disabled until setup is complete.
    </p>
    <?php } return;
  }

  $widget_opts = $display_opts['sidebar_widget'];

  if ( !isset($widget_opts) ) {
    $widget_opts = array( 'title' => 'Skribit Suggestions' );
    $display_opts['sidebar_widget'] = $widget_opts;
  }

  if ( isset($_POST['skribit-submit']) ) {
    // Remember to sanitize and format use input appropriately.
    $widget_opts['title']  = strip_tags(stripslashes( $_POST['skribit-title'] ));
    $widget_opts['no_css'] = ( $_POST['skribit-nocss'] == 'on' );
    $display_opts['sidebar_widget'] = $widget_opts;

    update_option( 'skribit_display', $display_opts );
  }

  // Be sure you format your options to be valid HTML attributes.
  $title = htmlspecialchars( $widget_opts['title'], ENT_QUOTES );
  $noCSS = $widget_opts['no_css']; ?>

  <p><label for="skribit-title"><?php _e('Title:'); ?>
    <input id="skribit-title" class="widefat" name="skribit-title" type="text" value="<?php _e($title); ?>" />
  </label></p>
  <p><label for="skribit-nocss">
    <input class="checkbox" type="checkbox" name="skribit-nocss" value="on" <?php checked('on', $noCSS) ?>/> <?php _e('Disable Skribit CSS');?>
  </label></p>
  <input type="hidden" name="skribit-submit" value="1" />
  <?php
}

function skribit_render_config_page() {
?>
<?php if ( !empty($_GET['updated']) ) : ?>
<div id="message" class="updated fade"><p><strong><?php _e('Settings saved.') ?></strong></p></div>
<?php endif; ?>

<div class="wrap">
  <h2><?php _e('Skribit Configuration') ?></h2> <?php
  
  $bloginfo = get_option( 'skribit_bloginfo' );
  
  if ( !$bloginfo ) {
    skribit_render_setup_page();
    return;
  }

  skribit_render_final_config_page( $bloginfo );
}

function skribit_render_setup_page() { ?>
  <div class="narrow" style="width:30em">
  	<h4><?php printf(__("In order to use this plugin, it must be connected to a <b>Skribit</b> account.  Click the button below to get started.")) ?></h4>

  	<form action="<?php echo add_query_arg('setup', true) ?>" method="POST">
    	<input type="submit" value="  Connect to Skribit  " />
  	</form>
	</div> <?php
}

function skribit_render_final_config_page($bloginfo) { ?>
  <form method="post" action="options.php">
    <?php settings_fields( 'skribit_options' );
    $options  = get_option( 'skribit_display' );
    $bloginfo = get_option( 'skribit_bloginfo' );

    $sel_bloginfo = $options['bloginfo']; ?>
    
    <table class="form-table">
      <tr valign="top"><th scope="row"><label>Blog</label></th>
        <td>
          <?php if ( sizeof( $bloginfo ) > 1 ) { ?>
            <select name="skribit_display[bloginfo]">
            <?php foreach ( $bloginfo as $item ) { ?>
              <option<?php selected( $sel_bloginfo['slug'], $item['slug'] ); ?> value="<?php echo $item['slug'] ?>"><?php echo $item['name'] ?></option>
            <?php } ?>
            </select>
          <?php } else { echo $sel_bloginfo['name']; } ?>
        </td>
      </tr>
      <tr valign="top"><th scope="row">Lightbox</th>
        <td>
          <fieldset>
            <label for="skribit-lightbox">
              <input class="checkbox" type="checkbox" name="skribit_display[lightbox]" id="skribit-lightbox" value="on" <?php checked('on', $options['lightbox']) ?> /> Show LightBox Widget
            </label>
          </fieldset>
        </td>
      </tr>
      <tr valign="top">
        <th scope="row"><label>Sidebar</label></th>
        <td>Looking for the sidebar widget?  You can add that from the <a href="<?php echo get_bloginfo('wpurl').'/wp-admin/widgets.php' ?>">Widgets page</a>.</td>
      </tr>
    </table>
    <p class="submit">
      <input type="submit" name="submit" value="<?php _e('Update options &raquo;'); ?>" /></p>
    </p>
  </form> <?php
} ?>
