<?php
/*
Plugin Name: Category Posts Widget
Plugin URI: http://mkrdip.me/category-posts-widget
Description: Adds a widget that shows the most recent posts from a single category.
Author: Mrinal Kanti Roy
Version: 4.6.1
Author URI: http://mkrdip.me
*/

namespace categoryPosts;

// Don't call the file directly
if ( !defined( 'ABSPATH' ) ) exit;

define( 'CAT_POST_PLUGINURL', plugins_url(basename( dirname(__FILE__))) . "/");
define( 'CAT_POST_PLUGINPATH', dirname(__FILE__) . "/");
define( 'CAT_POST_VERSION', "4.6.1");

/*
 * Iterate over all the widgets active at the page and call the callback for them
 * 
 * callback - accepts the widget settings, return true to continue iteration or false to stop
*/
function iterator($id_base,$class,$callback) {
	global $wp_registered_widgets;
	$sidebars_widgets = wp_get_sidebars_widgets();

	if ( is_array($sidebars_widgets) ) {
		foreach ( $sidebars_widgets as $sidebar => $widgets ) {
			if ( 'wp_inactive_widgets' === $sidebar || 'orphaned_widgets' === substr( $sidebar, 0, 16 ) ) {
				continue;
			}

			if ( is_array($widgets) ) {
				foreach ( $widgets as $widget ) {
					$widget_base = _get_widget_id_base($widget);
					if ( $widget_base == $id_base )  {
						$widgetclass = new $class();
						$allsettings = $widgetclass->get_settings();
						$settings = isset($allsettings[str_replace($widget_base.'-','',$widget)]) ? $allsettings[str_replace($widget_base.'-','',$widget)] : false;
						if (!$callback($settings))
							return;
					}
				}
			}
		}
	}
}

/*
	Check if CSS needs to be added to support cropping by traversing all active widgets on the page
	and checking if any has cropping enabled.
	
	Return: false if cropping is not active, false otherwise
*/
function cropping_active($id_base,$class) {
	$ret = false;
	
	iterator($id_base, $class, function ($settings) use (&$ret) {
		if (isset($settings['use_css_cropping'])) { // checks if cropping is active
			$ret = true;
			return false; // stop iterator
		} else
			return true; // continue iteration to next widget
	});
	
	return $ret;
}

/*
	Check if CSS needs to be enqueued by traversing all active widgets on the page
	and checking if they all have disabled CSS.
	
	Return: false if CSS should not be enqueued, true if it should
*/
function should_enqueue($id_base,$class) {
	$ret = false;
	
	iterator($id_base, $class, function ($settings) use (&$ret) {
		if (!isset($settings['disable_css'])) { // checks if css disable is not set
			$ret = true;
			return false; // stop iterator
		} else
			return true; // continue iteration to next widget
	});
	
	return $ret;
}

/**
 * Register our styles
 *
 * @return void
 */
add_action( 'wp_enqueue_scripts', __NAMESPACE__.'\widget_styles' );

function wp_head() {
	if (cropping_active('category-posts',__NAMESPACE__.'\Widget')) {
?>
<style type="text/css">
.cat-post-item .cat-post-css-cropping span {
	overflow: hidden;
	display:inline-block;
}
</style>
<?php	
	}
}

add_action('wp_head',__NAMESPACE__.'\wp_head');

function widget_styles() {
	$enqueue = should_enqueue('category-posts',__NAMESPACE__.'\Widget');
	if ($enqueue) {
		wp_register_style( 'category-posts', CAT_POST_PLUGINURL . 'cat-posts.css',array(),CAT_POST_VERSION );
		wp_enqueue_style( 'category-posts' );
	}
}

/*
 *	Initialize select2
 */	
function load_select2_scripts_footer() {
?>
	<script type="text/javascript">
		<?php if (!is_customize_preview()) { // only for widgets page ?>
		jQuery(document).ready(function () {
			jQuery(".cphtml").select2({
				width:'100%',
				placeholder:'<?php _e('Select many HTML elements','categorypostspro')?>',
				DropdownAdapter:'DropdownSearch'
			});
			jQuery('#widget-list .cphtml').select2('destroy');

			jQuery(document).on('widget-added widget-updated', function(event,widget){
				widget.find('.cphtml').select2({
					width:'100%',
					placeholder:'<?php _e('Select as many as needed','categoryposts')?>',
					DropdownAdapter:'DropdownSearch'
				});	
			});	
            jQuery(".cpwpany").on("select2:select", function (e) {
                if (e.params.data.id == '0') { // any was selected remove rest
                    jQuery(this).val(['0']).trigger("change");
                } else {
                    var v = jQuery(this).val();
                    var i = v.indexOf('0');
                    if (i > -1) {
                        v.splice(i,1);
                        jQuery(this).val(v).trigger("change");
                    }
                }
            });
            jQuery(".cpwpany").on("select2:unselect", function (e) {
                var v = jQuery(this).val();
                if (v == null) {
                    jQuery(this).val(['0']).trigger("change");
                }
            });
		});
		<?php } else { // for customizer ?>
		jQuery(document).on('expanded widget-added', function(){
			jQuery(this).find('.widget-rendered.expanded .cphtml').select2({
				width:'100%',
				placeholder:'<?php _e('Select as many as needed','categoryposts')?>',
				DropdownAdapter:'DropdownSearch'
			});	
		});
        jQuery(".cpwpany").on("select2:select", function (e) {
            if (e.params.data.id == '0') { // any was selected remove rest
                jQuery(this).val(['0']).trigger("change");
            } else {
                var v = jQuery(this).val();
                var i = v.indexOf('0');
                if (i > -1) {
                    v.splice(i,1);
                    jQuery(this).val(v).trigger("change");
                }
            }
        });
        jQuery(".cpwpany").on("select2:unselect", function (e) {
            var v = jQuery(this).val();
            if (v == null) {
                jQuery(this).val(['0']).trigger("change");
            }
        });
		<?php } ?>
	</script>

	<style>
		<?php if (isset($GLOBALS['wp_customize'])) { // this abomination need only for customizer ?> 
		.select2-container 
		{
			z-index:500001;
		}
		<?php } ?>
		.cphtml 
		{
			width:58% !important;
		}
		.select2-search-field
		{
			width: 30px;
		}
		.select2-container li.select2-search
		{
			float: initial !important;
			margin: 0;
		}
		.select2-container input
		{
			width: 100% !important
		}
	</style>
<?php
}

/*
	Enqueue select2 related JS and CSS for widget manipulation on the widget admin screen and costumizer
	but due to customizer bugs only for 4.4 and above for the customizer
*/	
function admin_scripts($hook) {
 
	if ($hook == 'widgets.php') { // enqueue only for widget admin and customizer
		if (version_compare( $GLOBALS['wp_version'], '4.4', '>=' ) || !isset($GLOBALS['wp_customize'])) {
			
			// select2
			wp_enqueue_script( 'select2-css', plugins_url( 'js/select2-4.0.1/js/select2.min.js' , __FILE__ ), array( 'jquery' ),'4.0.1' );
			wp_enqueue_style( 'select2-js', plugins_url( 'js/select2-4.0.1/css/select2.min.css' , __FILE__ ) );
			
			add_action('admin_print_scripts',__NAMESPACE__.'\load_select2_scripts_footer',100);

		}
		
		// control open and close the widget section
        wp_register_script( 'category-posts-widget-admin-js', CAT_POST_PLUGINURL.'/js/admin/category-posts-widget.js',array('jquery'),CAT_POST_VERSION,true );
        wp_enqueue_script( 'category-posts-widget-admin-js' );	
	}	
}

add_action('admin_enqueue_scripts', __NAMESPACE__.'\admin_scripts'); // "called on widgets.php and costumizer since 3.9


/**
 * Load plugin textdomain.
 *
 */
add_action( 'admin_init', __NAMESPACE__.'\load_textdomain' );

function load_textdomain() {
  load_plugin_textdomain( 'categoryposts', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' ); 
}

/**
 * Add styles for widget sections
 *
 */
add_action( 'admin_print_styles-widgets.php', __NAMESPACE__.'\admin_styles' );
 
function admin_styles() {
?>
<style>
.category-widget-cont h4 {
    padding: 12px 15px;
    cursor: pointer;
    margin: 5px 0;
    border: 1px solid #E5E5E5;
}
.category-widget-cont h4:first-child {
	margin-top: 10px;	
}
.category-widget-cont h4:last-of-type {
	margin-bottom: 10px;
}
.category-widget-cont h4:after {
	float:right;
	font-family: "dashicons";
	content: '\f140';
	-ms-transform: translate(-1px,1px);
	-webkit-transform: translate(-1px,1px);
	-moz-transform: translate(-1px,1px);
	transform: translate(-1px,1px);
	-ms-transition: all 600ms;
	-webkit-transition: all 600ms;
	-moz-transition: all 600ms;
    transition: all 600ms;	
}	
.category-widget-cont h4.open:after {
	-ms-transition: all 600ms;
	-webkit-transition: all 600ms;
	-moz-transition: all 600ms;
    transition: all 600ms;	
	-ms-transform: rotate(180deg);
    -webkit-transform: rotate(180deg);
	-moz-transform: rotate(180deg);
	transform: rotate(180deg);
}	
.category-widget-cont > div {
	display:none;
	overflow: hidden;
}	
.category-widget-cont > div.open {
	display:block;
}	
</style>
<?php
}

/**
 * Get image size
 *
 * $thumb_w, $thumb_h - the width and height of the thumbnail in the widget settings
 * $image_w,$image_h - the width and height of the actual image being displayed
 *
 * return: an array with the width and height of the element containing the image
 */
function get_image_size( $thumb_w,$thumb_h,$image_w,$image_h) {
	
	$image_size = array('image_h' => $thumb_h, 'image_w' => $thumb_w, 'marginAttr' => '', 'marginVal' => '');
	$relation_thumbnail = $thumb_w / $thumb_h;
	$relation_cropped = $image_w / $image_h;
	
	if ($relation_thumbnail < $relation_cropped) {
		// crop left and right site
		// thumbnail width/height ration is smaller, need to inflate the height of the image to thumb height
		// and adjust width to keep aspect ration of image
		$image_size['image_h'] = $thumb_h;
		$image_size['image_w'] = $thumb_h / $image_h * $image_w; 
		$image_size['marginAttr'] = 'margin-left';
		$image_size['marginVal'] = ($image_size['image_w'] - $thumb_w) / 2;
	} else {
		// crop top and bottom
		// thumbnail width/height ration is bigger, need to inflate the width of the image to thumb width
		// and adjust height to keep aspect ration of image
		$image_size['image_w'] = $thumb_w;
		$image_size['image_h'] = $thumb_w / $image_w * $image_h; 
		$image_size['marginAttr'] = 'margin-top';
		$image_size['marginVal'] = ($image_size['image_h'] - $thumb_h) / 2;
	}
	
	return $image_size;
}

/**
 * Category Posts Widget Class
 *
 * Shows the single category posts with some configurable options
 */
class Widget extends \WP_Widget {

	function __construct() {
		$widget_ops = array('classname' => 'cat-post-widget', 'description' => __('List single category posts','categoryposts'));
		parent::__construct('category-posts', __('Category Posts','categoryposts'), $widget_ops);
	}

	/*
		override the thumbnail htmo to insert cropping when needed
	*/
	function post_thumbnail_html($html, $post_id, $post_thumbnail_id, $size, $attr){
		if ( empty($this->instance['thumb_w']) || empty($this->instance['thumb_h']))
			return $html; // bail out if no full dimensions defined

		$meta = image_get_intermediate_size($post_thumbnail_id,$size);
		
		if ( empty( $meta )) {		
			$post_img = wp_get_attachment_metadata($post_thumbnail_id, $size);
			$meta['file'] = basename( $post_img['file'] );
		}

		$origfile = get_attached_file( $post_thumbnail_id, true); // the location of the full file
		$file =	dirname($origfile) .'/'.$meta['file']; // the location of the file displayed as thumb
		list( $width, $height ) = getimagesize($file);  // get actual size of the thumb file

		if ($width / $height == $this->instance['thumb_w'] / $this->instance['thumb_h']) {
			// image is same ratio as asked for, nothing to do here as the browser will handle it correctly
			;
		} else if (isset($this->instance['use_css_cropping'])) {
			$image = get_image_size($this->instance['thumb_w'],$this->instance['thumb_h'],$width,$height);			

			// replace srcset
			$array = array();
			preg_match( '/width="([^"]*)"/i', $html, $array ) ;
			$pattern = "/".$array[1]."w/";
			$html = preg_replace($pattern, $image['image_w']."w", $html);			
			// replace size
			$pattern = "/".$array[1]."px/";
			$html = preg_replace($pattern, $image['image_w']."px", $html);						
			// replace width
			$pattern = "/width=\"[0-9]*\"/";
			$html = preg_replace($pattern, "width='".$image['image_w']."'", $html);
			// replace height
			$pattern = "/height=\"[0-9]*\"/";
			$html = preg_replace($pattern, "height='".$image['image_h']."'", $html);			
			// set margin
			$html = str_replace('<img ','<img style="'.$image['marginAttr'].':-'.$image['marginVal'].'px;height:'.$image['image_h']
				.'px;clip:rect(auto,'.($this->instance['thumb_w']+$image['marginVal']).'px,auto,'.$image['marginVal']
				.'px);width:auto;max-width:initial;" ',$html);
			// wrap span
			$html = '<span style="width:'.$this->instance['thumb_w'].'px;height:'.$this->instance['thumb_h'].'px;">'
				.$html.'</span>';
		} else {
			// if use_css_cropping not used
			// no interface changes: leave without change
		}
		return $html;
	}
	
	/*
		wrapper to execute the the_post_thumbnail with filters
	*/
	function the_post_thumbnail($size= 'post-thumbnail',$attr='') {
		add_filter('post_thumbnail_html',array($this,'post_thumbnail_html'),1,5);
		the_post_thumbnail($size,$attr);
		remove_filter('post_thumbnail_html',array($this,'post_thumbnail_html'),1,5);
	}
	
	/**
	 * Excerpt more link filter
	 */
	function excerpt_more_filter($more) {
		return ' <a class="cat-post-excerpt-more" href="'. get_permalink() . '">' . esc_html($this->instance["excerpt_more_text"]) . '</a>';
	}

	/**
	 * Explicite excerpt
	 */	
	function explicite_the_excerpt($text) {
		return apply_filters('the_content', $text);
	}
	
	/**
	 * Excerpt allow HTML
	 */
	function allow_html_excerpt($text) {
		global $post, $wp_filter;
		$new_excerpt_length = ( isset($this->instance["excerpt_length"]) && $this->instance["excerpt_length"] > 0 ) ? $this->instance["excerpt_length"] : 55;
		if ( '' == $text ) {
			$text = get_the_content('');
			$text = strip_shortcodes( $text );
			$text = apply_filters('the_content', $text);
			$text = str_replace('\]\]\>', ']]&gt;', $text);
			$text = preg_replace('@<script[^>]*?>.*?</script>@si', '', $text);
			$cphtml = array(
				'&lt;a&gt;',
				'&lt;br&gt;',
				'&lt;em&gt;',
				'&lt;i&gt;',
				'&lt;ul&gt;',
				'&lt;ol&gt;',
				'&lt;li&gt;',
				'&lt;p&gt;',
				'&lt;img&gt;',
				'&lt;script&gt;',
				'&lt;style&gt;',								
				'&lt;video&gt;',
				'&lt;audio&gt;'
			);
			$allowed_HTML = "";
			foreach ($cphtml as $index => $name) {
				if (in_array((string)($index),$this->instance['excerpt_allowed_elements'],true))
					$allowed_HTML .= $cphtml[$index];
			}			
			$text = strip_tags($text, htmlspecialchars_decode($allowed_HTML));
			$excerpt_length = $new_excerpt_length;		

			if( !empty($this->instance["excerpt_more_text"]) ) {
				$excerpt_more = $this->excerpt_more_filter($this->instance["excerpt_more_text"]); 
			}else if($filterName = key($wp_filter['excerpt_more'][10])) {
				$excerpt_more = $wp_filter['excerpt_more'][10][$filterName]['function'](0);
			}else {
				$excerpt_more = '[...]';
			}
			
			$words = explode(' ', $text, $excerpt_length + 1);
			if (count($words)> $excerpt_length) {
				array_pop($words);
				array_push($words, $excerpt_more);
				$text = implode(' ', $words);
			}
		}

		return '<p>' . $text . '</p>';
	}
	
	/**
	 * Calculate the HTML for showing the thumb of a post item.
     * Expected to be called from a loop with globals properly set
	 *
	 * @param  array $instance Array which contains the various settings
	 * @return string The HTML for the thumb related to the post
     *
     * @since 4.6
	 */
	function show_thumb($instance) {
        $ret = '';
        
		if ( isset( $instance["thumb"] ) &&
				has_post_thumbnail() ) {
			$use_css_cropping = isset($this->instance['use_css_cropping']) ? "cat-post-css-cropping" : "";
            $class = '';
            if( !isset( $this->instance['disable_css'] )) { 
                if( isset($this->instance['thumb_hover'] )) {
                    $class = "class=\"cat-post-thumbnail " . $use_css_cropping ." cat-post-" . $instance['thumb_hover'] . "\"";
                } else {
                    $class = "class=\"cat-post-thumbnail " . $use_css_cropping . "\"";
                }
            } 
            $title_args = array('echo'=>false);
			$ret .= '<a '.$class . ' href="'.get_the_permalink().'" title="'.the_title_attribute($title_args).'">';
            $ret .= $this->the_post_thumbnail( array($this->instance['thumb_w'],$this->instance['thumb_h'])); 
			$ret .= '</a>';
		}
        
        return $ret;
	}
	
	/**
	 * Calculate the wp-query arguments matching the filter settings of the widget
	 *
	 * @param  array $instance Array which contains the various settings
	 * @return array The array that can be fed to wp_Query to get the relevant posts
     *
     * @since 4.6
	 */
    function queryArgs($instance) {
		$valid_sort_orders = array('date', 'title', 'comment_count', 'rand');
		if ( isset($instance['sort_by']) && in_array($instance['sort_by'],$valid_sort_orders) ) {
			$sort_by = $instance['sort_by'];
			$sort_order = (bool) isset( $instance['asc_sort_order'] ) ? 'ASC' : 'DESC';
		} else {
			// by default, display latest first
			$sort_by = 'date';
			$sort_order = 'DESC';
		}
		
		// Get array of post info.
		$args = array(
			'orderby' => $sort_by,
			'order' => $sort_order
		);
        
        if (isset($instance["num"])) 
            $args['showposts'] = (int) $instance["num"];
		
        if (isset($instance["cat"])) 
            $args['cat'] = (int) $instance["cat"];

		$current_post_id = get_the_ID();
        if (!$current_post_id && isset( $instance['exclude_current_post'] )) 
            $args['post__not_in'] = $current_post_id;

        if( isset( $instance['hideNoThumb'] ) ) {
			$args = array_merge( $args, array( 'meta_query' => array(
					array(
					 'key' => '_thumbnail_id',
					 'compare' => 'EXISTS' )
					)
				)	
			);
		}
        
        return $args;
    }
    
	/**
	 * Calculate the HTML of the title based on the widget settings
	 *
     * @param  string $before_title The sidebar configured HTML that should come
     *                              before the title itself
     * @param  string $after_title The sidebar configured HTML that should come
     *                              after the title itself
	 * @param  array $instance Array which contains the various settings
	 * @return string The HTML for the title area
     *
     * @since 4.6
	 */
    function titleHTML($before_title,$after_title,$instance) {
        $ret = '';
        
		// If not title, use the name of the category.
		if( !isset($instance["title"]) || !$instance["title"] ) {
            $instance["title"] = '';
            if (isset($instance["cat"])) {
                $category_info = get_category($instance["cat"]);
                if ($category_info)
                    $instance["title"] = $category_info->name;
            }
		}

        if( !isset ( $instance["hide_title"] ) ) {
            $ret = $before_title;
            if( isset ( $instance["title_link"]) && isset($instance["cat"]) && (get_category($instance["cat"]) != null))  {
                $ret .= '<a href="' . get_category_link($instance["cat"]) . '">' . esc_html(apply_filters( 'widget_title', $instance["title"] )) . '</a>';
            } else {
                $ret .= esc_html(apply_filters( 'widget_title', $instance["title"] ));
            }
            $ret .= $after_title;
        }
        
        return $ret;
    }
    
	/**
	 * Calculate the HTML of the footer based on the widget settings
	 *
	 * @param  array $instance Array which contains the various settings
	 * @return string The HTML for the footer area
     *
     * @since 4.6
	 */
    function footerHTML($instance) {
        $ret = '';
        
        if( isset ( $instance["footer_link"] ) && $instance["footer_link"] && isset($instance["cat"]) && (get_category($instance["cat"]) != null) ) {
            $ret = "<a";
                if( !isset( $instance['disable_css'] ) ) { $ret.= " class=\"cat-post-footer-link\""; }
            $ret .= " href=\"" . get_category_link($instance["cat"]) . "\">" . esc_html($instance["footer_link"]) . "</a>";
        }
        
        return $ret;
    }
    
	/**
	 * Calculate the HTML for a post item based on the widget settings and post.
     * Expected to be called in an active loop with all the globals set
	 *
	 * @param  array $instance Array which contains the various settings
     * $param  null|integer $current_post_id If on singular page specifies the id of
     *                      the post, otherwise null
	 * @return string The HTML for item related to the post
     *
     * @since 4.6
	 */
    function itemHTML($instance,$current_post_id) {
        global $post;
        
        $ret = '<li ';
                    
        if ( $current_post_id == $post->ID ) { 
            $ret .= "class='cat-post-item cat-post-current'"; 
        } else {
            $ret .= "class='cat-post-item'";
        }
        $ret.='>'; // close the li opening tag
        
        // Thumbnail position to top
        if( isset( $instance["thumbTop"] ) ) {
            $ret .= $this->show_thumb($instance); 
        }
        
        if( !isset( $instance['hide_post_titles'] ) ) { 
            $ret .= '<a class="post-title';
            if( !isset( $instance['disable_css'] ) ) { 
                $ret .= " cat-post-title"; 
            }
            $ret .= '" href="'.get_the_permalink().'" rel="bookmark">'.get_the_title();
            $ret .= '</a> ';
        }

        if ( isset( $instance['date'] ) ) {
            if ( isset( $instance['date_format'] ) && strlen( trim( $instance['date_format'] ) ) > 0 ) { 
                $date_format = $instance['date_format']; 
            } else {
                $date_format = "j M Y"; 
            } 
            $ret .= '<p class="post-date ';
            if( !isset( $instance['disable_css'] ) ) { 
                $ret .= "cat-post-date";
            } 
            $ret .= '">';
            if ( isset ( $instance["date_link"] ) ) { 
                $ret .= '<a href="'.get_the_permalink().'">';
            }
            $ret .= get_the_time($date_format);
            if ( isset ( $instance["date_link"] ) ) { 
                $ret .= '</a>';
            }
            $ret .= '</p>';
        }
        
        // Thumbnail position normal
        if( !isset( $instance["thumbTop"] ) ) {
            $this->show_thumb($instance);
        }

        if ( isset( $instance['excerpt'] ) ) {
            $ret .= apply_filters('the_excerpt',get_the_excerpt());
        }
        
        if ( isset( $instance['comment_num'] ) ) {
            $ret .= '<p class="comment-num';
            if ( !isset( $instance['disable_css'] ) ) {
                $ret .= " cat-post-comment-num"; 
            } 
            $ret .= '">';
            $ret .= '('.get_comments_number().')';
            $ret .= '</p>';
        }

        if ( isset( $instance['author'] ) ) {
            $ret .= '<p class="post-author ';
            if( !isset( $instance['disable_css'] ) ) { 
                $ret .= "cat-post-author"; 
            } 
            $ret .= '">';
            $ret .= get_the_author_posts_link(); 
            $ret .= '</p>';
        }
        
        $ret .= '</li>';
        return $ret;
    }
    
	/**
	 * Filter to set the number of words in an excerpt
	 *
	 * @param  int $length The number of words as configured by wordpress core or set by previous filters
	 * @return int The number of words configured for the widget, 
     *             or the $length parameter if it is not configured or garbage value
     *
     * @since 4.6
	 */
    function excerpt_length_filter($length) {
        if ( isset($this->instance["excerpt_length"]) && $this->instance["excerpt_length"] > 0 )
            $length = $this->instance["excerpt_length"];

        return $length;
    }
    
	/**
	 * The main widget display controller
     *
     * Called by the sidebar processing core logic to display the widget
	 *
	 * @param array $args An array containing the "environment" setting for the widget,
     *                     namely, the enclosing tags for the widget and its title. 
	 * @param array $instance The settings associate with the widget
     *
     * @since 4.1
	 */
	function widget($args, $instance) {

		extract( $args );
		$this->instance = $instance;
		
        $args = $this->queryArgs($instance);
		$cat_posts = new \WP_Query( $args );
		
		if ( !isset ( $instance["hide_if_empty"] ) || $cat_posts->have_posts() ) {
			
			/**
			 * Excerpt length filter
			 */
 			if ( isset($instance["excerpt_length"]) && $instance["excerpt_length"] > 0 ) {
 				add_filter('excerpt_length', array($this,'excerpt_length_filter'));
			}
			
			if( isset($instance["excerpt_more_text"]) && ltrim($instance["excerpt_more_text"]) != '' )
			{
				add_filter('excerpt_more', array($this,'excerpt_more_filter'));
			}

			if( isset( $instance['excerpt_allow_html'] ) ) {
				remove_filter('get_the_excerpt', 'wp_trim_excerpt');
				add_filter('the_excerpt', array($this,'allow_html_excerpt'));
			} else {
				add_filter('the_excerpt', array($this,'explicite_the_excerpt'));
			}
			

			echo $before_widget;

			// Widget title
            echo $this->titleHTML($before_title,$after_title,$instance);

			// Post list
			echo "<ul>\n";

            $current_post_id = null;
            if (is_singular())
                $current_post_id = get_the_ID();
            
			while ( $cat_posts->have_posts() )
			{
                $cat_posts->the_post();
                
				echo $this->itemHTML($instance,$current_post_id);
			}

			echo "</ul>\n";

			// Footer link to category page
            echo $this->footerHTML($instance);

			echo $after_widget;

			remove_filter('excerpt_length', array($this,'excerpt_length_filter'));
			remove_filter('excerpt_more', array($this,'excerpt_more_filter'));
			add_filter('get_the_excerpt', 'wp_trim_excerpt');
			remove_filter('the_excerpt', array($this,'allow_html_excerpt'));
			remove_filter('the_excerpt', array($this,'explicite_the_excerpt'));
			
			wp_reset_postdata();
			
		} // END if
	} // END function

	/**
	 * Update the options
	 *
	 * @param  array $new_instance
	 * @param  array $old_instance
	 * @return array
	 */
	function update($new_instance, $old_instance) {

		return $new_instance;
	}

	/**
	 * Output the title panel of the widget configuration form.
	 *
	 * @param  array $instance
	 * @return void
     *
     * @since 4.6
	 */
    function formTitlePanel($instance) {
		$instance = wp_parse_args( ( array ) $instance, array(
			'title'                => '',
			'title_link'           => '',
			'hide_title'           => ''
        ));
		$title                = $instance['title'];
		$hide_title           = $instance['hide_title'];
		$title_link           = $instance['title_link'];        
?>    
        <h4 data-panel="title"><?php _e('Title','categoryposts')?></h4>
        <div>
            <p>
                <label for="<?php echo $this->get_field_id("title"); ?>">
                    <?php _e( 'Title','categoryposts' ); ?>:
                    <input class="widefat" style="width:80%;" id="<?php echo $this->get_field_id("title"); ?>" name="<?php echo $this->get_field_name("title"); ?>" type="text" value="<?php echo esc_attr($instance["title"]); ?>" />
                </label>
            </p>
            <p>
                <label for="<?php echo $this->get_field_id("title_link"); ?>">
                    <input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id("title_link"); ?>" name="<?php echo $this->get_field_name("title_link"); ?>"<?php checked( (bool) $instance["title_link"], true ); ?> />
                    <?php _e( 'Make widget title link','categoryposts' ); ?>
                </label>
            </p>
            <p>
                <label for="<?php echo $this->get_field_id("hide_title"); ?>">
                    <input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id("hide_title"); ?>" name="<?php echo $this->get_field_name("hide_title"); ?>"<?php checked( (bool) $instance["hide_title"], true ); ?> />
                    <?php _e( 'Hide title','categoryposts' ); ?>
                </label>
            </p>
        </div>			
<?php            
    }
    
	/**
	 * Output the filter panel of the widget configuration form.
	 *
	 * @param  array $instance
	 * @return void
     *
     * @since 4.6
	 */
    function formFilterPanel($instance) {
		$instance = wp_parse_args( ( array ) $instance, array(
			'cat'                  => '',
			'num'                  => get_option('posts_per_page'),
			'sort_by'              => '',
			'asc_sort_order'       => '',
			'exclude_current_post' => '',
			'hideNoThumb'          => ''
        ));
		$cat                  = $instance['cat'];
		$num                  = $instance['num'];
		$sort_by              = $instance['sort_by'];
		$asc_sort_order       = $instance['asc_sort_order'];
		$exclude_current_post = $instance['exclude_current_post'];
		$hideNoThumb          = $instance['hideNoThumb'];
?>
        <h4 data-panel="filter"><?php _e('Filter','categoryposts');?></h4>
        <div>
            <p>
                <label>
                    <?php _e( 'Category','categoryposts' ); ?>:
                    <?php wp_dropdown_categories( array( 'hide_empty'=> 0, 'name' => $this->get_field_name("cat"), 'selected' => $instance["cat"] ) ); ?>
                </label>
            </p>
            <p>
                <label for="<?php echo $this->get_field_id("num"); ?>">
                    <?php _e('Number of posts to show','categoryposts'); ?>:
                    <input style="text-align: center; width: 30%;" id="<?php echo $this->get_field_id("num"); ?>" name="<?php echo $this->get_field_name("num"); ?>" type="number" min="0" value="<?php echo absint($instance["num"]); ?>" />
                </label>
            </p>
            <p>
                <label for="<?php echo $this->get_field_id("sort_by"); ?>">
                    <?php _e('Sort by','categoryposts'); ?>:
                    <select id="<?php echo $this->get_field_id("sort_by"); ?>" name="<?php echo $this->get_field_name("sort_by"); ?>">
                        <option value="date"<?php selected( $instance["sort_by"], "date" ); ?>><?php _e('Date','categoryposts')?></option>
                        <option value="title"<?php selected( $instance["sort_by"], "title" ); ?>><?php _e('Title','categoryposts')?></option>
                        <option value="comment_count"<?php selected( $instance["sort_by"], "comment_count" ); ?>><?php _e('Number of comments','categoryposts')?></option>
                        <option value="rand"<?php selected( $instance["sort_by"], "rand" ); ?>><?php _e('Random','categoryposts')?></option>
                    </select>
                </label>
            </p>
            <p>
                <label for="<?php echo $this->get_field_id("asc_sort_order"); ?>">
                    <input type="checkbox" class="checkbox" 
                        id="<?php echo $this->get_field_id("asc_sort_order"); ?>" 
                        name="<?php echo $this->get_field_name("asc_sort_order"); ?>"
                        <?php checked( (bool) $instance["asc_sort_order"], true ); ?> />
                            <?php _e( 'Reverse sort order (ascending)','categoryposts' ); ?>
                </label>
            </p>
            <p>
                <label for="<?php echo $this->get_field_id("exclude_current_post"); ?>">
                    <input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id("exclude_current_post"); ?>" name="<?php echo $this->get_field_name("exclude_current_post"); ?>"<?php checked( (bool) $instance["exclude_current_post"], true ); ?> />
                    <?php _e( 'Exclude current post','categoryposts' ); ?>
                </label>
            </p>
            <p>
                <label for="<?php echo $this->get_field_id("hideNoThumb"); ?>">
                    <input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id("hideNoThumb"); ?>" name="<?php echo $this->get_field_name("hideNoThumb"); ?>"<?php checked( (bool) $instance["hideNoThumb"], true ); ?> />
                    <?php _e( 'Exclude posts which have no thumbnail','categoryposts' ); ?>
                </label>
            </p>					
        </div>			
<?php
    }
    
	/**
	 * Output the filter panel of the widget configuration form.
	 *
	 * @param  array $instance
	 * @return void
     *
     * @since 4.6
	 */
    function formThumbnailPanel($instance) {
		$instance = wp_parse_args( ( array ) $instance, array(
			'thumb'                => '',
			'thumbTop'             => '',
			'thumb_w'              => '',
			'thumb_h'              => '',
			'use_css_cropping'     => '',
			'thumb_hover'          => ''
        ));
		$thumb                = $instance['thumb'];
		$thumbTop             = $instance['thumbTop'];
		$thumb_w              = $instance['thumb_w'];
		$thumb_h              = $instance['thumb_h'];
		$use_css_cropping     = $instance['use_css_cropping'];
		$thumb_hover          = $instance['thumb_hover'];
?>        
        <h4 data-panel="thumbnail"><?php _e('Thumbnails','categoryposts')?></h4>
        <div>
            <p>
                <label for="<?php echo $this->get_field_id("thumb"); ?>">
                    <input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id("thumb"); ?>" name="<?php echo $this->get_field_name("thumb"); ?>"<?php checked( (bool) $instance["thumb"], true ); ?> />
                    <?php _e( 'Show post thumbnail','categoryposts' ); ?>
                </label>
            </p>
            <p>
                <label for="<?php echo $this->get_field_id("thumbTop"); ?>">
                    <input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id("thumbTop"); ?>" name="<?php echo $this->get_field_name("thumbTop"); ?>"<?php checked( (bool) $instance["thumbTop"], true ); ?> />
                    <?php _e( 'Show thumbnails above text','categoryposts' ); ?>
                </label>
            </p>
            <p>
                <label>
                    <?php _e('Thumbnail dimensions (in pixels)','categoryposts'); ?>:<br />
                    <label for="<?php echo $this->get_field_id("thumb_w"); ?>">
                        <?php _e('Width:','categoryposts')?> <input class="widefat" style="width:30%;" type="number" min="1" id="<?php echo $this->get_field_id("thumb_w"); ?>" name="<?php echo $this->get_field_name("thumb_w"); ?>" value="<?php echo esc_attr($instance["thumb_w"]); ?>" />
                    </label>
                    
                    <label for="<?php echo $this->get_field_id("thumb_h"); ?>">
                        <?php _e('Height:','categoryposts')?> <input class="widefat" style="width:30%;" type="number" min="1" id="<?php echo $this->get_field_id("thumb_h"); ?>" name="<?php echo $this->get_field_name("thumb_h"); ?>" value="<?php echo esc_attr($instance["thumb_h"]); ?>" />
                    </label>
                </label>
            </p>
            <p>
                <label for="<?php echo $this->get_field_id("use_css_cropping"); ?>">
                    <input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id("use_css_cropping"); ?>" name="<?php echo $this->get_field_name("use_css_cropping"); ?>"<?php checked( (bool) $instance["use_css_cropping"], true ); ?> />
                    <?php _e( 'Crop bigger thumbnails around the center ','categoryposts' ); ?>
                </label>
            </p>					
            <p>
                <label for="<?php echo $this->get_field_id("thumb_hover"); ?>">
                    <?php _e( 'Animation on mouse hover:','categoryposts' ); ?>
                </label>
                <select id="<?php echo $this->get_field_id("thumb_hover"); ?>" name="<?php echo $this->get_field_name("thumb_hover"); ?>">
                    <option value="none" <?php selected($thumb_hover, 'none')?>><?php _e( 'None', 'categoryposts' ); ?></option>
                    <option value="dark" <?php selected($thumb_hover, 'dark')?>><?php _e( 'Darker', 'categoryposts' ); ?></option>
                    <option value="white" <?php selected($thumb_hover, 'white')?>><?php _e( 'Brighter', 'categoryposts' ); ?></option>
                    <option value="scale" <?php selected($thumb_hover, 'scale')?>><?php _e( 'Zoom in', 'categoryposts' ); ?></option>
					<option value="blur" <?php selected($thumb_hover, 'blur')?>><?php _e( 'Blur', 'categoryposts' ); ?></option>
                </select>
            </p>
        </div>
<?php
    }
    
	/**
	 * The widget configuration form back end.
	 *
	 * @param  array $instance
	 * @return void
	 */
	function form($instance) {
		$instance = wp_parse_args( ( array ) $instance, array(
			'footer_link'          => '',
			'hide_post_titles'     => '',
			'excerpt'              => '',
			'excerpt_length'       => 55,
			'excerpt_allow_html'   => '',
			'excerpt_allowed_elements' => array('0'),
			'excerpt_more_text'    => '',
			'comment_num'          => '',
			'author'               => '',
			'date'                 => '',
			'date_link'            => '',
			'date_format'          => '',
			'disable_css'          => '',
			'hide_if_empty'        => ''
		) );

		$footer_link          = $instance['footer_link'];
		$hide_post_titles     = $instance['hide_post_titles'];
		$excerpt              = $instance['excerpt'];
		$excerpt_length       = $instance['excerpt_length'];
		$excerpt_allow_html   = $instance['excerpt_allow_html'];
		$excerpt_allowed_elements = $instance['excerpt_allowed_elements'];
		$excerpt_more_text    = $instance['excerpt_more_text'];
		$comment_num          = $instance['comment_num'];
		$author               = $instance['author'];
		$date                 = $instance['date'];
		$date_link            = $instance['date_link'];
		$date_format          = $instance['date_format'];
		$disable_css          = $instance['disable_css'];
		$hide_if_empty        = $instance['hide_if_empty'];

		?>
		<div class="category-widget-cont">
            <p><a target="_blank" href="http://tiptoppress.com/terms-tags-and-categories-posts-widget/">Get the Pro Version</a></p>
            <p><a target="_blank" href="http://tiptoppress.com/category-posts-widget/documentation/">Documentation</a></p>
        <?php
            $this->formTitlePanel($instance);
            $this->formFilterPanel($instance);
            $this->formThumbnailPanel($instance);
        ?>
			<h4 data-panel="details"><?php _e('Post details','categoryposts')?></h4>
			<div>
				<p>
					<label for="<?php echo $this->get_field_id("hide_post_titles"); ?>">
						<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id("hide_post_titles"); ?>" name="<?php echo $this->get_field_name("hide_post_titles"); ?>"<?php checked( (bool) $instance["hide_post_titles"], true ); ?> />
						<?php _e( 'Hide post titles','categoryposts' ); ?>
					</label>
				</p>
				<p>
					<label for="<?php echo $this->get_field_id("excerpt"); ?>">
						<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id("excerpt"); ?>" name="<?php echo $this->get_field_name("excerpt"); ?>"<?php checked( (bool) $instance["excerpt"], true ); ?> />
						<?php _e( 'Show post excerpt','categoryposts' ); ?>
					</label>
				</p>
				<p>
					<label for="<?php echo $this->get_field_id("excerpt_length"); ?>">
						<?php _e( 'Excerpt length (in words):','categoryposts' ); ?>
					</label>
					<input style="text-align: center; width:30%;" type="number" min="0" id="<?php echo $this->get_field_id("excerpt_length"); ?>" name="<?php echo $this->get_field_name("excerpt_length"); ?>" value="<?php echo $instance["excerpt_length"]; ?>" />
				</p>
				<p>
					<label for="<?php echo $this->get_field_id("excerpt_allow_html"); ?>">
						<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id("excerpt_allow_html"); ?>" name="<?php echo $this->get_field_name("excerpt_allow_html"); ?>"<?php checked( (bool) $instance["excerpt_allow_html"], true ); ?> />
						<?php _e( 'Allow HTML in excerpt:','categoryposts' ); ?>
					

						<select class="cphtml cpwpany" multiple name="<?php echo $this->get_field_name('excerpt_allowed_elements'); ?>[]" id="<?php echo $this->get_field_id('excerpt_allowed_elements'); ?>">
						<?php									
						if (isset($instance['excerpt_allowed_elements']))
							$selected = $instance['excerpt_allowed_elements'];

						if (in_array('0',$selected,true))
							echo '<option value="0" selected="selected">&lt;a&gt;</option>';
						else
							echo '<option value="0">&lt;a&gt;</option>';		
							
						$cphtml = array(
								'&lt;br&gt;',
								'&lt;em&gt;',
								'&lt;i&gt;',
								'&lt;ul&gt;',
								'&lt;ol&gt;',
								'&lt;li&gt;',
								'&lt;p&gt;',
								'&lt;img&gt;',
								'&lt;script&gt;',
								'&lt;style&gt;',								
								'&lt;video&gt;',
								'&lt;audio&gt;'
						);
						foreach ($cphtml as $index => $name) {
							$sel = '';
							if (in_array((string)($index+1),$selected,true))
								$sel = 'selected="selected"';
							echo '<option value="'.($index+1).'"'.$sel.'>'.$name.'</option>';
						}
						?>
						</select>
					</label>
				</p>
				<p>
					<label for="<?php echo $this->get_field_id("excerpt_more_text"); ?>">
						<?php _e( 'Excerpt \'more\' text:','categoryposts' ); ?>
					</label>
					<input class="widefat" style="width:50%;" placeholder="<?php _e('... more','categoryposts')?>" id="<?php echo $this->get_field_id("excerpt_more_text"); ?>" name="<?php echo $this->get_field_name("excerpt_more_text"); ?>" type="text" value="<?php echo esc_attr($instance["excerpt_more_text"]); ?>" />
				</p>
				<p>
					<label for="<?php echo $this->get_field_id("date"); ?>">
						<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id("date"); ?>" name="<?php echo $this->get_field_name("date"); ?>"<?php checked( (bool) $instance["date"], true ); ?> />
						<?php _e( 'Show post date','categoryposts' ); ?>
					</label>
				</p>
				<p>
					<label for="<?php echo $this->get_field_id("date_format"); ?>">
						<?php _e( 'Date format:','categoryposts' ); ?>
					</label>
					<input class="text" placeholder="j M Y" id="<?php echo $this->get_field_id("date_format"); ?>" name="<?php echo $this->get_field_name("date_format"); ?>" type="text" value="<?php echo esc_attr($instance["date_format"]); ?>" size="8" />
				</p>
				<p>
					<label for="<?php echo $this->get_field_id("date_link"); ?>">
						<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id("date_link"); ?>" name="<?php echo $this->get_field_name("date_link"); ?>"<?php checked( (bool) $instance["date_link"], true ); ?> />
						<?php _e( 'Make widget date link','categoryposts' ); ?>
					</label>
				</p>
				<p>
					<label for="<?php echo $this->get_field_id("comment_num"); ?>">
						<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id("comment_num"); ?>" name="<?php echo $this->get_field_name("comment_num"); ?>"<?php checked( (bool) $instance["comment_num"], true ); ?> />
						<?php _e( 'Show number of comments','categoryposts' ); ?>
					</label>
				</p>
				<p>
					<label for="<?php echo $this->get_field_id("author"); ?>">
						<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id("author"); ?>" name="<?php echo $this->get_field_name("author"); ?>"<?php checked( (bool) $instance["author"], true ); ?> />
						<?php _e( 'Show post author','categoryposts' ); ?>
					</label>
				</p>
			</div>
			<h4 data-panel="general"><?php _e('General','categoryposts')?></h4>
			<div>
				<p>
					<label for="<?php echo $this->get_field_id("disable_css"); ?>">
						<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id("disable_css"); ?>" name="<?php echo $this->get_field_name("disable_css"); ?>"<?php checked( (bool) $instance["disable_css"], true ); ?> />
						<?php _e( 'Disable the built-in CSS for this widget','categoryposts' ); ?>
					</label>
				</p>
				<p>
					<label for="<?php echo $this->get_field_id("hide_if_empty"); ?>">
						<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id("hide_if_empty"); ?>" name="<?php echo $this->get_field_name("hide_if_empty"); ?>"<?php checked( (bool) $instance["hide_if_empty"], true ); ?> />
						<?php _e( 'Hide widget if there are no matching posts','categoryposts' ); ?>
					</label>
				</p>
			</div>
			<h4 data-panel="footer"><?php _e('Footer','categoryposts')?></h4>
			<div>
				<p>
					<label for="<?php echo $this->get_field_id("footer_link"); ?>">
						<?php _e( 'Footer link text','categoryposts' ); ?>:
						<input class="widefat" style="width:60%;" placeholder="<?php _e('... more by this topic','categoryposts')?>" id="<?php echo $this->get_field_id("footer_link"); ?>" name="<?php echo $this->get_field_name("footer_link"); ?>" type="text" value="<?php echo esc_attr($instance["footer_link"]); ?>" />
					</label>
				</p>
			</div>
            <p style="text-align:right;">
                Follow us on <a target="_blank" href="https://www.facebook.com/TipTopPress">Facebook</a> and 
				<a target="_blank" href="https://twitter.com/TipTopPress">Twitter</a></br></br>
            </p>
		</div>
		<?php
	}
}

function register_widget() {
    return \register_widget(__NAMESPACE__.'\Widget');
}

add_action( 'widgets_init', __NAMESPACE__.'\register_widget' );