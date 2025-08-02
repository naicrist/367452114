<?php
/**
 * Gestiona la configuración y renderizado del avatar
 */
class Avatar_Manager {

    private $available_avatars = array(
        'avatar1' => 'Avatar Clásico',
        'avatar2' => 'Avatar Moderno',
        'avatar3' => 'Avatar Robot',
        'avatar4' => 'Avatar Asistente'
    );

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_save_avatar_settings', array($this, 'save_avatar_settings_ajax'));
        add_filter('avatar_parlante_available_avatars', array($this, 'add_custom_avatars'));
    }

    /**
     * Añadir menú de administración
     */
    public function add_admin_menu() {
        add_submenu_page(
            'avatar_parlante',
            __('Configuración del Avatar', 'avatar-parlante'),
            __('Avatar', 'avatar-parlante'),
            'manage_options',
            'avatar_parlante_avatar',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Renderizar página de configuración
     */
    public function render_settings_page() {
        $current_avatar = get_option('avatar_parlante_current_avatar', 'avatar1');
        $custom_css = get_option('avatar_parlante_custom_css', '');
        ?>
        <div class="wrap">
            <h1><?php _e('Configuración del Avatar', 'avatar-parlante'); ?></h1>
            
            <form method="post" id="avatar-settings-form">
                <div class="avatar-form-section">
                    <h2><?php _e('Selección del Avatar', 'avatar-parlante'); ?></h2>
                    
                    <div class="avatar-selector">
                        <?php foreach ($this->get_available_avatars() as $id => $name) : ?>
                            <div class="avatar-option <?php echo $id === $current_avatar ? 'selected' : ''; ?>">
                                <input type="radio" id="avatar-<?php echo esc_attr($id); ?>" 
                                       name="avatar" value="<?php echo esc_attr($id); ?>" 
                                       <?php checked($id, $current_avatar); ?>>
                                <label for="avatar-<?php echo esc_attr($id); ?>">
                                    <img src="<?php echo esc_url(AVATAR_PARLANTE_PLUGIN_URL . 'assets/images/avatars/' . $id . '.png'); ?>" 
                                         alt="<?php echo esc_attr($name); ?>">
                                    <?php echo esc_html($name); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="avatar-form-section">
                    <h2><?php _e('Personalización CSS', 'avatar-parlante'); ?></h2>
                    <textarea name="custom_css" class="large-text code" rows="10"><?php echo esc_textarea($custom_css); ?></textarea>
                    <p class="description">
                        <?php _e('CSS personalizado para el avatar. Ejemplo:', 'avatar-parlante'); ?>
                        <code>.avatar-image { border: 3px solid #0073aa; }</code>
                    </p>
                </div>

                <?php submit_button(__('Guardar Cambios', 'avatar-parlante')); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Guardar configuración via AJAX
     */
    public function save_avatar_settings_ajax() {
        check_ajax_referer('avatar_parlante_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para realizar esta acción', 'avatar-parlante'));
        }

        $avatar = sanitize_text_field($_POST['avatar']);
        $custom_css = sanitize_textarea_field($_POST['custom_css']);

        update_option('avatar_parlante_current_avatar', $avatar);
        update_option('avatar_parlante_custom_css', $custom_css);

        wp_send_json_success(__('Configuración del avatar guardada correctamente', 'avatar-parlante'));
    }

    /**
     * Añadir avatares personalizados via filtro
     */
    public function add_custom_avatars($avatars) {
        return apply_filters('avatar_parlante_custom_avatars', $avatars);
    }

    /**
     * Obtener avatares disponibles
     */
    public function get_available_avatars() {
        return $this->available_avatars;
    }

    /**
     * Renderizar shortcode del avatar
     */
    public function render_avatar_shortcode($atts) {
        $atts = shortcode_atts(array(
            'avatar' => get_option('avatar_parlante_current_avatar', 'avatar1'),
            'autoplay' => 'no',
            'position' => 'bottom-right'
        ), $atts);

        ob_start();
        include AVATAR_PARLANTE_PLUGIN_DIR . 'templates/frontend/avatar.php';
        return ob_get_clean();
    }
}