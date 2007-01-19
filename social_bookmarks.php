<?php
/*
Plugin Name: Social Bookmarks
Version: 3.1
Plugin URI: http://www.dountsis.com/
Author: Apostolos Dountsis
Author URI: http://www.dountsis.com
Description: <a href="http://www.dountsis.com/" title="Visit plugin page" target="_blank">Social Bookmarks</a> adds a list of XHTML compliant graphic linkss at the end of your posts that allow your visitors to easily submit them to a number of social bookmarking sites. Use plugin options (In menu Options>Social Bookmarks) to configure the plugin.
*/

class social_bookmarks
{
	var $plugin_dir;
	var $social_places;
	var $default_settings;
	var $current_settings;
	
	function social_bookmarks()
	{
		// Initialise variables
		$this->plugin_dir = get_option('siteurl').'/wp-content/plugins/social_bookmarks/';
		
		// Default Settings
		$this->default_settings = array('sbb_sites' => 'delicious|digg|furl|blinklist|reddit|feed_me|technorati|yahoo|newsvine|socializer|magnolia|stumbleupon|google|rawsugar|squidoo|spurl|blinkbits|netvouz|rojo|blogmarks|shadows|simpy|comments|scuttle|bloglines|tailrank',
							'sbb_label' => 'Bookmark to:',
							'sbb_target' => 'new',
							'sbb_pages_excluded' => 'none',
							'sbb_lines' => 2,
							'sbb_display' => 3);
		
		// The Sites
		$xml_sites = $this->read_xml(dirname(__FILE__)."/sites.xml");

		foreach($xml_sites as $i => $value)
		{
			$key = $value['key'];
			$name = $value['name'];
			$img = $value['img'];
			$url = $value['url'];
			$url = str_replace('&', '&amp;', $url);	
			
			$this->social_places[$key] = array('img' => $img, 'url' => $url , 'name' => $name);
		}
							
		// Manage form POSTs
		if($_POST)
		{
			if($_POST['sbb_sites'])
			{
				unset($_POST['sbb_sites']);
				$this->update_sites($_POST);
			}
			elseif($_POST['sbb_general'])
			{
				unset($_POST['sbb_general']);
				$this->update_other($_POST);
			}
		}

		// Set Default settings as current if no options in wpdb
		// Otherwise populate current settings from wpdb
		foreach($this->default_settings  as $label => $value)
		{
			if(!get_option($label))
			{
				add_option($label, $value);
				$this->current_settings[$label] = $value;
			}
			else
			{
				$this->current_settings[$label] = get_option($label);
			}
		}
		
		// Add Admin Menu
		add_action('admin_menu', array(&$this, 'admin_menu'));
		
		// Display social_bar in the_content
		add_filter('the_content', array(&$this, 'display_bar'));
		// Display social_bar in the_excerpt
		add_filter('the_excerpt', array(&$this, 'display_bar'));

		// Display social_bar in the_actions
		add_filter('comment_post', array(&$this, 'display_bar'));
		
		// Adds the CSS to the header
		add_action('wp_head', array(&$this, 'include_header'));
	}
	
	function include_header()
	{
		print("<link rel=\"stylesheet\" type=\"text/css\" media=\"screen\" href=\"".$this->plugin_dir."social_bookmarks.css\" />\n");
	}
	
	function update_sites($data)
	{
		$option_array = array();

		// Compile option for wpdb
		foreach($this->social_places as $site => $settings)
		{
			if(array_key_exists($site, $data))
			{
				$option_array[] = "$site";
			}
		}
		
		$option = implode('|', $option_array);

		// Store in wpdb
		if(get_option('sbb_sites'))
		{
			update_option('sbb_sites', $option);
		}
		else
		{
			add_option('sbb_sites', $option);
		}
	}
	
	function update_other($data)
	{
		global $wpdb;
	
		if(!array_key_exists('sbb_pages_excluded',$data))
		{
			$data['sbb_pages_excluded'] = 'none';
		}

		if($data)
		{
			foreach($data as $name => $value)
			{
				if($name == 'sbb_label')
				{
					$value = $wpdb->escape($value);
				}

				if(get_option($name) != $value)				
//				if(get_option($name) and get_option($name) != $value)
				{
//					print("update $name - $value <br />");
					update_option($name, $value);
				}
/*				elseif(!get_option($name) )
				{
					add_option($name, $value);
				}
*/
			}
		}
	}
	
	// Manage Admin Options
	function admin_menu()
	{
		// Add admin page to the Options Tab of the admin section
		add_options_page('Social Bookmarks Options', 'Social Bookmarks', 8, __FILE__, array(&$this, 'plugin_options'));
		
		// Check if the options exists on the database and add them if not
	}
	
	// Admin page
	function plugin_options()
	{
		print('<div class="wrap">');
		print('<h2>Social Bookmarks</h2>');
		
  		// Other Options
  		$this->option_other();
		
  		// Sites Option
  		$this->option_sites();
  		
  		// Debug Screen
//  		$this->debug_section();
		print('</div>');
	}
	
	function option_sites()
	{
		$user_option = explode('|', $this->current_settings['sbb_sites']);

    		// Enable/Disable Sites
		print('<div class="wrap">');
		print('<h2>Social Bookmarking Sites</h2>');
		print('<p>Select the links you want to display on your site:</p>');
		print('<form id="sites" style="padding-left:25px;" method="post">');

		$i = 0;
		$html_left = $html_right = '';
		foreach($this->social_places as $site => $settings)
		{
			$site_name = $settings['name'];
			$site_img = "<img src=\"".$this->plugin_dir.$settings['img']."\" title=\"$site_name\" alt=\"$site_name\" border=\"0\" align=\"top\" />\n";

			$html = "<p>\n";
			if(in_array($site, $user_option))
			{
				$html .= "<input type=\"checkbox\" name=\"$site\" value=\"Y\" checked />\n";
			}
			else
			{
				$html .= "<input type=\"checkbox\" name=\"$site\" value=\"Y\" />\n";
			}
			$html .= " $site_img <em>$site_name</em>";		
			$html .= "</p>\n";

			if($this->is_even($i))
			{
				$html_left .= $html;
			}
			else
			{
				$html_right .= $html;
			}

			$i++;
		}
		print('<div style="float:left;width:50%;">');
		print($html_left);
		print('</div>');

		print('<div style="float:right;width:50%;">');
		print($html_right);
		print('</div>');

		// Hidden var to assist identfying the form POST
		print('<p>&nbsp;</p>');
		print('<input type="hidden" name="sbb_sites" value="sbb_sites" />');
		print('<p><input type="submit" value="Save  &raquo;"></p>');
		print('</form>');
		print('</div>');	
	}
	
	function option_other()
	{
  		// Other Options
		print('<div class="wrap">');
   		print('<h2>Options</h2>');

   		print('<form style="padding-left:25px;" method="post">');
   			
		// Site _target
		$html = "<p>";
		$html .= "Open links in ";
		$html .= "<select name=\"sbb_target\" >\n";
		if($this->current_settings['sbb_target'] == 'current')
		{
			$html .= "<option value=\"current\" selected>current</option>\n";
			$html .= "<option value=\"new\">new</option>\n";
		}
		else
		{
			$html .= "<option value=\"current\">current</option>\n";
			$html .= "<option value=\"new\" selected>new</option>\n";
		}
		$html .= "</select>\n";
		$html .= " window.\n";
		$html .= "</p>";
		
		// Label
		$html .= '<p>Display Title: <input type="text" name="sbb_label" value="'.$this->current_settings['sbb_label'].'" /></p>';

		// Lines
		$html .= "<p>";
		$html .= "Display links in ";
		$html .= "<select name=\"sbb_lines\" >\n";
		if($this->current_settings['sbb_lines'] == 1)
		{
			$html .= "<option value=\"1\" selected>1</option>\n";
			$html .= "<option value=\"2\">2</option>\n";
		}
		else
		{
			$html .= "<option value=\"1\">1</option>\n";
			$html .= "<option value=\"2\" selected>2</option>\n";
		}
		$html .= "</select>\n";
		$html .= " line(s).\n";
		$html .= "</p>";

		// Position the plugin in the blog
		// possible options: 1:Everywhere, 2: single page only 3: Actions (trackback, edit, comments rss)
		
		$html .= "<p>";
		$html .= "Display the plugin ";
		$html .= "<select name=\"sbb_display\" >\n";
		if($this->current_settings['sbb_display'] == 1)
		{
			$html .= "<option value=\"1\" selected>in the blog listing (index.php)</option>\n";
			$html .= "<option value=\"2\">when a single post is viewed</option>\n";
			$html .= "<option value=\"3\">in both single post &amp; blog listing</option>\n";
		}
		elseif($this->current_settings['sbb_display'] == 2)
		{
			$html .= "<option value=\"1\">in the blog listing (index.php)</option>\n";
			$html .= "<option value=\"2\" selected>when a single post is viewed</option>\n";
			$html .= "<option value=\"3\">in both single post &amp; blog listing</option>\n";
		}
		elseif($this->current_settings['sbb_display'] == 3)
		{
			$html .= "<option value=\"1\">in the blog listing (index.php)</option>\n";
			$html .= "<option value=\"2\">when a single post is viewed</option>\n";
			$html .= "<option value=\"3\" selected>in both single post &amp; blog listing</option>\n";
		}
		$html .= "</select>\n";
		$html .= ".\n";
		$html .= "</p>";

		// Exclude these pages
		$html.= '<p>Do not display the links on the selected pages below:</p>';
		$html.='<select id="sbb_pages_excluded" name="sbb_pages_excluded[]" size="5" multiple="true">';
		
		$site_pages = $this->get_pages();
		$exclude_selected = $this->current_settings['sbb_pages_excluded'];

		if($site_pages)
		{
			foreach($site_pages as $page)
			{
				$s = '';
				if($exclude_selected and $exclude_selected != 'none')
				{
					if(in_array($page['id'], $exclude_selected))
					{
						$s = 'selected';
					}
				}
				$html .= "<option name=\"page_{$page['id']}\" value=\"{$page['id']}\" $s>{$page['post_title']}</option>\n";
			}
		}
		$html .= '</select>';
		$html .= '<ul>';
		$html .= '<li>Hold Ctrl to select more than one page.</li>';
		$html .= '<li>Hold Shift to select a region of pages.</li>';
		$html .= '<li>If you have selected more than one page<br /> then hold the Ctrl key to de-select individual pages.</li>';
		$html .= '<li>In OS X, use the Apple key instead of Ctrl<br /> to perform the described above operations.</li>';
		$html .= '</ul>';

		// Save General options	
		// Hidden var to assist identfying the form POST
		$html .= '<input type="hidden" name="sbb_general" value="sites" />';
		$html .= '<p><input type="submit" value="Save  &raquo;"></p>';
		$html .= '</form>';
		$html .= '</div>';

		// Display form
		print($html);
	}

	function get_item($site_key, $settings, $output = 1)
	{
		// Post Permalink
		$permalink = get_permalink();
		// Post Title
		$title = the_title('', '', false);
		$title_enc = urlencode($title);

		// Post Alternative (Title) description
		$target_desc = "Add '$title' to ".$settings['name'];

		// Populate the url with the article variables
		$target_href = str_replace('{title}', $title_enc, $settings['url']);
		$target_href = str_replace('{link}', $permalink, $target_href);	

		$target_img = "<img src=\"".$this->plugin_dir.$settings['img']."\" title=\"$target_desc\" alt=\"$target_desc\" />";

		if($this->current_settings['sbb_target'] != 'new')
		{
			$target_url = "<a class=\"social_img\" href=\"$target_href\" title=\"$target_desc\">$target_img</a>";
		}
		else
		{
			$target_url = "<a class=\"social_img\" onclick=\"window.open(this.href, '_blank', 'scrollbars=yes,menubar=no,height=600,width=750,resizable=yes,toolbar=no,location=no,status=no'); return false;\" href=\"$target_href\" title=\"$target_desc\">$target_img</a>";
		}
		
		// Return result
		if($output)
		{
			print($target_url);
			return;
		}
		else
		{
			return($target_url);
		}
	}

	function render_plugin()
	{
		global $id;

		$user_sites = explode('|', $this->current_settings['sbb_sites']);
		
		if($this->current_settings['sbb_lines'] > 1)
		{
			$sites_per_line = ceil(sizeof($user_sites) / $this->current_settings['sbb_lines']);
		}
		else
		{
			$sites_per_line = 100;
		}
		
		$html = "<!-- Social Bookmarks BEGIN -->";
		$html .= "<div class=\"social_bookmark\">";
//		$html .= "<span style=\"padding:1px;\">&nbsp;</span>";
//		$html .= "<span style=\"display:block;margin-left:auto;margin-right:auto;text-align:center;\">";
		$html .= "<em>{$this->current_settings['sbb_label']}</em><br />";

		$i  = 1;
		foreach($this->social_places as $site => $settings)
		{
			if(in_array($site, $user_sites))
			{
				$html .= $this->get_item($site, $settings, 0);
				if($i == $sites_per_line)
				{
					$html .= '<br />';
				}
/*				else
				{
					$html .= "&nbsp;";
				}
*/
				$i++;
			}
		}

//		$html .= "</span>";
		$html .= "</div>\n";
		$html .= "<!-- Social Bookmarks END -->";
		
		return $html;
	}

	function display_bar($content)
	{
		global $id;
		$html = '';
		
		if(is_array($this->current_settings['sbb_pages_excluded']))
		{
			if(!in_array($id, $this->current_settings['sbb_pages_excluded']))
			{		
				$html.= $this->render_plugin();
			}
		}
		else
		{
			$html .= $this->render_plugin();
		}
		
		if(substr($content, 0, 12) == '<p>Apostolos')
		{
			return($content);
		}
		else
		{
			switch($this->current_settings['sbb_display'])
			{
				case 1:
					// Only in blog listing (not single page)
					if(is_single())
					{
						return($content);
					}
					else
					{
						return($content.$html);
					}
					break;
				case 2: 
					// Single Page
					if(!is_single())
					{
						return($content);
					}
					else
					{
						return($content.$html);
					}
					break;
				case 3: 
					return($content.$html);
					break;
				default: 
					return($content.$html);
					break;
			}
		}
	}
	
	function debug_section()
	{
		print('<div class="wrap">');
    	print('<h2>Debug</h2>');

    	print('<p>Current Settings</p>');
    	print('<pre>');
    	print_r($this->current_settings);
    	print('<pre>');
    	
		print('Current directory: '.dirname(__FILE__));
		
    	if($_POST)
    	{
			print('<p>POST Array</p>');
			print('<pre>');
			print_r($_POST);
			print('<pre>');
    	}
    	
    	print("<p>Sites</p>");
    	print('<pre>');
    	print_r($this->social_places);
    	print('<pre>');    	
    	
    	print('</div>');
	}

	function read_xml($filename) 
	{
		$site = array();

		// read the XML file
		$data = implode("", file($filename));
		$parser = xml_parser_create();
		xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
		xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
		xml_parse_into_struct($parser, $data, $values, $tags);
		xml_parser_free($parser);

	   // loop through the structures
	   foreach ($tags as $key=>$val) 
	   {
		   if ($key == "site")
		   {
			   $site_ranges = $val;
			   // each contiguous pair of array entries are the 
			   // lower and upper range for each site definition
			   for ($i=0; $i < count($site_ranges); $i+=2)
			   {
				   $offset = $site_ranges[$i] + 1;
				   $len = $site_ranges[$i + 1] - $offset;

				   $site[] = $this->parse_site(array_slice($values, $offset, $len));
			   }
		   }
		   else
		   {
			   continue;
		   }
	   }
	   return $site;
	}

	function parse_site($site_values) 
	{
		$site = array();
		
		for ($i=0; $i < count($site_values); $i++)
		{
			$site[$site_values[$i]['tag']] = $site_values[$i]['value'];
		}

		return($site);
	}

	
	function is_page($id)
	{
		global $wpdb;
		
		$status = $wpdb->get_var("select post_status
							from $wpdb->posts
							where id = '$id'");

		if($status == 'static')
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	function is_even($number)
	{
		if ($number % 2 == 0 )
		{
		        // The number is even
			return true;
		}
    		else
		{
		        // The number is odd
		        return false;
		}
	}
	
	function get_pages()
	{
		global $wpdb;
		
		$pages = $wpdb->get_results("select id, post_title
							from $wpdb->posts
							where post_status = 'static'
							order by post_title asc", 'ARRAY_A');
		return $pages;
	}
}

// Instantiate social_bookmarks class
$sb &= new social_bookmarks();

// For compatibility with version 1.x and 2.x
function get_social_bar()
{}

?>
