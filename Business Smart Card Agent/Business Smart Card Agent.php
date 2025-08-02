<?php
/**
 * Plugin Name: Agente empresarial Inteligent Card
 * Description: Un avatar interactivo que lee contenido y responde preguntas usando Web Speech API
 * Version: 2.0
 * Author: Naicrist
 * Text Domain: Agente empresarial Inteligent Card
 * Domain Path: /languages
 */

defined('ABSPATH') or die('Acceso denegado');

// Definir constantes del plugin
define('BUSINESS_SMART_CARD_AGENT_VERSION', '2.0');
define('BUSINESS_SMART_CARD_AGENT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BUSINESS_SMART_CARD_AGENT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BUSINESS_SMART_CARD_AGENT_BASENAME', plugin_basename(__FILE__));

// Cargar archivos necesarios
require_once BUSINESS_SMART_CARD_AGENT_PLUGIN_DIR . 'includes/class-avatar-manager.php';
require_once BUSINESS_SMART_CARD_AGENT_PLUGIN_DIR . 'includes/class-voice-settings.php';
require_once BUSINESS_SMART_CARD_AGENT_PLUGIN_DIR . 'includes/class-schedule-manager.php';
require_once BUSINESS_SMART_CARD_AGENT_PLUGIN_DIR . 'includes/class-reports.php';
require_once BUSINESS_SMART_CARD_AGENT_PLUGIN_DIR . 'includes/class-ajax-handler.php';

class AvatarParlante {

    private static $instance = null;
    private $avatar_manager;
    private $voice_settings;
    private $schedule_manager;
    private $reports;
    private $ajax_handler;

    /**
     * Obtener instancia singleton
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor privado para singleton
     */
    private function __construct() {
        // Inicializar componentes
        $this->avatar_manager = new Avatar_Manager();
        $this->voice_settings = new Voice_Settings();
        $this->schedule_manager = new Schedule_Manager();
        $this->reports = new Reports();
        $this->ajax_handler = new Ajax_Handler();

        // Registrar hooks
        $this->register_hooks();
    }

    /**
     * Registrar todos los hooks de WordPress
     */
    private function register_hooks() {
        // Activación/desactivación
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array(__CLASS__, 'uninstall'));

        // Acciones y filtros
        add_action('plugins_loaded', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // Shortcode
        add_shortcode('BUSINESS_SMART_CARD_AGENT', array($this->avatar_manager, 'render_avatar_shortcode'));
    }

    /**
     * Activación del plugin
     */
    public function activate() {
        // Crear tablas necesarias
        $this->reports->create_tables();
        
        // Configuración por defecto
        add_option('BUSINESS_SMART_CARD_AGENT_settings', array(
            'default_avatar' => 'avatar1',
            'voice_speed' => 1.0,
            'voice_pitch' => 1.0,
            'voice_lang' => 'es-ES',
            'autoplay' => false,
            'enable_reporting' => true
        ));

        // Programar evento cron si es necesario
        if (!wp_next_scheduled('BUSINESS_SMART_CARD_AGENT_daily_report')) {
            wp_schedule_event(time(), 'daily', 'BUSINESS_SMART_CARD_AGENT_daily_report');
        }
    }

    /**
     * Desactivación del plugin
     */
    public function deactivate() {
        // Limpiar eventos programados
        wp_clear_scheduled_hook('BUSINESS_SMART_CARD_AGENT_daily_report');
    }

    /**
     * Desinstalación del plugin
     */
    public static function uninstall() {
        // Eliminar opciones
        delete_option('BUSINESS_SMART_CARD_AGENT_settings');
        delete_option('BUSINESS_SMART_CARD_AGENT_version');

        // Eliminar tablas personalizadas (opcional)
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}BUSINESS_SMART_CARD_AGENT_interactions");
    }

    /**
     * Inicialización del plugin
     */
    public function init() {
        // Cargar traducciones
        load_plugin_textdomain(
            'avatar-parlante',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );

        // Verificar actualizaciones
        $this->check_for_updates();
    }

    /**
     * Verificar actualizaciones del plugin
     */
    private function check_for_updates() {
        $current_version = get_option('BUSINESS_SMART_CARD_AGENT_version', '1.0');
        
        if (version_compare($current_version, BUSINESS_SMART_CARD_AGENT_VERSION, '<')) {
            // Realizar actualizaciones necesarias
            update_option('BUSINESS_SMART_CARD_AGENT_version', BUSINESS_SMART_CARD_AGENT_VERSION);
        }
    }

    /**
     * Cargar assets para el frontend
     */
    public function enqueue_frontend_assets() {
        // Estilos
        wp_enqueue_style(
            'avatar-parlante-frontend',
            BUSINESS_SMART_CARD_AGENT_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            BUSINESS_SMART_CARD_AGENT_VERSION
        );

        // Scripts
        wp_enqueue_script(
            'avatar-parlante-frontend',
            BUSINESS_SMART_CARD_AGENT_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            BUSINESS_SMART_CARD_AGENT_VERSION,
            true
        );

        // Localizar script
        wp_localize_script(
            'avatar-parlante-frontend',
            'avatarParlanteFrontend',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('BUSINESS_SMART_CARD_AGENT_nonce'),
                'settings' => $this->get_frontend_settings(),
                'i18n' => array(
                    'listening' => __('Escuchando...', 'avatar-parlante'),
                    'speaking' => __('Hablando...', 'avatar-parlante'),
                    'error' => __('Error', 'avatar-parlante')
                )
            )
        );
    }

    /**
     * Cargar assets para el admin
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'BUSINESS_SMART_CARD_AGENT') === false) {
            return;
        }

        // Estilos admin
        wp_enqueue_style(
            'avatar-parlante-admin',
            BUSINESS_SMART_CARD_AGENT_PLUGIN_URL . 'assets/css/admin.css',
            array('wp-color-picker'),
            BUSINESS_SMART_CARD_AGENT_VERSION
        );

        // Scripts admin
        wp_enqueue_script(
            'avatar-parlante-admin',
            BUSINESS_SMART_CARD_AGENT_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-color-picker', 'jquery-ui-datepicker'),
            BUSINESS_SMART_CARD_AGENT_VERSION,
            true
        );

        // Localizar script admin
        wp_localize_script(
            'avatar-parlante-admin',
            'avatarParlanteAdmin',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('BUSINESS_SMART_CARD_AGENT_admin_nonce'),
                'i18n' => array(
                    'saving' => __('Guardando...', 'avatar-parlante'),
                    'saved' => __('¡Guardado!', 'avatar-parlante'),
                    'error' => __('Error', 'avatar-parlante')
                )
            )
        );

        // Cargar estilos de datepicker
        wp_enqueue_style(
            'jquery-ui',
            'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css',
            array(),
            '1.12.1'
        );
    }

    /**
     * Registrar configuraciones
     */
    public function register_settings() {
        register_setting(
            'BUSINESS_SMART_CARD_AGENT_settings_group',
            'BUSINESS_SMART_CARD_AGENT_settings',
            array($this, 'sanitize_settings')
        );
    }

    /**
     * Sanitizar configuraciones
     */
    public function sanitize_settings($input) {
        $output = array();
        
        $output['default_avatar'] = sanitize_text_field($input['default_avatar']);
        $output['voice_speed'] = floatval($input['voice_speed']);
        $output['voice_pitch'] = floatval($input['voice_pitch']);
        $output['voice_lang'] = sanitize_text_field($input['voice_lang']);
        $output['autoplay'] = isset($input['autoplay']) ? true : false;
        $output['enable_reporting'] = isset($input['enable_reporting']) ? true : false;
        
        return $output;
    }

    /**
     * Obtener configuraciones para frontend
     */
    private function get_frontend_settings() {
        $settings = get_option('BUSINESS_SMART_CARD_AGENT_settings', array());
        
        return wp_parse_args($settings, array(
            'default_avatar' => 'avatar1',
            'voice_speed' => 1.0,
            'voice_pitch' => 1.0,
            'voice_lang' => 'es-ES',
            'autoplay' => false
        ));
    }
}

// Inicializar el plugin
AvatarParlante::get_instance();