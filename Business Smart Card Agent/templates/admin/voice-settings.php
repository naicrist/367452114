<?php
/**
 * Configuración avanzada de voz para Business Smart Card Agent
 * 
 * @package AvatarParlante
 */

defined('ABSPATH') or die('Acceso denegado');

class Voice_Settings {

    private $available_voices = [
        'es-ES' => [
            'female' => 'Español (Femenino)',
            'male' => 'Español (Masculino)'
        ],
        'es-MX' => [
            'female' => 'Español MX (Femenino)',
            'male' => 'Español MX (Masculino)'
        ],
        'en-US' => [
            'female' => 'English (Female)',
            'male' => 'English (Male)'
        ]
    ];

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_avatar_parlante_test_voice', [$this, 'test_voice_ajax']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Añade el menú de administración
     */
    public function add_admin_menu() {
        add_submenu_page(
            'avatar_parlante',
            __('Configuración de Voz', 'business-smart-card-agent'),
            __('Configuración de Voz', 'business-smart-card-agent'),
            'manage_options',
            'avatar_parlante_voice',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Registra las opciones de configuración
     */
    public function register_settings() {
        register_setting(
            'avatar_parlante_voice_settings',
            'avatar_parlante_voice_settings',
            [$this, 'sanitize_settings']
        );

        add_settings_section(
            'voice_main_settings',
            __('Ajustes Principales de Voz', 'business-smart-card-agent'),
            [$this, 'render_section_header'],
            'avatar_parlante_voice'
        );

        add_settings_field(
            'voice_lang',
            __('Idioma y Tipo de Voz', 'business-smart-card-agent'),
            [$this, 'render_voice_select'],
            'avatar_parlante_voice',
            'voice_main_settings'
        );

        add_settings_field(
            'voice_parameters',
            __('Ajustes de Voz', 'business-smart-card-agent'),
            [$this, 'render_voice_parameters'],
            'avatar_parlante_voice',
            'voice_main_settings'
        );

        add_settings_field(
            'voice_test',
            __('Prueba de Voz', 'business-smart-card-agent'),
            [$this, 'render_voice_test'],
            'avatar_parlante_voice',
            'voice_main_settings'
        );
    }

    /**
     * Renderiza la página de configuración
     */
    public function render_settings_page() {
        ?>
        <div class="wrap voice-settings-wrap">
            <h1><?php _e('Configuración de Voz del Business Smart Card Agent', 'business-smart-card-agent'); ?></h1>
            
            <form method="post" action="options.php" id="voice-settings-form">
                <?php
                settings_fields('avatar_parlante_voice_settings');
                do_settings_sections('avatar_parlante_voice');
                submit_button(__('Guardar Configuración', 'business-smart-card-agent'));
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Encabezado de sección
     */
    public function render_section_header() {
        echo '<p>' . __('Configura los parámetros de voz para tu Business Smart Card Agent.', 'business-smart-card-agent') . '</p>';
    }

    /**
     * Selector de idioma y tipo de voz
     */
    public function render_voice_select() {
        $settings = get_option('avatar_parlante_voice_settings', []);
        $current_lang = $settings['voice_lang'] ?? 'es-ES';
        $current_type = $settings['voice_type'] ?? 'female';
        ?>
        <div class="voice-selection-container">
            <select name="avatar_parlante_voice_settings[voice_lang]" id="voice-lang" class="regular-text">
                <?php foreach ($this->available_voices as $lang_code => $voices) : ?>
                    <option value="<?php echo esc_attr($lang_code); ?>" <?php selected($current_lang, $lang_code); ?>>
                        <?php echo $this->get_language_name($lang_code); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="avatar_parlante_voice_settings[voice_type]" id="voice-type" class="regular-text">
                <?php if (isset($this->available_voices[$current_lang])) : ?>
                    <?php foreach ($this->available_voices[$current_lang] as $type => $label) : ?>
                        <option value="<?php echo esc_attr($type); ?>" <?php selected($current_type, $type); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>
        <?php
    }

    /**
     * Parámetros ajustables de voz
     */
    public function render_voice_parameters() {
        $settings = get_option('avatar_parlante_voice_settings', []);
        $speed = $settings['voice_speed'] ?? 1.0;
        $pitch = $settings['voice_pitch'] ?? 1.0;
        ?>
        <div class="voice-parameters-container">
            <div class="voice-parameter">
                <label for="voice-speed"><?php _e('Velocidad:', 'business-smart-card-agent'); ?> <span class="parameter-value" id="speed-value"><?php echo number_format($speed, 1); ?></span></label>
                <input type="range" name="avatar_parlante_voice_settings[voice_speed]" id="voice-speed" 
                       min="0.5" max="2" step="0.1" value="<?php echo esc_attr($speed); ?>">
            </div>

            <div class="voice-parameter">
                <label for="voice-pitch"><?php _e('Tono:', 'business-smart-card-agent'); ?> <span class="parameter-value" id="pitch-value"><?php echo number_format($pitch, 1); ?></span></label>
                <input type="range" name="avatar_parlante_voice_settings[voice_pitch]" id="voice-pitch" 
                       min="0.5" max="2" step="0.1" value="<?php echo esc_attr($pitch); ?>">
            </div>
        </div>
        <?php
    }

    /**
     * Sección de prueba de voz
     */
    public function render_voice_test() {
        ?>
        <div class="voice-test-container">
            <textarea id="voice-test-text" class="large-text" rows="3" placeholder="<?php esc_attr_e('Escribe aquí el texto para probar la voz...', 'business-smart-card-agent'); ?>"></textarea>
            <button type="button" id="test-voice-btn" class="button button-primary">
                <?php _e('Probar Voz', 'business-smart-card-agent'); ?>
            </button>
            <div id="voice-test-result" class="test-result"></div>
        </div>
        <?php
    }

    /**
     * Sanitiza los ajustes antes de guardar
     */
    public function sanitize_settings($input) {
        $output = [];
        
        $output['voice_lang'] = sanitize_text_field($input['voice_lang']);
        $output['voice_type'] = sanitize_text_field($input['voice_type']);
        $output['voice_speed'] = floatval($input['voice_speed']);
        $output['voice_pitch'] = floatval($input['voice_pitch']);
        
        // Validar rangos
        $output['voice_speed'] = max(0.5, min(2.0, $output['voice_speed']));
        $output['voice_pitch'] = max(0.5, min(2.0, $output['voice_pitch']));
        
        return $output;
    }

    /**
     * Prueba de voz via AJAX
     */
    public function test_voice_ajax() {
        check_ajax_referer('avatar_parlante_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para esta acción', 'business-smart-card-agent'));
        }

        $text = isset($_POST['text']) ? sanitize_textarea_field($_POST['text']) : '';
        $settings = get_option('avatar_parlante_voice_settings', []);

        $response = [
            'success' => true,
            'data' => [
                'message' => __('Voz probada correctamente', 'business-smart-card-agent'),
                'settings' => $settings,
                'sample_output' => $this->generate_voice_sample($text, $settings)
            ]
        ];

        wp_send_json($response);
    }

    /**
     * Genera un ejemplo de salida de voz
     */
    private function generate_voice_sample($text, $settings) {
        $lang = $settings['voice_lang'] ?? 'es-ES';
        $type = $settings['voice_type'] ?? 'female';
        
        $sample = sprintf(
            __('Texto: "%s" | Idioma: %s | Voz: %s | Velocidad: %s | Tono: %s', 'business-smart-card-agent'),
            $text,
            $this->get_language_name($lang),
            $type === 'female' ? __('Femenina', 'business-smart-card-agent') : __('Masculina', 'business-smart-card-agent'),
            $settings['voice_speed'],
            $settings['voice_pitch']
        );
        
        return $sample;
    }

    /**
     * Obtiene el nombre completo del idioma
     */
    private function get_language_name($code) {
        $languages = [
            'es-ES' => __('Español (España)', 'business-smart-card-agent'),
            'es-MX' => __('Español (México)', 'business-smart-card-agent'),
            'en-US' => __('Inglés (EE.UU.)', 'business-smart-card-agent')
        ];
        
        return $languages[$code] ?? $code;
    }

    /**
     * Carga los assets necesarios
     */
    public function enqueue_assets($hook) {
        if (strpos($hook, 'avatar_parlante_voice') === false) {
            return;
        }

        wp_enqueue_style(
            'business-smart-card-agent-voice-settings',
            plugins_url('assets/css/voice-settings.css', dirname(__FILE__)),
            [],
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/css/voice-settings.css')
        );

        wp_enqueue_script(
            'business-smart-card-agent-voice-settings',
            plugins_url('assets/js/voice-settings.js', dirname(__FILE__)),
            ['jquery'],
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/js/voice-settings.js'),
            true
        );

        wp_localize_script(
            'business-smart-card-agent-voice-settings',
            'avatarVoiceData',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('avatar_parlante_nonce'),
                'i18n' => [
                    'testing' => __('Probando voz...', 'business-smart-card-agent'),
                    'error' => __('Error al probar la voz', 'business-smart-card-agent'),
                    'browser_not_supported' => __('Tu navegador no soporta la síntesis de voz', 'business-smart-card-agent')
                ]
            ]
        );
    }
}

new Voice_Settings();