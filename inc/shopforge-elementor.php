<?php
/**
 * Widget Elementor nativi per i principali shortcode del prodotto —
 * riusano la stessa lista di inc/shopforge-blocks.php (shopforge_gutenberg_blocks_registry())
 * così le due integrazioni restano allineate senza duplicare l'elenco.
 *
 * Solo compatibilità/rendering: nessun controllo di stile custom, dato che
 * questi shortcode leggono già il prodotto corrente per default. Chi vuole
 * personalizzare colori/dimensioni resta libero di farlo via CSS custom di
 * Elementor sul wrapper del widget.
 *
 * @package ShopForge
 */

defined( 'ABSPATH' ) || exit;

class ShopForge_Elementor_Shortcode_Widget extends \Elementor\Widget_Base {
	private array $shopforge_config;

	public function __construct( $data = [], $args = null ) {
		parent::__construct( $data, $args );
		$this->shopforge_config = $args['shopforge_config'] ?? [
			'slug'      => 'widget',
			'title'     => 'ShopForge',
			'shortcode' => '',
		];
	}

	public function get_name() {
		return 'shopforge-' . $this->shopforge_config['slug'];
	}

	public function get_title() {
		return $this->shopforge_config['title'];
	}

	public function get_icon() {
		return 'eicon-shortcode';
	}

	public function get_categories() {
		return [ 'general' ];
	}

	public function get_keywords() {
		return [ 'shopforge', 'woocommerce' ];
	}

	protected function register_controls() {}

	protected function render() {
		echo do_shortcode( '[' . $this->shopforge_config['shortcode'] . ']' );
	}
}

add_action( 'elementor/widgets/register', function ( $widgets_manager ) {
	foreach ( shopforge_gutenberg_blocks_registry() as $slug => $block ) {
		$widgets_manager->register( new ShopForge_Elementor_Shortcode_Widget( [], [
			'shopforge_config' => [
				'slug'      => $slug,
				'title'     => $block['title'],
				'shortcode' => $block['shortcode'],
			],
		] ) );
	}
} );
