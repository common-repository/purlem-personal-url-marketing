<?php
/*
Plugin Name: Purlem Personalized URL
Plugin URI: http://purlem.com
Description: Personalize your blog to visitors and track results with Personalized URLs (PURLs). <strong>The Plugin Requires a <a href='http://www.purlem.com'>Purlem Account</a>.</strong>
Version: 1.4.0
Author: Marty Thomas
Author URI: http://purlem.com/company
License: A "Slug" license name e.g. GPL2


Copyright 2012  Marty Thomas  (email : support@purlem.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as 
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if($_GET['purl'] && $_GET['purl'] != '') {
	add_action('wp_head', 'display_purl_code');
	add_action('the_content', 'display_purl_content');
	add_action('the_title', 'display_purl_header');
	add_action('wp_head', 'purlCSS'); 
}

//when adding options for the first time
add_action('add_option_purlemID', 'add_htaccess_code');
add_action('add_option_purlemURI', 'add_htaccess_code');
//when updating options
add_action('update_option_purlemID', 'add_htaccess_code');
add_action('update_option_purlemURI', 'add_htaccess_code');

add_action('widgets_init', create_function('', 'return register_widget("PurlemWidget");'));

function add_htaccess_code() {
	$file = '../.htaccess';
	$code = "\r\n#PURL CODE\nRewriteEngine on 
RewriteCond %{SCRIPT_FILENAME} !([A-Za-z0-9_]+)\.(html?|php|asp|css|jpg|gif|shtml|htm|xhtml|txt|ico|xml|wp-admin|admin)/?$ [NC] 
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^([A-Za-z0-9]+)\\\\\.([A-Za-z0-9]+)/?$ ".get_option('purlemURI')."&purl=\\$1\\$2&ID=".get_option('purlemID')."&page=1&wordpress=Y [R]\n#END PURL CODE";
	$code_permalink = "#PURL CODE
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteCond %{SCRIPT_FILENAME} !([A-Za-z0-9_]+)\.(html?|php|asp|css|jpg|gif|shtml|htm|xhtml|txt|ico|xml|wp-admin|admin)/?$ [NC]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^([A-Za-z0-9_]+)\\\\\.([A-Za-z0-9_]+)/?$ ".get_option('purlemURI')."?purl=\\$1\\$2&ID=".get_option('purlemID')."&page=1&wordpress=Y [R,L]
</IfModule>
#END PURL CODE\n";
	if($htaccess_content = file_get_contents($file)) {
		if($htaccess_content) {
			if(strstr($htaccess_content,'<IfModule mod_rewrite.c>')) {
				$purlCode = $code_permalink;
			} else {
				$purlCode = $code;
			}
			$search_pattern = "/(#PURL CODE)(?:[\w\W\r\n]*?)(#END PURL CODE)/i";
			$new_content = preg_replace($search_pattern, $purlCode, $htaccess_content);
			if(!strstr($new_content,'#PURL CODE')) {
				$new_content = stripslashes($purlCode)."".$htaccess_content;
			}
			if(!file_put_contents($file, $new_content)) {
				add_settings_error( 'error', '', 'We were not able to append to the .htaccess file. For help please contact us - support@purlem.com', 'error' );
			}
		} else {
			if(!file_put_contents($file, stripslashes($code))) {
				add_settings_error( 'error', '', 'We were not able to update the .htaccess file. For help please contact us - support@purlem.com', 'error' );
			}
		}
	} else {
		add_settings_error( 'error', '', 'We were not able to access the .htaccess file. For help please contact us - support@purlem.com', 'error' );
	}
	
}

function display_purl_code() {
	$uri_addslash = str_replace('?','/?',$_SERVER['REQUEST_URI']);
	$uri = explode('/',$uri_addslash);
	if(!$_GET['page']) $_GET['page'] = $uri[2];
	if(!is_numeric($_GET['page'])) $_GET['page'] = $uri[3];
	if(!is_numeric($_GET['page'])) $_GET['page'] = $uri[4];
	if(!is_numeric($_GET['page'])) $_GET['page'] = 1;

	if(get_option('purlapi') == 'file_get_contents') {
		$data = @file_get_contents('http://www.purlapi.com/lp/index.php?ID='.$_GET["ID"].'&name='.$_GET["purl"].'&page='.$_GET["page"].'&qr='.$_GET["qr"].'&test='.$_GET["test"].'&wordpress='.$_GET["wordpress"].'&domain='.$_SERVER["HTTP_HOST"].'&useragent='.urlencode($_SERVER['HTTP_USER_AGENT']).'&uri='.urlencode($_SERVER['REQUEST_URI']).'&ip='.urlencode($_SERVER['REMOTE_ADDR'])); 
	} else {
		$curl = @curl_init(); curl_setopt ($curl, CURLOPT_URL, 'http://www.purlapi.com/lp/index.php?ID='.$_GET["ID"].'&name='.$_GET["purl"].'&page='.$_GET["page"].'&qr='.$_GET["qr"].'&test='.$_GET["test"].'&wordpress='.$_GET["wordpress"].'&domain='.$_SERVER["HTTP_HOST"].'&useragent='.urlencode($_SERVER['HTTP_USER_AGENT']).'&uri='.urlencode($_SERVER['REQUEST_URI']).'&ip='.urlencode($_SERVER['REMOTE_ADDR'])); 
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);          
		$data = curl_exec ($curl); 
		curl_close ($curl);
	}
	$visitor = json_decode($data); 
	@session_start();
	if($_GET['username']) $_SESSION['visitor']=$_GET['username']; 
	if($visitor->{'login'} && ($_SESSION['visitor'] != $visitor->{'firstName'}.''.$visitor->{'lastName'})) { 
		echo $visitor->{'login'}; 
		exit; 
	}
	$_SESSION['visitor'] = $visitor;
}

function display_purl_content($content) {
	$newContent .= purl_convert($content);
	@session_start();
	
	if(!$_SESSION['visitor'] && !$_GET['refreshed'] && $_GET['purl']) {
		header( 'Location: http://'.$_SERVER[HTTP_HOST].$_SERVER[REQUEST_URI].'?ID='.$_GET['ID'].'&purl='.$_GET['purl'].'&test='.$_GET['test'].'&wordpress='.$_GET['wordpress'].'&refreshed=Y' ) ;
	}
	
	if($_GET['wordpress'] == 'Y') {
		$newContent .= $_SESSION['visitor']->{'content'};
		if(get_option('showPurlForm') == 'Y') $newContent .= $_SESSION['visitor']->{'form'};
		
		if(!$_SESSION['visitor']) {
			$newContent .= '<b>PURL NOT FOUND</b> Please try again.';
		}
	}
	
	
	
	return $newContent;
}

function display_purl_header($content) {
	$content = purl_convert($content);
	return $content;
}


// add the admin options page
add_action('admin_menu', 'plugin_admin_add_page');
	function plugin_admin_add_page() {
	add_options_page('Purlem Settings', 'Purlem', 'manage_options', 'plugin', 'plugin_options_page');
}


// display the admin options page
function plugin_options_page() {
	?>	
  <div class="wrap">
  <div id="icon-options-general" class="icon32"><br /></div>
	<h2>Purlem Settings</h2>
	by <strong>Marty Thomas</strong> of <strong>Purlem</strong><br />
	<div style="background-color:white;padding: 10px 10px 10px 10px;margin-right:15px;margin-top:10px;margin-bottom:15px;border: 1px solid #ddd; width:350px;">
	<img src="http://www.purlem.com/assets/images/logo_white.gif" width="300" height="84" alt="Purlem Personalized URL Marketing" /><br />
  <h3 style="margin-top:0px;margin-bottom:0px;">Purlem - Personal URL Marketing</h3>
	<a href="http://www.purlem.com">http://www.purlem.com</a></div>
  <p><b>Enter details from your Purlem account below.</b><br />
  Don't have an account? <a href="http://www.purlem.com">Sign Up</a><br />
  Need Help?  Visit the <a href="http://docs.purlem.com/article/7-purlem-wordpress-plugin-install" target="_blank">Support Page</a>.</p>
  <form action="options.php" method="post">
  <?php wp_nonce_field('update-options'); ?>
  
  <table class="form-table">
  
  <tr valign="top" style="background-color:#f4f4f4; border-bottom: 1px solid #e6e6e6;">
  <th scope="row">Purlem User ID:</th>
  <td><input name="purlemID" type="text" value="<?php echo get_option('purlemID'); ?>" size="10" style="font-size:16px;" /></td>
  </tr>
   
  <tr valign="top" style="background-color:#f4f4f4; border-bottom: 1px solid #ccc;">
  <th scope="row">Page URL: </th>
  <td><input name="purlemURI" type="text" value="<?php echo get_option('purlemURI'); ?>" size="50" style="font-size:16px;" /><br />
  <i style="color:gray;font-size:11px;">The full URL of the blog page to be personalized.</i></td>
  </tr>
  
  <tr valign="top" style="border-bottom: 1px solid #e6e6e6;">
  <th scope="row">Show Form in Content Area: </th>
  <td><input <?php if (!(strcmp(get_option('showPurlForm'),"Y"))) {echo "checked=\"checked\"";} ?> name="showPurlForm" type="checkbox" value="Y" /></td>
  </tr>

  <tr valign="top">
  <th scope="row">API Type: </th>
  <td>
  <?php if (!(strcmp(get_option('purlapi'),"curl"))) { 
	$curl = 'checked';
  } else {
	$file_get_contents = 'checked';
  }?>
  <input type="radio" name="purlapi" value="file_get_contents" <?php echo $file_get_contents; ?>> file_get_contents  &nbsp;  <input type="radio" name="purlapi" value="curl" <?php echo $curl; ?>> curl
  <br />
  <i style="color:gray;font-size:11px;">If you receive a "PURL NOT FOUND" error, try using curl.</i>
  </td>
  </tr>
  
  
  </table>
  
  <input type="hidden" name="apiType" value="update" />
  <input type="hidden" name="action" value="update" />
  <input type="hidden" name="page_options" value="purlemID,purlemURI,showPurlForm,purlapi" />
  
  <p class="submit">
  <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
  </p>
  
  </form>
  </div>
  
  
	<?php
}

function purl_convert($content) {
	@session_start();
	$i = 0;
	$patterns[$i] = '/#firstName/'; $i++;
	$patterns[$i] = '/#lastName/'; $i++;
	$patterns[$i] = '/#organization/'; $i++;
	$patterns[$i] = '/#company/'; $i++;
	$patterns[$i] = '/#position/'; $i++;
	$patterns[$i] = '/#email/'; $i++;
	$patterns[$i] = '/#phone/'; $i++;
	$patterns[$i] = '/#address1/'; $i++;
	$patterns[$i] = '/#address/'; $i++;
	$patterns[$i] = '/#city/'; $i++;
	$patterns[$i] = '/#state/'; $i++;
	$patterns[$i] = '/#zip/'; $i++;
	$patterns[$i] = '/#password/'; $i++;
	$patterns[$i] = '/#form/'; $i++;
	$patterns[$i] = '/#custom10/'; $i++;
	$patterns[$i] = '/#custom11/'; $i++;
	$patterns[$i] = '/#custom12/'; $i++;
	$patterns[$i] = '/#custom13/'; $i++;
	$patterns[$i] = '/#custom14/'; $i++;
	$patterns[$i] = '/#custom15/'; $i++;
	$patterns[$i] = '/#custom16/'; $i++;
	$patterns[$i] = '/#custom17/'; $i++;
	$patterns[$i] = '/#custom18/'; $i++;
	$patterns[$i] = '/#custom19/'; $i++;
	$patterns[$i] = '/#custom20/'; $i++;
	$patterns[$i] = '/#custom21/'; $i++;
	$patterns[$i] = '/#custom22/'; $i++;
	$patterns[$i] = '/#custom23/'; $i++;
	$patterns[$i] = '/#custom24/'; $i++;
	$patterns[$i] = '/#custom25/'; $i++;
	$patterns[$i] = '/#custom26/'; $i++;
	$patterns[$i] = '/#custom27/'; $i++;
	$patterns[$i] = '/#custom28/'; $i++;
	$patterns[$i] = '/#custom29/'; $i++;
	$patterns[$i] = '/#custom30/'; $i++;
	$patterns[$i] = '/#custom31/'; $i++;
	$patterns[$i] = '/#custom32/'; $i++;
	$patterns[$i] = '/#custom33/'; $i++;
	$patterns[$i] = '/#custom34/'; $i++;
	$patterns[$i] = '/#custom35/'; $i++;
	$patterns[$i] = '/#custom36/'; $i++;
	$patterns[$i] = '/#custom37/'; $i++;
	$patterns[$i] = '/#custom38/'; $i++;
	$patterns[$i] = '/#custom39/'; $i++;
	$patterns[$i] = '/#custom40/'; $i++;
	$patterns[$i] = '/#custom41/'; $i++;
	$patterns[$i] = '/#custom42/'; $i++;
	$patterns[$i] = '/#custom43/'; $i++;
	$patterns[$i] = '/#custom44/'; $i++;
	$patterns[$i] = '/#custom45/'; $i++;
	$patterns[$i] = '/#custom46/'; $i++;
	$patterns[$i] = '/#custom47/'; $i++;
	$patterns[$i] = '/#custom48/'; $i++;
	$patterns[$i] = '/#custom49/'; $i++;
	$patterns[$i] = '/#custom50/'; $i++;
	$patterns[$i] = '/#custom1/'; $i++;
	$patterns[$i] = '/#custom2/'; $i++;
	$patterns[$i] = '/#custom3/'; $i++;
	$patterns[$i] = '/#custom4/'; $i++;
	$patterns[$i] = '/#custom5/'; $i++;
	$patterns[$i] = '/#custom6/'; $i++;
	$patterns[$i] = '/#custom7/'; $i++;
	$patterns[$i] = '/#custom8/'; $i++;
	$patterns[$i] = '/#custom9/'; $i++;

	$i=0;
	$replacements[$i] = $_SESSION['visitor']->{'firstName'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'lastName'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contact_organization'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contact_company'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contact_position'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contact_email'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contact_phone'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contact_address1'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contact_address1'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contact_city'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contact_state'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contact_zip'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contact_password'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'form'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_10'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_11'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_12'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_13'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_14'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_15'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_16'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_17'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_18'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_19'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_20'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_21'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_22'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_23'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_24'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_25'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_26'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_27'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_28'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_29'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_30'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_31'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_32'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_33'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_34'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_35'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_36'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_37'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_38'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_39'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_40'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_41'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_42'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_43'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_44'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_45'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_46'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_47'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_48'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_49'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_50'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_1'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_2'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_3'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_4'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_5'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_6'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_7'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_8'}; $i++;
	$replacements[$i] = $_SESSION['visitor']->{'contacts_cf_9'}; $i++;
	$convertedContent = preg_replace($patterns, $replacements, $content);
	return $convertedContent;
}


/**
 * PurlemWidget Class
 */
class PurlemWidget extends WP_Widget {
    /** constructor */
    function PurlemWidget() {
        parent::WP_Widget(false, $name = 'Purlem Form');	
    }

    /** @see WP_Widget::widget */
    function widget($args, $instance) {		
        extract( $args );
        $title = apply_filters('widget_title', $instance['title']);
        ?>
              <?php echo $before_widget; ?>
                  <?php if ( $title )
                        echo $before_title . $title . $after_title;
						echo $_SESSION['visitor']->{'form'};
               echo $after_widget; ?>
        <?php
    }

    /** @see WP_Widget::update */
    function update($new_instance, $old_instance) {				
        return $new_instance;
    }

    /** @see WP_Widget::form */
    function form($instance) {				
        $title = esc_attr($instance['title']);
        ?>
            <p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Form Title:'); ?> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></label></p>
        <?php 
    }

} // class PurlemWidget


function purlCSS() {
	?>
  <style type="text/css">
	<!--
	.formTitle {
		font-size:20px;
		font-weight:bold;
		margin-bottom:10px;
	}
	.formDescription {
		font-size:16px;
		margin-bottom:15px;
	}
	.formElement {
		margin-bottom:15px;
	}
	.formElement .textbox {
		font-size: 16px;
		width:97%;
		font-weight: bold;
		padding:2px;
	}
	.formElement .title {
		font-weight:bold;
	}
	.checkbox, .radio {
		font-weight:normal;
	}
	.button {
		margin-top:10px;
		font-size: 14px;
	}
	.required {
		color:red;
	}
	-->
	</style>
  <?php
}
?>