<?php

if ( ! class_exists( 'Timber' ) ) {
	add_action( 'admin_notices', function() {
		echo '<div class="error"><p>Timber not activated. Make sure you activate the plugin in <a href="' . esc_url( admin_url( 'plugins.php#timber' ) ) . '">' . esc_url( admin_url( 'plugins.php') ) . '</a></p></div>';
	});
	
	add_filter('template_include', function($template) {
		return get_stylesheet_directory() . '/static/no-timber.html';
	});
	
	return;
}

Timber::$dirname = array('templates', 'views');

class ApparitionsSite extends TimberSite {

	function __construct() {
		add_theme_support( 'post-formats' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'menus' );
		add_theme_support( 'html5', array( 'comment-list', 'comment-form', 'search-form', 'gallery', 'caption' ) );
		add_filter( 'timber_context', array( $this, 'add_to_context' ) );
		add_filter( 'get_twig', array( $this, 'add_to_twig' ) );

		add_filter( 'pre_get_posts', array ( $this, 'configure_get_posts' ) );
		add_filter( 'jpeg_quality', array($this, 'configure_jpeg_quality'));

		add_action( 'init', array( $this, 'register_taxonomies' ) );
		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'init', array( $this, 'register_nav_menus' ) );
		add_action( 'init', array( $this, 'register_shortcodes' ) );
		parent::__construct();
	}

	function register_post_types() {
		$this->register_post_type_member();
		$this->register_post_type_press_release();
	}

	function register_taxonomies() {
		$this->register_taxonomy_members_category();
	}

	function register_nav_menus() {
		register_nav_menu('main-menu',__( 'Main Menu' ));
		register_nav_menu('footer-menu',__( 'Footer Menu' ));
	}

	function register_shortcodes() {
		add_shortcode('readmore', array($this, 'shortcode_readmore'));
		add_shortcode('apparitions_members', array($this, 'shortcode_apparitions_members'));
		add_shortcode('apparitions_press_releases', array($this, 'shortcode_apparitions_press_releases'));
		add_shortcode('apparitions_logos', array($this, 'shortcode_apparitions_logos'));
	}

	function add_to_context( $context ) {
		
		$context['menu'] = new TimberMenu('main-menu');
		$context['footer_menu'] = new TimberMenu('footer-menu');
		$context['pagination'] = Timber::get_pagination();
		
		$context['site'] = $this;

		$context['is_home'] = is_home() || is_front_page();

		// add the WPML languages
		if (function_exists('icl_get_languages')) {
			$context['languages'] = icl_get_languages('skip_missing=0&orderby=code');
		}

		return $context;
	}

	function add_to_twig( $twig ) {
		/* this is where you can add your own functions to twig */
		$twig->addExtension( new Twig_Extension_StringLoader() );
		$twig->addFilter(new Twig_SimpleFilter('has_shortcode', array($this, 'filter_has_shortcode')));
		$twig->addFilter('fit', new Twig_SimpleFilter('fit', array($this, 'fit_image')));
		return $twig;
	}

	function filter_has_shortcode($post, $shortcode) {
        if (strpos($post->post_content, '[' . $shortcode)) {
            return true;
        }
		return false;
	}

	function register_post_type_member() {
		register_post_type('member', array(
			'labels' => array(
				'name' => 'Members',
				'singular_name' => 'Member'
			),
			'description' => 'Team & Organizer Bios',
			'rewrite' => array(
				'slug' => 'members'
			),
			'supports' => array(
				'title', 
				'editor', 
				'thumbnail', 
				'excerpt', 
				'custom-fields', 
				'page-attributes'
			),
			'public' => true,
			'has_archive' => false,
			'hierarchical' => false
		));

		register_taxonomy_for_object_type('member_category', 'member');
	}

	function register_post_type_press_release() {
		register_post_type('press_release', array(
			'labels' => array(
				'name' => 'Press Releases',
				'singular_name' => 'Press Release'
			),
			'description' => 'Press Releases / Comunicate de presă',
			'rewrite' => array(
				'slug' => 'press-releases'
			),
			'supports' => array(
				'title', 
				'editor', 
				'thumbnail', 
				'excerpt', 
				'custom-fields', 
				'page-attributes'
			),
			'public' => true,
			'has_archive' => true,
			'hierarchical' => false
		));
	}

	function register_taxonomy_members_category() {
		register_taxonomy('member_category', 'proiect', array(
			'labels' => array(
				'name' => 'Member Categories',
				'singular_name' => 'Member Category'
			),
			'hierarchical' => true,
			'public' => true,
			'rewrite' => array(
				'slug' => 'member-categories'
			),
			'show_in_quick_edit' => true
		));
	}

	function configure_get_posts($query) {

	    // Don't alter queries in the admin interface
	    // and don't alter any query that's not the main one
	    if (is_admin() || !$query->is_main_query()) {
	        return;
	    } 

	    // for news articles, display 5 items per page
	    if ($query->is_category()) {
	        $query->set('posts_per_archive_page', 5);
	    }
	}

	function shortcode_readmore($atts = [], $content = '', $tag = '') {
		$context = array();

		// convert atts to lowercase
		$atts = array_change_key_case((array)$atts, CASE_LOWER);
		
		// default attributes
		$readmore_atts = shortcode_atts([
             'more' => 'Read more',
             'less' => 'Read less'
        ], $atts, $tag);

		$context['atts'] = $readmore_atts;
		$context['content'] = $content;
		$context['tag'] = $tag;

		// todo could do here 'shortcodes/' . $tag . '.twig', but ignore missing
		return Timber::compile('shortcodes/readmore.twig', $context);
	}

	function shortcode_apparitions_members($atts = [], $content = '', $tag = '') {
		$context = array();

		// convert atts to lowercase
		$atts = array_change_key_case((array)$atts, CASE_LOWER);

		$query = array(
			'post_type' => 'member',
			'orderby' => 'menu_order',
			'order' => 'ASC'
		);

		if (array_key_exists('category', $atts)) {
			$query['tax_query'] = array(
				array(
					'taxonomy' => 'member_category',
					'field' => 'slug',
					'terms' => $atts['category']
				)
			);
		}

		$context['members'] = Timber::get_posts($query);
		$context['layout'] = $atts['layout'];

		// todo could do here 'shortcodes/' . $tag . '.twig', but ignore missing
		return Timber::compile('shortcodes/apparitions_members.twig', $context);
	}

	function shortcode_apparitions_logos($atts = [], $content = '', $tag = '') {

		$about_project_page_id = 69;

		$context['reference_post'] = new TimberPost(
			$this->object_id_in_current_language($about_project_page_id)
		);

		$context['exclude_categories'] = $atts['exclude'];

		return Timber::compile('shortcodes/apparitions_logos.twig', $context);
	}

	function shortcode_apparitions_press_releases($atts = [], $content = '', $tag = '') {
		$context = array();

		// convert atts to lowercase
		$atts = array_change_key_case((array)$atts, CASE_LOWER);
		
		// default attributes
		$pr_atts = shortcode_atts([
             'count' => 6
        ], $atts, $tag);

		$context['press_releases'] = Timber::get_posts(array(
			'post_type' => 'press_release',
			'orderby' => 'date',
			'order' => 'DESC',
			'posts_per_page' => $pr_atts['count']
		));

		return Timber::compile('shortcodes/apparitions_press_releases.twig', $context);
	}


	function fit_image($src, $w, $h = 0) {
		// Instantiate TimberImage from $src so we have access to dimensions
		$img = new TimberImage($src);

		// Call proportional resize on width or height, depending on how the image's
		// aspect ratio compares to the target box aspect ratio
		if ($h == 0 || $img->aspect() > $w / $h) {
			return Timber\ImageHelper::resize($src, $w);
		} else {
			return Timber\ImageHelper::resize($src, 0, $h);
		}
	}

	function object_id_in_current_language($id, $type = 'page') {
		if (function_exists('icl_object_id')) {
			return icl_object_id($id, $type, true);
		} else {
			return $id;
		}
	}

	function configure_jpeg_quality() {
		return 100;
	}
}

new ApparitionsSite();
