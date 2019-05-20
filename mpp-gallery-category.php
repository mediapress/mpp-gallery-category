<?php

/**
 * Plugin Name: MediaPress Gallery Category
 * Plugin URI: http://buddydev.com
 * Description: This plugin create a custom taxonomy for MediaPress and allow users to add gallery to these categories and filter them on gallery directory based on category.
 * Version: 1.0.0
 * Author: BuddyDev Team
 * Author URI: https://buddydev.com
 */

/**
 * Contributor Name: Ravi Sharma ( raviousprime )
 *
 * This plugin is an alias for mpp-gallery-categories it enable user to select only one category at the time of creation or edit gallery detail page.
 */

// exit if file access directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MPP_Gallery_Categories_Helper
 */
class MPP_Gallery_Categories_Helper {

	/**
	 * Class instance
	 *
	 * @var MPP_Gallery_Categories_Helper
	 */
	private static $instance = null;

	/**
	 * The constructor.
	 */
	private function __construct() {
		$this->setup();
	}

	/**
	 * Class instance
	 *
	 * @return MPP_Gallery_Categories_Helper
	 */
	public static function get_instance() {

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Setup the requirements
	 */
	public function setup() {
		// register custom taxonomy for mediapress gallery.
		add_action( 'mpp_setup', array( $this, 'register_gallery_taxonomy' ), 99 );
		// add interface to assign gallery to any category.
		add_action( 'mpp_after_edit_gallery_form_fields', array( $this, 'add_interface' ) );
		add_action( 'mpp_before_create_gallery_form_submit_field', array( $this, 'add_interface' ) );
		// assign term to gallery.
		add_action( 'mpp_gallery_created', array( $this, 'save_gallery_category' ) );
		add_action( 'mpp_gallery_updated', array( $this, 'save_gallery_category' ), 8 );
		// add category filter on gallery page.
		add_action( 'mpp_gallery_directory_order_options', array( $this, 'add_category_filter' ) );
		// ajax action to filter gallery.
		add_action( 'wp_ajax_mpp_filter', array( $this, 'load_filter_list' ), 5 );
		add_action( 'wp_ajax_nopriv_mpp_filter', array( $this, 'load_filter_list' ), 5 );
	}

	/**
	 * Register a custom taxonomy for MediaPress
	 */
	public function register_gallery_taxonomy() {

		$labels = array(
			'name'          => _x( 'Gallery Categories', 'taxonomy general name', 'mpp-gallery-category' ),
			'singular_name' => _x( 'Gallery Category', 'taxonomy singular name', 'mpp-gallery-category' ),
			'search_items'  => __( 'Search Gallery Category', 'mpp-gallery-category' ),
			'all_items'     => __( 'All Gallery Category', 'mpp-gallery-category' ),
			'edit_item'     => __( 'Edit Gallery Category', 'mpp-gallery-category' ),
			'update_item'   => __( 'Update Gallery Category', 'mpp-gallery-category' ),
			'add_new_item'  => __( 'Add New Gallery Category', 'mpp-gallery-category' ),
			'new_item_name' => __( 'New Gallery Category Name', 'mpp-gallery-category' ),
		);

		$args = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => $this->get_taxonomy_slug() ),
		);

		register_taxonomy( $this->get_taxonomy(),  mpp_get_gallery_post_type(), $args );
	}

	/**
	 * Get gallery slug
	 *
	 * @return string
	 */
	public function get_taxonomy_slug() {
		return apply_filters( 'mpp_gallery_category_taxonomy_slug', 'mpp-gallery-category' );
	}

	/**
	 * Get custom taxonomy name
	 *
	 * @return string
	 */
	public function get_taxonomy() {
		return 'mpp_gallery_category';
	}

	/**
	 * Add interface to apply category to the gallery
	 */
	public function add_interface() {
	    $gallery_id = mpp_is_gallery_create() ? false : mpp_get_current_gallery_id();

		$args = array(
			'taxonomy'   => $this->get_taxonomy(),
			'name'       => 'gallery_category',
			'hide_empty' => false,
		);

		if ( ! empty( $gallery_id ) ) {
			$args['selected'] = $this->get_gallery_category( $gallery_id );
		}

		echo '<div class="mpp-u mpp-gallery-categories"><label>'.__( 'Choose Category', 'mpp_gallery_categories' ).'</label>';
		wp_dropdown_categories( $args );
		echo '</div>';
	}

	/**
	 * Get gallery category
	 *
	 * @param int $gallery_id Gallery id.
	 *
	 * @return int
	 */
	public function get_gallery_category( $gallery_id ) {
		return current( wp_get_post_terms( $gallery_id, $this->get_taxonomy(), array( 'fields' => 'ids' ) ) );
	}

	/**
	 * Save gallery category
	 *
	 * @param int $gallery_id Gallery id.
	 */
	public function save_gallery_category( $gallery_id ) {
		$term = absint( $_POST['gallery_category'] );

		if ( empty( $term ) ) {
			return;
		}

		wp_set_object_terms( $gallery_id, $term, $this->get_taxonomy(), false );
	}

	/**
	 * Add category filter to gallery listing directory.
	 */
	public function add_category_filter(){
		$args = array(
			'hide_empty' => 0,
			'fields'     => 'id=>name',
			'taxonomy'   => $this->get_taxonomy(),
		);

		$terms = get_terms( $args );

		?>
		<?php if ( ! empty( $terms ) ) : ?>
			<optgroup label="<?php _e( 'By Category', 'mpp-gallery-category' ) ?>">
				<?php foreach ( ( array ) $terms as $id => $name ) : ?>
					<option value="mpp-gallery-category-<?php echo $id?>">
						<?php echo $name; ?>
					</option>
				<?php endforeach;?>
			</optgroup>
		<?php endif; ?>
		<?php
	}

	/**
	 * Load filter list
	 */
	public function load_filter_list() {
		$type = isset( $_POST['filter'] ) ? $_POST['filter'] : '';

		if ( strpos( $type, 'mpp-gallery-category-' ) === false ) {
			return;
		}

		$cat_id = absint( str_replace( 'mpp-gallery-category-', '', $type ) );

		$page         = absint( $_POST['page'] );
		$scope        = $_POST['scope'];
		$search_terms = $_POST['search_terms'];

		// Make the query and setup.
		mediapress()->is_directory = true;

		// Get all public galleries, should we do type filtering.
		mediapress()->the_gallery_query = new MPP_Gallery_Query( array(
			'status'       => 'public',
			'page'         => $page,
			'search_terms' => $search_terms,
			'tax_query'    => array(
				array(
					'taxonomy' => $this->get_taxonomy(),
					'field'    => 'term_id',
					'terms'    => $cat_id,
				),
			),
		) );

		mpp_get_template( 'gallery/loop-gallery.php' );

		exit( 0 );
	}
}

mpp_gallery_categories();

function mpp_gallery_categories() {
	return MPP_Gallery_Categories_Helper::get_instance();
}


