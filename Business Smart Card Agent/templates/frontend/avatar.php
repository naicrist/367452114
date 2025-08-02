<?php
/**
 * Avatar Parlante - Frontend
 * 
 * @package TarjetaDigital
 */

if (!defined('ABSPATH')) {
    exit; // Seguridad
}

class TarjetaDigital_Avatar {

    private static $instance;

    public static function get_instance() {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Registra shortcode [avatar_parlante]
        add_shortcode('avatar_parlante', [$this, 'render_avatar']);

        // Carga assets
        add_action('wp_enqueue_scripts', [$this, 'load_assets']);
    }

    /**
     * Renderiza el avatar en el frontend
     */
    public function render_avatar($atts) {
        $atts = shortcode_atts([
            'voice'    => get_option('td_voice_type', 'female'),
            'speed'    => get_option('td_voice_speed', 1),
            'language' => get_option('td_voice_lang', 'es-ES')
        ], $atts);

        ob_start();
        ?>
        <div class="td-avatar-container" 
             data-voice="<?= esc_attr($atts['voice']) ?>" 
             data-speed="<?= esc_attr($atts['speed']) ?>" 
             data-lang="<?= esc_attr($atts['language']) ?>">
            
            <div class="td-avatar-image">
                <img src="<?= esc_url(plugins_url('assets/images/avatar-default.png', dirname(__FILE__))) ?>" alt="Avatar">
            </div>
            
            <div class="td-avatar-controls">
                <button class="td-speak-btn">Hablar</button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Carga JS/CSS necesarios
     */
    public function load_assets() {
        wp_enqueue_script(
            'td-frontend-js',
            plugins_url('assets/js/frontend.js', dirname(__FILE__)),
            ['jquery'],
            '1.0',
            true
        );

        wp_enqueue_style(
            'td-frontend-css',
            plugins_url('assets/css/frontend.css', dirname(__FILE__))
        );

        // Localiza el script para AJAX y configs
        wp_localize_script('td-frontend-js', 'tdSettings', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('td_avatar_nonce')
        ]);
    }
}

// Inicializa el avatar
TarjetaDigital_Avatar::get_instance();