<?php
/**
 * Gestiona la configuración de voz
 */
class Voice_Settings {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_save_voice_settings', array($this, 'save_voice_settings_ajax'));
        add_action('wp_ajax_test_voice', array($this, 'test_voice_ajax'));
    }

    /**
     * Añadir menú de administración
     */
    public function add_admin_menu() {
        add_submenu_page(
            'avatar_parlante',
            __('Configuración de Voz', 'avatar-parlante'),
            __('Voz', 'avatar-parlante'),
            'manage_options',
            'avatar_parlante_voice',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Renderizar página de configuración
     */
    public function render_settings_page() {
        $settings = $this->get_current_settings();
        ?>
        <div class="wrap">
            <h1><?php _e('Configuración de Voz', 'avatar-parlante'); ?></h1>
            
            <form method="post" id="voice-settings-form">
                <div class="avatar-form-section">
                    <h2><?php _e('Ajustes de Voz', 'avatar-parlante'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="voice-lang"><?php _e('Idioma', 'avatar-parlante'); ?></label>
                            </th>
                            <td>
                                <select name="voice_lang" id="voice-lang" class="regular-text">
                                    <option value="es-ES" <?php selected($settings['voice_lang'], 'es-ES'); ?>>
                                        Español (España)
                                    </option>
                                    <option value="es-MX" <?php selected($settings['voice_lang'], 'es-MX'); ?>>
                                        Español (México)
                                    </option>
                                    <option value="en-US" <?php selected($settings['voice_lang'], 'en-US'); ?>>
                                        English (US)
                                    </option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="voice-type"><?php _e('Tipo de Voz', 'avatar-parlante'); ?></label>
                            </th>
                            <td>
                                <select name="voice_type" id="voice-type" class="regular-text">
                                    <option value="female" <?php selected($settings['voice_type'], 'female'); ?>>
                                        Femenina
                                    </option>
                                    <option value="male" <?php selected($settings['voice_type'], 'male'); ?>>
                                        Masculina
                                    </option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="voice-speed"><?php _e('Velocidad', 'avatar-parlante'); ?></label>
                            </th>
                            <td>
                                <input type="range" name="voice_speed" id="voice-speed" 
                                       min="0.5" max="2" step="0.1" 
                                       value="<?php echo esc_attr($settings['voice_speed']); ?>">
                                <span class="range-value"><?php echo esc_html($settings['voice_speed']); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="voice-pitch"><?php _e('Tono', 'avatar-parlante'); ?></label>
                            </th>
                            <td>
                                <input type="range" name="voice_pitch" id="voice-pitch" 
                                       min="0.5" max="2" step="0.1" 
                                       value="<?php echo esc_attr($settings['voice_pitch']); ?>">
                                <span class="range-value"><?php echo esc_html($settings['voice_pitch']); ?></span>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="avatar-form-section" id="voice-test-container" style="display: none;">
                    <h2><?php _e('Probar Voz', 'avatar-parlante'); ?></h2>
                    <textarea id="voice-test-text" class="large-text" rows="3">
                        <?php _e('Hola, esta es una prueba de cómo sonará mi voz con la configuración actual.', 'avatar-parlante'); ?>
                    </textarea>
                    <button type="button" id="test-voice-btn" class="button button-primary">
                        <?php _e('Probar Voz', 'avatar-parlante'); ?>
                    </button>
                </div>

                <?php submit_button(__('Guardar Cambios', 'avatar-parlante')); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Guardar configuración via AJAX
     */
    public function save_voice_settings_ajax() {
        check_ajax_referer('avatar_parlante_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para realizar esta acción', 'avatar-parlante'));
        }

        $settings = array(
            'voice_lang' => sanitize_text_field($_POST['voice_lang']),
            'voice_type' => sanitize_text_field($_POST['voice_type']),
            'voice_speed' => floatval($_POST['voice_speed']),
            'voice_pitch' => floatval($_POST['voice_pitch'])
        );

        update_option('avatar_parlante_voice_settings', $settings);

        wp_send_json_success(__('Configuración de voz guardada correctamente', 'avatar-parlante'));
    }

    /**
     * Probar configuración de voz via AJAX
     */
    public function test_voice_ajax() {
        check_ajax_referer('avatar_parlante_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para realizar esta acción', 'avatar-parlante'));
        }

        $text = sanitize_text_field($_POST['text']);
        $settings = $this->get_current_settings();

        wp_send_json_success(array(
            'text' => $text,
            'settings' => $settings
        ));
    }

    /**
     * Obtener configuración actual
     */
    public function get_current_settings() {
        $defaults = array(
            'voice_lang' => 'es-ES',
            'voice_type' => 'female',
            'voice_speed' => 1.0,
            'voice_pitch' => 1.0
        );

        $saved = get_option('avatar_parlante_voice_settings', array());
        return wp_parse_args($saved, $defaults);
    }
}