<?php

/**
 * Plugin Name: CoverDesign Wallpaper Configurator
 * Plugin URI: https://local.coverdesign.it
 * Author: Haris Zafar
 * Author URI: #
 * Version: 1.6
 * Description: Adds a configurator panel in frontend which allows user to make changes on the fly before placing order.
 */

namespace CoverDesign;

use WC_Product;

class CoverDesign {
    protected static $_instance = null;

    private $version = '1.0';

    private $plugin_url = null;

	/**
     * Contains the custom fields for WooCommerce
     *
	 * @var array|string[]
     * @since 1.2
	 */
    public $fields = [
        'width' => 'Width (cm)',
        'height' => 'Height (cm)',
        'mirrored' => 'Is mirrored',
        'whole' => 'Is whole image'
    ];

    public function __construct() {}

    /**
     * Returns the same memory instance
     *
     * @return CoverDesign|null
     */
    public static function get_instance() {
        if ( ! isset( self::$_instance ) ) {
            self::$_instance = new CoverDesign();
        }

        return self::$_instance;
    }

    /**
     * Hooks the initial actions
     *
     * @return void
     */
    public function init() {
        // Public
        add_action( 'after_setup_theme', array( $this, 'setup' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );

        // Admin
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

        // Meta boxes
        add_action( 'add_meta_boxes', array( $this, 'meta_boxes' ) );
        add_action( 'save_post', array( $this, 'save_configurator_image_meta_box' ) );

        add_filter( 'woocommerce_product_tabs', array( $this, 'wallpaper_product_tab' ) );

        add_filter( 'woocommerce_add_cart_item_data', array( $this, 'cd_add_item_data' ), 10, 3 );
	    add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'cd_add_custom_fields' ) );

        add_filter( 'woocommerce_get_item_data', array( $this, 'cd_add_item_meta'), 10, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'cd_add_custom_order_line_item_meta'), 10, 4 );
    }

    /**
     * Adds custom field for Product
     * @return false|string [type] [description]
     */
    public function cd_add_custom_fields()
    {
        global $product;

        ob_start(); ?>

        <div class="cd-custom-fields" style="display: none">
            <label>Width: </label><input type="text" name="cd_width">
            <label>Height: </label><input type="text" name="cd_height">
            <label>Mirrored: </label><input type="text" name="cd_mirrored">
            <label>Whole: </label><input type="text" name="cd_whole">
        </div>
        <div class="clear"></div>

        <?php

        $content = ob_get_contents();
        ob_end_flush();

        return $content;
    }

    /**
     * Add custom data to Cart
     * @param $cart_item_data
     * @param $product_id
     * @param $variation_id
     * @return mixed [type]                 [description]
     */
    public function cd_add_item_data( $cart_item_data, $product_id, $variation_id ) {
        foreach ( array_keys( $this->fields ) as $_field ) {
            $field = $this->_get_field( $_field );

	        if( isset( $_POST[$field] ) )
	        {
		        $cart_item_data[$field] = sanitize_text_field($_POST[$field]);
	        }
        }

        return $cart_item_data;
    }

    /**
     * Display information as Meta on Cart page
     * @param $item_data
     * @param $cart_item
     * @return mixed [type]            [description]
     */
    public function cd_add_item_meta( $item_data, $cart_item )
    {
	    foreach ( $this->fields as $_field => $field_desc ) {
		    $field = $this->_get_field( $_field );

		    if(array_key_exists($field, $cart_item))
		    {
			    /** @noinspection NestedTernaryOperatorInspection */
			    $value = in_array( $_field, [ 'mirrored', 'whole' ] ) ? ( (bool) $cart_item[$field] ? 'Yes' : 'No' ) : $cart_item[$field];

			    $item_data[] = array(
				    'key'   => $field_desc,
				    'value' =>  $value
			    );
		    }
	    }

        return $item_data;
    }

    /**
     * @param $item
     * @param $cart_item_key
     * @param $values
     * @param $order
     * @return void
     */
    public function cd_add_custom_order_line_item_meta( $item, $cart_item_key, $values, $order )
    {
	    foreach ( $this->fields as $_field => $field_desc ) {
		    $field = $this->_get_field( $_field );

		    if( array_key_exists( $field, $values ) ) {
			    $item->add_meta_data( "_{$field}", $values[$field] );
		    }
	    }
    }

	/**
     * Returns the field with prefix
	 * @return string
	 */
    private function _get_field( $field ) {
        return array_key_exists( $field, $this->fields ) ? "cd_{$field}" : "";
    }

    /**
     * @return void
     */
    public function setup() {
        // Adds support for auto generated page title by WordPress
    }

    /**
     * @return void
     */
    public function scripts()
    {
        wp_enqueue_script( 'cd-wallpaper-script', $this->get_plugin_url() . '/assets/js/wallpaper.js', array( 'jquery' ), null );

        wp_enqueue_style( 'cd-wallpaper-style', $this->get_plugin_url() . '/assets/css/wallpaper.css', array(), $this->version );
        wp_enqueue_style( 'cd-bootstrap-style', '//cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap-grid.min.css', array(), $this->version );

    }

    public function admin_scripts() {
        if ( ! did_action( 'wp_enqueue_media' ) ) {
            wp_enqueue_media();
        }

        wp_enqueue_script( 'cd-wallpaper-admin-script', $this->get_plugin_url() . '/admin/assets/js/cd-admin.js', array('jquery'), null, false );
    }

    public function meta_boxes() {
        add_meta_box( 'cd_configurator_image_meta_box',
            'Configurator Image', array( $this,
                'render_configurator_image_meta_box' ), 'product', 'side');
    }

    public function render_configurator_image_meta_box( $post ) {
        $meta_key = 'cd_configurator_image';
        $value = get_post_meta( $post->ID, $meta_key, true );

        $meta_width_key = 'cd_min_width';
        $width_value = get_post_meta( $post->ID, $meta_width_key, true );

        $meta_height_key = 'cd_min_height';
        $height_value = get_post_meta( $post->ID, $meta_height_key, true );

        $image = ' button">Select image';

        if( $image_attributes = wp_get_attachment_image_src( $value, 'full' ) ) {
            $image = '"><img src="' . $image_attributes[0] . '" style="max-width:50%;display:block;" />';
            $display = 'inline-block';
        }

        echo '
        <div>
            <div class="width-and-length">
                <p>
                    <label for="' . $meta_width_key . '">Min Width (cm)</label>
                    <input type="number" class="short" name="' . $meta_width_key . '"
                        id="' . $meta_width_key . '" value="' . $width_value . '" step="any" min="0">
                </p>
                <p>
                    <label for="' . $meta_height_key . '">Min Height (cm)</label>
                    <input type="number" class="short" name="' . $meta_height_key . '"
                        id="' . $meta_height_key . '" value="' . $height_value . '" step="any" min="0">
                </p>                      
            </div>
            <p><a href="#" class="cd_upload_image_button' . $image . '</a></p>
            <input type="hidden" name="' . $meta_key . '" id="' . $meta_key . '" value="' . $value . '" />
            <p>
                <button type="button" class="cd_remove_image_button components-button
                is-link is-destructive" style="display:' . $display . '">Remove second featured image</button>
            </p>
        </div>';
    }

    public function save_configurator_image_meta_box( $post_id ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return $post_id;
        }
        $keys = ['cd_configurator_image', 'cd_min_width', 'cd_min_height'];

        foreach ($keys as $key) {
            update_post_meta( $post_id, $key, $_POST[$key] );
        }

        return $post_id;
    }

    public function wallpaper_product_tab( $tabs ) {
        // Adds the new tab
        $tabs['configuration_tab'] = array(
            'title'    => __( 'Configure Wallpaper', 'coverdesign' ),
            'priority' => 10,
            'callback' => array( $this, 'wallpaper_product_tab_content' )
        );

        return $tabs;
    }

    /**
     * @param WC_Product $product
     * @return void
     */
    public function get_configurator_image( $product ) {
        $value = $product->get_meta( 'cd_configurator_image', true );
        $imageURL = '';

        if( $image_attributes = wp_get_attachment_image_src( $value, 'full' ) ) {
            $imageURL = $image_attributes[0];
        }

        return $imageURL;
    }

    public function wallpaper_product_tab_content() {
        /** @var WC_Product */
        global $product;
        $image = wp_get_attachment_image_src( get_post_thumbnail_id( $product->get_id() ), 'single-post-thumbnail' );
        ?>
        <div id="configurator" class="pt-3">
            <h5 class="align-item-center text-center">Configura e ordina Prezzo € <?php echo $product->get_price(); ?> / m²</h5>

            <div class="panel d-flex flex-column">
                <div class="viewport" id="viewport">
                    <div class="editor">
                        <!--						<img id="sourceBanner" class="sourceBanner" alt="" src="--><?php //echo $image[0]; ?><!--" />-->
                        <img id="sourceBanner" class="sourceBanner" alt="" src="<?php echo $this->get_configurator_image( $product ); ?>" />
                        <div class="cropArea" id="cropArea"></div>
                    </div>
                    <div class="ruler-width"><div class="ruler-content">30 cm</div></div>
                    <div class="ruler-height"><div class="ruler-content">30 cm</div></div>
                </div>
                <div class="actions">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="action rel" id="settings-action">
                                <div class="action-button">
                                    <div class="action-icon"><i class="fas fa-expand-alt"></i></div>
                                    <div class="action-content">
                                        <span class="action-name">Impostazioni</span><span class="action-desc">...</span>
                                    </div>
                                    <div class="action-marker"><i class="fas fa-chevron-up"></i></div>
                                </div>
                                <div class="action-pane">
                                    <div class="pane-header d-flex">
                                        <span class="pane-heading me-auto"><strong>Impostazioni</strong></span>
                                        <i class="fas fa-chevron-down close-pane"></i>
                                    </div>
                                    <hr />
                                    <div class="pane-settings">
                                        <div class="pane-setting">
                                            <span>Dimensione:</span>
                                            <div class="row">
                                                <div class="col">
                                                    <div class="input-group p-settings" id="p-width">
                                                        <span class="input-group-text"><i class="fas fa-arrows-alt-h"></i></span>
                                                        <input type="number" data-min="<?php echo $product->get_meta('cd_min_width'); ?>" name="p-width" class="form-control" placeholder="Larghezza" />
                                                        <span class="input-group-text"><small>cm</small></span>
                                                        <div class="feedback"></div>
                                                    </div>
                                                </div>
                                                <div class="col">
                                                    <div class="input-group p-settings" id="p-height">
                                                        <span class="input-group-text"><i class="fas fa-arrows-alt-v"></i></span>
                                                        <input type="number" data-min="<?php echo $product->get_meta('cd_min_height'); ?>" name="p-height" class="form-control" placeholder="Altezza" />
                                                        <span class="input-group-text"><small>cm</small></span>
                                                        <div class="feedback"></div>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="p-tooltip mt-2 mb-2">
                                                        <span class="p-view-tooltip">Aggiungi altri 6-10 centimetri per il margine.</span>
                                                        <p class="p-tooltip-message">
                                                            Poiché pareti e soffitti non sono sempre completamente diritti, si consiglia di aggiungere da 6 a 10 centimetri sia in larghezza che in altezza per il margine durante il montaggio.
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="pane-setting">
                                            <span>Personalizza il motivo:</span>
                                            <div class="form-check">
                                                <input
                                                    type="checkbox"
                                                    class="form-check-input"
                                                    name="use-whole-image"
                                                    id="use-whole-image"
                                                    value="use-whole-image"
                                                />
<!--                                                <label class="form-check-label" for="use-whole-image">Use the whole image</label>-->
                                            </div>
                                            <div class="form-check">
                                                <input
                                                    type="checkbox"
                                                    class="form-check-input"
                                                    name="at-the-mirror"
                                                    id="at-the-mirror"
                                                    value="at-the-mirror"
                                                />
                                                <label class="form-check-label" for="at-the-mirror">Allo specchio</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="action">
                                <div class="action-button">
                                    <div class="action-icon"><i class="fas fa-ellipsis-v"></i></div>
                                    <div class="action-content">
                                        <span class="action-name">Qualità della carta da parati</span><span class="action-desc">...</span>
                                    </div>
                                    <div class="action-marker"><i class="fas fa-chevron-up"></i></div>
                                </div>
                                <div class="action-pane full">
                                    <div class="pane-header d-flex">
                                        <h4 class="pane-heading me-auto">Qualità della carta da parati</h4>
                                        <i class="fas fa-chevron-down close-pane"></i>
                                    </div>
                                    <hr />
                                    <div class="pane-content">
                                        <div class="pane-plans">
                                            <div class="row">
                                                <div class="col-12">
                                                    <div class="plan" id="plan-standard">
                                                        <div class="plan-header">
                                                            <div class="form-check form-check-inline">
                                                                <label class="form-check-label">
                                                                    <input class="form-check-input" type="radio" name="quality[]" id="" value="Standard" />
                                                                    <span>Standard</span>
                                                                </label>
                                                            </div>
                                                        </div>
                                                        <div class="plan-content">
                                                            <span class="plan-desc">
                                                                <p>
                                                                    La carta da parati standard è realizzata in tessuto non tessuto, un materiale dimensionalmente stabile e durevole. Colla per carta da parati inclusa.
                                                                </p>
                                                            </span>
                                                            <ul class="plan-benefits">
                                                                <li>La colla viene applicata al muro</li>
                                                                <li>Non sbiadisce alla luce del sole</li>
                                                                <li>Ecologico</li>
                                                                <li>Classificato per la protezione antincendio</li>
                                                            </ul>
                                                            <div class="plan-price">
                                                                <span class="plan-currency">€</span> <span class="plan-amount" data-amount="<?php echo $product->get_price(); ?>"><?php echo $product->get_price(); ?></span> /
                                                                <span class="plan-unit">m²</span>
                                                            </div>
                                                            <div class="select-plan mt-2">
                                                                <div class="d-grid gap-2">
                                                                    <button
                                                                        type="button"
                                                                        name="standard-quality"
                                                                        id="standard-quality"
                                                                        class="btn btn-primary p-3"
                                                                    >
                                                                        Qualità Standard
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="action-button disabled" id="add-to-cart">
                                <div class="action-icon"><i class="fas fa-shopping-cart"></i></div>
                                <div class="action-content">

                                    <span class="action-name">
                                        Aggiungi al carrello:
                                    <span class="total-price">...</span></span>
                                    <span class="action-desc">L'ordine verrà spedito entro 10 giorni</span>
                                </div>
                                <div class="action-marker"><i class="fas fa-arrow-right"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="p-modals">
                    <div class="p-modal" id="intial-config">
                        <div class="p-modal-content d-flex justify-content-center flex-column">
                            <h6>Inserisci la dimensione dello sfondo</h6>
                            <span class="small-text-sm">Misura l'area che vuoi tappezzare e inserisci la larghezza e l'altezza</span>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <div class="input-group p-settings" id="p-width">
                                        <span class="input-group-text"><i class="fas fa-arrows-alt-h"></i></span>
                                        <input type="number" data-min="<?php echo $product->get_meta('cd_min_width'); ?>" name="p-width" class="form-control" placeholder="Larghezza" aria-label="Width" />
                                        <span class="input-group-text"><small>cm</small></span>
                                        <div class="feedback"></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="input-group p-settings" id="p-height">
                                        <span class="input-group-text"><i class="fas fa-arrows-alt-v"></i></span>
                                        <input type="number" data-min="<?php echo $product->get_meta('cd_min_height'); ?>" name="p-height" class="form-control" placeholder="Altezza" aria-label="Height" />
                                        <span class="input-group-text"><small>cm</small></span>
                                        <div class="feedback"></div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="p-tooltip mt-2 mb-2">
                                        <span class="p-view-tooltip">Aggiungi altri 6-10 centimetri per il margine.</span>
                                        <p class="p-tooltip-message">
                                            Poiché pareti e soffitti non sono sempre completamente diritti, si consiglia di aggiungere da 6 a 10 centimetri sia in larghezza che in altezza per il margine durante il montaggio.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <a name="start" id="start" class="btn btn-sm btn-primary" href="#" role="button">Mostra sfondo e prezzo</a>

                            <form style="display: none" action="" id="cd-cart" method="post" enctype="multipart/form-data" >
                                <input type="hidden" name="area_needed" id="cd_area_needed">

                                <input type="hidden" name="cd_width" id="cd_width">
                                <input type="hidden" name="cd_height" id="cd_height">

                                <input type="hidden" name="cd_mirrored" id="cd_mirrored">
                                <input type="hidden" name="cd_whole" id="cd_whole">

                                <input type="hidden" id="cd_measurement_needed" name="_measurement_needed">
                                <input type="hidden" id="cd_measurement_needed_unit" name="_measurement_needed_unit" value="sq mm">

                                <input type="hidden" name="add-to-cart" value="<?php echo $product->get_id(); ?>">
                                <input type="hidden" name="quantity" value="1">
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function get_plugin_url() {
        if ( null === $this->plugin_url ) {
            $this->plugin_url = untrailingslashit( plugins_url( '/', __FILE__ ) );
        }

        return $this->plugin_url;
    }
}

CoverDesign::get_instance()->init();
