<?php
/*
Plugin Name: Retweet Anywhere
Plugin URI: http://kovshenin.com/wordpress/plugins/retweet-anywhere/
Description: Retweet Anywhere for WordPress is a nice and easy way to allow your readers to instantly retweet your blog posts through their Twitter accounts
Author: Konstantin Kovshenin
Version: 0.1.2
Author URI: http://kovshenin.com/

	License

    Retweet Anywhere
    Copyright (C) 2010 Konstantin Kovshenin (kovshenin@live.com)

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
    
*/

// JSON services for PHP4
if(!function_exists('json_encode'))
{
	include_once('json.php');
	$GLOBALS['JSON_OBJECT'] = new Services_JSON();
	function json_encode($value)
	{
		return $GLOBALS['JSON_OBJECT']->encode($value); 
	}
	
	function json_decode($value)
	{
		return $GLOBALS['JSON_OBJECT']->decode($value); 
	}
}

class RetweetAnywhereWidget extends WP_Widget {
	function RetweetAnywhereWidget()
	{
		$widget_ops = array('classname' => 'widget-retweet-anywhere', 'description' => __("A retweet widget for your blog"));
		$this->WP_Widget('widget-retweet-anywhere', __('Retweet Anywhere'), $widget_ops);
	}
	
	function widget($args, $instance)
	{
		extract($args);
		$title = apply_filters('widget_title', $instance['title']);
		$format = $instance['format'];
		$width = $instance['width'];
		$height = $instance['height'];
		
		global $wp_query;
		if ($wp_query->in_the_loop || is_singular())
		{
			global $post;
			$post_id = $post->ID;
		}
		else
			$post_id = 0;
		
		echo $before_widget;

		echo "<div class='retweet-anywhere-widget-box'>
			<style>.retweet-anywhere-widget-box em {display: none;}</style>
			<em class='post_id'>{$post_id}</em>
			<em class='title'>{$title}</em>
			<em class='format'>{$format}</em>
			<em class='width'>{$width}</em>
			<em class='height'>{$height}</em>
		</div>";
		
		echo $after_widget;
	}
	
	function update($new_instance, $old_instance)
	{
		return $new_instance;
	}
	
	function form($instance)
	{
		$instance = wp_parse_args((array) $instance, array('title' => 'Retweet', 'format' => '%s %l', 'width' => '200', 'height' => '70'));
		$title = $instance['title'];
		$format = $instance['format'];
		$width = $instance['width'];
		$height = $instance['height'];
?>
<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" /></label></p>
<p><label for="<?php echo $this->get_field_id('format'); ?>"><?php _e('Format:'); ?> <input class="widefat" id="<?php echo $this->get_field_id('format'); ?>" name="<?php echo $this->get_field_name('format'); ?>" type="text" value="<?php echo esc_attr($format); ?>" /></label><br /><span class="description">Described in the <a href="http://kovshenin.com/wordpress/plugins/retweet-anywhere/#faq">FAQ</a></span></p>
<p><label for="<?php echo $this->get_field_id('width'); ?>"><?php _e('Width (in px):'); ?> <input class="widefat" id="<?php echo $this->get_field_id('width'); ?>" name="<?php echo $this->get_field_name('width'); ?>" type="text" value="<?php echo esc_attr($width); ?>" /></label></p>
<p><label for="<?php echo $this->get_field_id('height'); ?>"><?php _e('Height (in px):'); ?> <input class="widefat" id="<?php echo $this->get_field_id('height'); ?>" name="<?php echo $this->get_field_name('height'); ?>" type="text" value="<?php echo esc_attr($height); ?>" /></label></p>
<?php
	}
}

// Main Retweet Anywhere class
class RetweetAnywhere {

	// Settings
	var $settings = array();
	var $default_settings = array();
	var $shorteners = array();
	
	// Admin notices
	var $notices = array();

	function RetweetAnywhere()
	{
		// Default plugin settings
		$this->default_settings = array(
			"title" => "Retweet This Post",
			"format" => "%s %l",
			"shortener" => "none",
			"placement" => "end",
			"style" => "default",
			"opacity" => "0.5",
			"widget" => "no",
		);
		
		// Load the plugin settings and merge with defaults
		$this->settings = (array) get_option("retweet-anywhere");
		$this->settings = array_merge($this->default_settings, $this->settings);
		
		// Action hooks
		add_action("wp_enqueue_scripts", array(&$this, "wp_enqueue_scripts"));
		
		// AJAX hooks to get message
		add_action("wp_ajax_rta_getmessage", array(&$this, "ajax_getmessage"));
		add_action("wp_ajax_nopriv_rta_getmessage", array(&$this, "ajax_getmessage"));
		
		// Admin system customization (menus, notices, etc)
		add_action("admin_menu", array(&$this, "admin_menu"));
		add_action("admin_init", array(&$this, "admin_init"));
		add_action("admin_notices", array(&$this, "admin_notices"));
		
		// Shortcode and content filters
		if ($this->settings["placement"] != "manual")
			add_filter("the_content", array(&$this, "the_content"));
		add_shortcode("retweet-anywhere", array(&$this, "shortcode"));
		
		// Enable the shortcode for widget text if set in the settings
		if ($this->settings["widget"] == "yes")
		{
			add_filter("widget_text", "do_shortcode");
		}
		
		// Administration notices
		if (empty($this->settings["api_key"]))
			$this->notices[] = "Please configure your Twitter API key in order to use Retweet Anywhere: <a href='options-general.php?page=retweet-anywhere/retweet-anywhere.php'>Plugin Settings</a>";
		if ($this->settings["shortener"] == "bitly" && (empty($this->settings["bitly_username"]) || empty($this->settings["bitly_api_key"])))
			$this->notices[] = "Bit.ly requires a username and a valid API key in order to work. <a href='options-general.php?page=retweet-anywhere/retweet-anywhere.php'>Plugin Settings</a>";
	}
	
	// Widget registration
	function widgets_init()
	{
		register_widget("RetweetAnywhereWidget");
	}
	
	// Settings management
	function admin_init()
	{
		register_setting('retweet-anywhere', 'retweet-anywhere');
	}
	
	// Plugin settings page
	function admin_menu()
	{
		$page = add_submenu_page('options-general.php', 'Retweet Anywhere Settings', 'Retweet Anywhere', 'administrator', __FILE__, array(&$this, "settings_page"));
		add_action("admin_print_scripts-{$page}", array(&$this, "admin_print_scripts"));
	}
	
	// Admin panel scripts
	function admin_print_scripts()
	{
		wp_enqueue_script("retweet-anywhere-admin", plugins_url("/js/admin.js", __FILE__), array("jquery"));
	}
	
	// Settings page content
	function settings_page()
	{
?>
<div class="wrap">
<h2>Retweet Anywhere Settings</h2>

<form method="post" action="options.php">
<?php
	// Nonce fields and settings
	wp_nonce_field('update-options');
	settings_fields('retweet-anywhere');
?>

	<h3>General</h3>
	<p class="description">These are the general settings used by the Retweet Anywhere plugin. Make sure you get the Twitter API key correct.</p>
	<table class="form-table">
		<tr valign="top">
			<th scope="row">Twitter API Key</th>
			<td><input type="text" class="regular-text" name="retweet-anywhere[api_key]" value="<?php echo $this->settings["api_key"]; ?>" /> <span class="description">The Twitter API key (<a href="http://kovshenin.com/wordpress/plugins/retweet-anywhere/#faq">How do I get one?</a>)</span></td>
		</tr>
		<tr valign="top">
			<th scope="row">Popup Title</th>
			<td><input type="text" class="regular-text" name="retweet-anywhere[title]" value="<?php echo $this->settings["title"]; ?>" /> <span class="description">Title goes above your tweet box, eg <code>Retweet This Post</code>.</span></td>
		</tr>
		<tr valign="top">
			<th scope="row">Retweet Format</th>
			<td><input type="text" class="regular-text" name="retweet-anywhere[format]" value="<?php echo $this->settings["format"]; ?>" /> <span class="description">The message format, eg: <code>%s %l (via @kovshenin)</code> described in the <a href="http://kovshenin.com/wordpress/plugins/retweet-anywhere/#faq">FAQ</a>.</span></td>
		</tr>
		<tr valign="top">
			<th scope="row">Enable Widget Shortcode</th>
			<?php $checked = ($this->settings["widget"] == "yes") ? 'checked="checked"' : ''; ?>
			<td><input type="checkbox" name="retweet-anywhere[widget]" <?php echo $checked; ?> value="yes" /> <span class="description">If you're planning to use the shortcode in your text widgets, enable this.</span></td>
			<?php unset($checked); ?>
		</tr>

	</table>
	
	<br /><h3>URL Shortening</h3>
	<p class="description">Pick your favorite shortening service and provide us with your account details.</p>
	<table class="form-table">
		<tr valign="top">
			<th scope="row">URL Shortener</th>
			<td>
				<?php
					$selected[$this->settings["shortener"]] = 'selected="selected"';
				?>
					<select name="retweet-anywhere[shortener]" id="rta-shortener">
						<option value="none" <?php echo @$selected["none"]; ?>>Don't shorten</option>
						<option value="bitly" <?php echo @$selected["bitly"]; ?>>Bit.ly</option>
						<?php							// Additional shorteners							$this->shorteners = apply_filters('retweet-anywhere-shorteners', $this->shorteners);							if ($this->shorteners)								foreach ($this->shorteners as $key => $shortener)									echo '<option value="' . $key . '" ' . @$selected[$key] . '>' . $shortener['name'] . '</option>';						?>					</select>
				<?php
					unset($selected);
				?>
				<span class="description">Pick your favorite URL shortener</span>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">Bit.ly Username</th>
			<td><input type="text" class="regular-text rta-bitly" name="retweet-anywhere[bitly_username]" value="<?php echo $this->settings["bitly_username"]; ?>" /> <span class="description">A valid bit.ly account username (<a href="http://bit.ly/account/register">Register for a bit.ly account</a>)</span></td>
		</tr>
		<tr valign="top">
			<th scope="row">Bit.ly API Key</th>
			<td><input type="text" class="regular-text rta-bitly" name="retweet-anywhere[bitly_api_key]" value="<?php echo $this->settings["bitly_api_key"]; ?>" /> <span class="description">Your bit.ly API key (<a href="http://bit.ly/account/your_api_key/">Where do I find it?</a>)</span></td>
		</tr>
	</table>

	<br /><h3>Look &amp; Feel</h3>
	<p class="description">Customize the look and feel of your retweet button.</p>
	<table class="form-table">
		<tr valign="top">
			<th scope="row">Placement</th>
			<td>
			<?php
				$selected[$this->settings["placement"]] = 'selected="selected"';
			?>
				<select name="retweet-anywhere[placement]">
					<option value="beginning" <?php echo @$selected["beginning"]; ?>>Beginning of post</option>
					<option value="end" <?php echo @$selected["end"]; ?>>End of post</option>
					<option value="manual" <?php echo @$selected["manual"]; ?>>Manual (PHP or Shortcode)</option>
				</select>
			<?php
				unset($selected);
			?>
				<span class="description">Select where you'd like your retweet button to appear. Details described in the <a href="http://kovshenin.com/wordpress/plugins/retweet-anywhere/#faq">FAQ</a>.</span>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">Button Style</th>
			<td>
			<?php
				$selected[$this->settings["style"]] = 'selected="selected"';
			?>
				<select name="retweet-anywhere[style]" id="rta-style">
					<option value="default" <?php echo @$selected["default"]; ?>>Default</option>
					<option value="text" <?php echo @$selected["text"]; ?>>Default Text</option>
					<option value="html" <?php echo @$selected["html"]; ?>>Custom HTML</option>
				</select>
			<?php
				unset($selected);
			?>
				<span class="description">This is a tricky one, default is a blue button, default text is simply a <code>Retweet</code> link. Use custom HTML to write your own.</span>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">Custom HTML</th>
			<td><input type="text" class="regular-text rta-style-html" name="retweet-anywhere[style_html]" value="<?php echo htmlspecialchars($this->settings["style_html"]); ?>" /> <span class="description">Do not include the <code>&lt;a&gt; &lt;/a&gt;</code> tags, they're done for you. Write only what's inside.</span></td>
		</tr>
		<tr valign="top">
			<th scope="row">Background Opacity</th>
			<td>
			<?php
				$selected[$this->settings["opacity"]] = 'selected="selected"';
			?>
				<select name="retweet-anywhere[opacity]">
					<option value="0.9" <?php echo @$selected["0.9"]; ?>>90% Black</option>
					<option value="0.5" <?php echo @$selected["0.5"]; ?>>50% Black</option>
					<option value="0.2" <?php echo @$selected["0.2"]; ?>>20% Black</option>
					<option value="0" <?php echo @$selected["0"]; ?>>0% Black (No Fill)</option>
				</select>
			<?php
				unset($selected);
			?>
				<span class="description">It's sometimes a good idea to fade the background so that retweeters could concentrate on the "Tweet" button ;)</span>
			</td>
		</tr>
	</table>
	<input type="hidden" name="action" value="update" />
	<input type="hidden" name="page_options" value="retweet-anywhere[]" />
	<p class="submit">
		<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
	</p>
</form>
</div>
<?php
	}

	// the_content filter
	function the_content($content)
	{
		// Let's see where we'd like to place the retweet button
		switch ($this->settings["placement"])
		{
			case "beginning":
				$content = "[retweet-anywhere]\r\n" . $content;
				break;
			case "end":
				$content = $content . "\r\n[retweet-anywhere]";
				break;
		}

		return $content;
	}

	// Shortcode
	function shortcode($atts)
	{
		// Extract the attributes
		extract(shortcode_atts(array(
			"html" => false,
			"format" => false,
			"title" => false,
		), $atts));

		if (!$html) $html = $this->get_button();
		if (!$format) $format = "";
		if (!$title) $title = $this->settings["title"];
		$message = '';
		
		// Let's see if we're inside the loop
		global $wp_query;
		if ($wp_query->in_the_loop || is_singular())
		{
			global $post;
			return '<a href="http://twitter.com/?status=' . urlencode($post->post_title . " " . $post->guid) . '" class="retweet-anywhere" title="' . $title . '" rev="' . $format . '" rel="' . $post->ID . '">' . $html . '</a>';
		}
		else
		{
			// Not inside the loop, link to the current page
			return '<a href="http://twitter.com/?status=' . urlencode(get_bloginfo("title") . " " . get_bloginfo("home")) . '" class="retweet-anywhere" title="' . $title . '" rev="' . $format . '" rel="0">' . $html . '</a>';
		}
	}
	
	// Echo the button according to the style
	function get_button()
	{
		switch ($this->settings["style"])
		{
			case "default":
				return '<img src="' . plugins_url("/images/retweet.png", __FILE__) . '" alt="Retweet" />';
				break;
			case "text":
				return 'Retweet';
				break;
			case "html":
				return $this->settings["style_html"];
				break;
		}
	}

	function wp_enqueue_scripts()
	{
		wp_enqueue_style("facebox", plugins_url("/css/facebox.css", __FILE__));
	
		$api_key = $this->settings["api_key"];
		wp_enqueue_script("twitter-anywhere", "http://platform.twitter.com/anywhere.js?id={$api_key}&v=1");
		wp_enqueue_script("facebox", plugins_url("/js/facebox.js", __FILE__), array("jquery"));
		wp_enqueue_script("retweet-anywhere", plugins_url("/js/retweet-anywhere.js", __FILE__), array("jquery", "facebox"));
		wp_localize_script("retweet-anywhere", "RetweetAnywhere", array(
			"ajaxurl" => admin_url('admin-ajax.php'),
			"loadingImage" => plugins_url("/images/facebox/loading.gif", __FILE__),
			"closeImage" => plugins_url("/images/facebox/closelabel.gif", __FILE__),
			"opacity" => $this->settings["opacity"],
			"title" => $this->settings["title"]
		));
	}

	// The AJAX call to retrieve the message
	function ajax_getmessage()
	{
		// Get all the details
		$post_id = $_POST["post_id"];
		$format = $_POST["format"];

		if (empty($format))
			$format = $this->settings["format"];
			
		// If we don't need %s or %l data then we don't query for the post
		if (strpos($format, "%s") === false && strpos($format, "%l") === false)
		{
			$message = $format;
		}
		else
		{
			if ($post_id == 0)
			{
				// If the post_id is 0 then link to the homepage
				$title = get_bloginfo("title");
				$url = get_bloginfo("home");
			}
			else
			{
				// Get the post data
				$post = get_post($post_id);
				$title = $post->post_title;
				$url = get_permalink($post_id);
			}
			
			// Shorten the link if we need to
			if ($this->settings["shortener"] == "bitly")
				$url = $this->shorten($url, $post_id);
			elseif ($this->settings['shortener'] != 'none')			{				$this->shorteners = apply_filters('retweet-anywhere-shorteners', $this->shorteners);				if (function_exists($this->shorteners[$this->settings['shortener']]['callback']))				{					$f = $this->shorteners[$this->settings['shortener']]['callback'];					$url = $f($url);				}			}
			// Format the message
			$replace = array(
				"%s" => $title,
				"%l" => $url
			);

			$message = str_replace(array_keys($replace), array_values($replace), $format);
		}
		
		// Format the response array
		$response = array(
			"message" => $message
		);
		
		// JSON encode, print and die
		echo json_encode($response);
		die();
	}
	
	// Shorten the URL via bit.ly
	function shorten($url, $post_id = 0)
	{
		if ($post_id > 0)
		{
			$short = get_post_meta($post_id, 'rta-shorturl', true);
			if (!empty($short))
				return $short;
		}

		// Let's get the WP_Http class if we don't have one
		if(!class_exists('WP_Http'))
			include_once(ABSPATH . WPINC . '/class-http.php');
		
		// Encode the url
		$url_encoded = urlencode($url);
		
		// Get the bit.ly settings
		$bitly_login = urlencode(trim($this->settings["bitly_username"]));
		$bitly_key = urlencode(trim($this->settings["bitly_api_key"]));
		
		// Init $http and fire the request
		$http = new WP_Http();		
		$result = $http->request("http://api.bit.ly/v3/shorten?login={$bitly_login}&apiKey={$bitly_key}&uri={$url_encoded}&format=json");
		
		if (gettype($result) == "object")
			if (get_class($result) == "WP_Error")
				return $url;
				
		// JSON decode the result body and return the data->url
		$result = json_decode($result["body"]);
		$result = $result->data;
		$shorturl = $result->url;
		
		// Store the shortened URL
		if (!empty($shorturl) && $post_id > 0)
		{
			$post = get_post($post_id);
			//return print_r($post, true);
			if ($post->post_status != 'draft' && $post->post_type != 'revision')
				update_post_meta($post_id, 'rta-shorturl', $shorturl);
		}
		
		return $shorturl;
	}
	
	// Administration notices
	function admin_notices()
	{
		$this->notices = array_unique($this->notices);
		foreach($this->notices as $key => $value)
		{
			echo "<div id='rta-info' class='updated fade'><p><strong>Retweet Anywhere</strong>: " . $value . "</p></div>";
		}
	}
}

// Used for manual mode (PHP)
function retweet_anywhere() {
	echo do_shortcode("[retweet-anywhere]");
}

// Initialize the environment
add_action("init", create_function('', 'global $RetweetAnywhere; $RetweetAnywhere = new RetweetAnywhere();'));
add_action("widgets_init", create_function('', 'return register_widget("RetweetAnywhereWidget");'));