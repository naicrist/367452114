<?php
/**
 * Gestión avanzada de programación de publicaciones para Business Smart Card Agent
 * 
 * @package AvatarParlante
 */

defined('ABSPATH') or die('Acceso denegado');

class Schedule_Manager {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_avatar_parlante_get_posts', [$this, 'get_posts_ajax']);
        add_action('wp_ajax_avatar_parlante_save_schedule', [$this, 'save_schedule_ajax']);
        add_action('wp_ajax_avatar_parlante_toggle_schedule', [$this, 'toggle_schedule_ajax']);
        add_action('wp_ajax_avatar_parlante_renew_schedule', [$this, 'renew_schedule_ajax']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('avatar_parlante_daily_check', [$this, 'check_scheduled_items']);
        
        // Programar evento diario si no existe
        if (!wp_next_scheduled('avatar_parlante_daily_check')) {
            wp_schedule_event(time(), 'daily', 'avatar_parlante_daily_check');
        }
    }

    /**
     * Añade el menú de administración
     */
    public function add_admin_menu() {
        add_submenu_page(
            'avatar_parlante',
            __('Programación de Publicaciones', 'business-smart-card-agent'),
            __('Programación', 'business-smart-card-agent'),
            'manage_options',
            'avatar_parlante_schedule',
            [$this, 'render_schedule_page']
        );
    }

    /**
     * Registra las opciones de configuración
     */
    public function register_settings() {
        register_setting(
            'avatar_parlante_schedule_settings',
            'avatar_parlante_schedule_settings',
            [$this, 'sanitize_settings']
        );
    }

    /**
     * Renderiza la página principal de programación
     */
    public function render_schedule_page() {
        $post_types = $this->get_available_post_types();
        ?>
        <div class="wrap schedule-manager">
            <h1><?php _e('Programación de Publicaciones', 'business-smart-card-agent'); ?></h1>
            
            <div class="schedule-container">
                <!-- Panel de Control -->
                <div class="schedule-control-panel">
                    <h2><?php _e('Nueva Programación', 'business-smart-card-agent'); ?></h2>
                    
                    <div class="form-group">
                        <label for="schedule-post-type"><?php _e('Tipo de Contenido:', 'business-smart-card-agent'); ?></label>
                        <select id="schedule-post-type" class="regular-text">
                            <option value=""><?php _e('Seleccionar...', 'business-smart-card-agent'); ?></option>
                            <?php foreach ($post_types as $type) : ?>
                                <option value="<?php echo esc_attr($type->name); ?>">
                                    <?php echo esc_html($type->labels->singular_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="schedule-post-id"><?php _e('Publicación:', 'business-smart-card-agent'); ?></label>
                        <select id="schedule-post-id" class="regular-text" disabled>
                            <option value=""><?php _e('Seleccione un tipo primero', 'business-smart-card-agent'); ?></option>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="invoice-number"><?php _e('N° Factura:', 'business-smart-card-agent'); ?></label>
                            <input type="text" id="invoice-number" class="regular-text" placeholder="<?php _e('Ej: FAC-001', 'business-smart-card-agent'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="schedule-duration"><?php _e('Duración:', 'business-smart-card-agent'); ?></label>
                            <select id="schedule-duration" class="regular-text">
                                <?php for ($i = 1; $i <= 10; $i++) : ?>
                                    <option value="<?php echo $i; ?>">
                                        <?php printf(_n('%d año', '%d años', $i, 'business-smart-card-agent'), $i); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="start-date"><?php _e('Fecha Inicio:', 'business-smart-card-agent'); ?></label>
                            <input type="date" id="start-date" class="regular-text" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="end-date"><?php _e('Fecha Fin:', 'business-smart-card-agent'); ?></label>
                            <input type="date" id="end-date" class="regular-text" readonly>
                        </div>
                    </div>
                    
                    <div class="form-group status-indicator">
                        <label><?php _e('Estado:', 'business-smart-card-agent'); ?></label>
                        <span id="schedule-status" class="status-badge inactive"><?php _e('Inactivo', 'business-smart-card-agent'); ?></span>
                        <span id="days-remaining" class="days-remaining"></span>
                    </div>
                    
                    <div class="form-actions">
                        <button id="save-schedule" class="button button-primary" disabled>
                            <?php _e('Guardar Programación', 'business-smart-card-agent'); ?>
                        </button>
                        <button id="renew-schedule" class="button" disabled>
                            <?php _e('Renovar', 'business-smart-card-agent'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Listado de Programaciones -->
                <div class="schedule-list-panel">
                    <h2><?php _e('Publicaciones Programadas', 'business-smart-card-agent'); ?></h2>
                    
                    <div class="filters">
                        <select id="filter-status" class="regular-text">
                            <option value="all"><?php _e('Todas las publicaciones', 'business-smart-card-agent'); ?></option>
                            <option value="active"><?php _e('Activas', 'business-smart-card-agent'); ?></option>
                            <option value="inactive"><?php _e('Inactivas', 'business-smart-card-agent'); ?></option>
                            <option value="expired"><?php _e('Expiradas', 'business-smart-card-agent'); ?></option>
                            <option value="pending"><?php _e('Pendientes', 'business-smart-card-agent'); ?></option>
                        </select>
                        
                        <input type="text" id="search-invoice" class="regular-text" placeholder="<?php _e('Buscar por N° Factura...', 'business-smart-card-agent'); ?>">
                    </div>
                    
                    <div class="schedule-table-container">
                        <table id="schedules-table" class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Publicación', 'business-smart-card-agent'); ?></th>
                                    <th><?php _e('Tipo', 'business-smart-card-agent'); ?></th>
                                    <th><?php _e('N° Factura', 'business-smart-card-agent'); ?></th>
                                    <th><?php _e('Duración', 'business-smart-card-agent'); ?></th>
                                    <th><?php _e('Periodo', 'business-smart-card-agent'); ?></th>
                                    <th><?php _e('Estado', 'business-smart-card-agent'); ?></th>
                                    <th><?php _e('Días Restantes', 'business-smart-card-agent'); ?></th>
                                    <th><?php _e('Acciones', 'business-smart-card-agent'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="8" class="loading-text">
                                        <?php _e('Cargando publicaciones programadas...', 'business-smart-card-agent'); ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Obtiene los tipos de post disponibles
     */
    private function get_available_post_types() {
        $excluded = ['attachment', 'revision', 'nav_menu_item'];
        $post_types = get_post_types(['public' => true], 'objects');
        
        return array_filter($post_types, function($type) use ($excluded) {
            return !in_array($type->name, $excluded);
        });
    }

    /**
     * AJAX: Obtiene publicaciones por tipo
     */
    public function get_posts_ajax() {
        check_ajax_referer('avatar_parlante_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para esta acción', 'business-smart-card-agent'));
        }

        $post_type = sanitize_text_field($_POST['post_type']);
        $posts = get_posts([
            'post_type' => $post_type,
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => 'publish'
        ]);

        $options = [];
        foreach ($posts as $post) {
            $options[] = [
                'id' => $post->ID,
                'text' => $post->post_title,
                'has_schedule' => $this->has_schedule($post->ID)
            ];
        }

        wp_send_json_success([
            'posts' => $options
        ]);
    }

    /**
     * Verifica si una publicación ya tiene programación
     */
    private function has_schedule($post_id) {
        $schedule = get_post_meta($post_id, '_avatar_parlante_schedule', true);
        return !empty($schedule);
    }

    /**
     * AJAX: Guarda una nueva programación
     */
    public function save_schedule_ajax() {
        check_ajax_referer('avatar_parlante_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para esta acción', 'business-smart-card-agent'));
        }

        $post_id = absint($_POST['post_id']);
        $invoice = sanitize_text_field($_POST['invoice']);
        $duration = absint($_POST['duration']);
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);

        // Validar datos
        if (!$post_id || !$invoice || !$duration || !$start_date || !$end_date) {
            wp_send_json_error(__('Datos incompletos', 'business-smart-card-agent'));
        }

        // Crear array de programación
        $schedule = [
            'invoice' => $invoice,
            'duration' => $duration,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'is_active' => true,
            'created_at' => current_time('mysql'),
            'created_by' => get_current_user_id()
        ];

        // Guardar en post meta
        update_post_meta($post_id, '_avatar_parlante_schedule', $schedule);

        // Registrar evento
        $this->log_schedule_event($post_id, 'schedule_created', $schedule);

        wp_send_json_success([
            'message' => __('Programación guardada correctamente', 'business-smart-card-agent'),
            'schedule' => $this->format_schedule_data($post_id, $schedule)
        ]);
    }

    /**
     * AJAX: Activa/desactiva una programación
     */
    public function toggle_schedule_ajax() {
        check_ajax_referer('avatar_parlante_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para esta acción', 'business-smart-card-agent'));
        }

        $post_id = absint($_POST['post_id']);
        $action = sanitize_text_field($_POST['action']); // 'activate' o 'deactivate'
        
        $schedule = get_post_meta($post_id, '_avatar_parlante_schedule', true);
        
        if (empty($schedule)) {
            wp_send_json_error(__('No se encontró la programación', 'business-smart-card-agent'));
        }

        $schedule['is_active'] = ($action === 'activate');
        update_post_meta($post_id, '_avatar_parlante_schedule', $schedule);

        // Registrar evento
        $this->log_schedule_event($post_id, 'schedule_' . $action);

        wp_send_json_success([
            'message' => $action === 'activate' 
                ? __('Programación activada', 'business-smart-card-agent') 
                : __('Programación desactivada', 'business-smart-card-agent'),
            'status' => $this->get_schedule_status($schedule)
        ]);
    }

    /**
     * AJAX: Renueva una programación existente
     */
    public function renew_schedule_ajax() {
        check_ajax_referer('avatar_parlante_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para esta acción', 'business-smart-card-agent'));
        }

        $post_id = absint($_POST['post_id']);
        $invoice = sanitize_text_field($_POST['invoice']);
        $duration = absint($_POST['duration']);
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);

        // Obtener programación existente
        $existing = get_post_meta($post_id, '_avatar_parlante_schedule', true);
        
        if (empty($existing)) {
            wp_send_json_error(__('No se encontró la programación original', 'business-smart-card-agent'));
        }

        // Crear nueva programación
        $new_schedule = [
            'invoice' => $invoice,
            'duration' => $duration,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'is_active' => true,
            'created_at' => current_time('mysql'),
            'created_by' => get_current_user_id(),
            'previous_schedule' => $existing['start_date'] . ' - ' . $existing['end_date']
        ];

        update_post_meta($post_id, '_avatar_parlante_schedule', $new_schedule);

        // Registrar evento
        $this->log_schedule_event($post_id, 'schedule_renewed', [
            'old_period' => $existing['start_date'] . ' - ' . $existing['end_date'],
            'new_period' => $new_schedule['start_date'] . ' - ' . $new_schedule['end_date']
        ]);

        wp_send_json_success([
            'message' => __('Programación renovada correctamente', 'business-smart-card-agent'),
            'schedule' => $this->format_schedule_data($post_id, $new_schedule)
        ]);
    }

    /**
     * Verifica y actualiza programaciones expiradas
     */
    public function check_scheduled_items() {
        $scheduled_posts = get_posts([
            'post_type' => array_keys($this->get_available_post_types()),
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_avatar_parlante_schedule',
                    'compare' => 'EXISTS'
                ]
            ]
        ]);

        $today = current_time('Y-m-d');
        
        foreach ($scheduled_posts as $post) {
            $schedule = get_post_meta($post->ID, '_avatar_parlante_schedule', true);
            
            if ($today > $schedule['end_date'] && $schedule['is_active']) {
                $schedule['is_active'] = false;
                update_post_meta($post->ID, '_avatar_parlante_schedule', $schedule);
                
                // Registrar evento
                $this->log_schedule_event($post->ID, 'schedule_expired');
            }
        }
    }

    /**
     * Formatea los datos de programación para la respuesta
     */
    private function format_schedule_data($post_id, $schedule) {
        $post = get_post($post_id);
        $post_type = get_post_type_object($post->post_type);
        
        $status = $this->get_schedule_status($schedule);
        $days_remaining = $this->get_days_remaining($schedule['end_date']);
        
        return [
            'post_id' => $post_id,
            'post_title' => $post->post_title,
            'post_type' => $post_type->labels->singular_name,
            'invoice' => $schedule['invoice'],
            'duration' => sprintf(_n('%d año', '%d años', $schedule['duration'], 'business-smart-card-agent'), $schedule['duration']),
            'period' => date_i18n(get_option('date_format'), strtotime($schedule['start_date'])) . ' - ' . 
                        date_i18n(get_option('date_format'), strtotime($schedule['end_date'])),
            'status' => $status,
            'status_class' => $status,
            'days_remaining' => $days_remaining,
            'is_active' => $schedule['is_active']
        ];
    }

    /**
     * Obtiene el estado de una programación
     */
    private function get_schedule_status($schedule) {
        $today = current_time('Y-m-d');
        
        if (!$schedule['is_active']) {
            return 'inactive';
        }
        
        if ($today < $schedule['start_date']) {
            return 'pending';
        }
        
        if ($today > $schedule['end_date']) {
            return 'expired';
        }
        
        return 'active';
    }

    /**
     * Calcula los días restantes para una fecha
     */
    private function get_days_remaining($end_date) {
        $end = new DateTime($end_date);
        $today = new DateTime(current_time('Y-m-d'));
        $interval = $today->diff($end);
        
        return $interval->invert ? 0 : $interval->days;
    }

    /**
     * Registra un evento de programación
     */
    private function log_schedule_event($post_id, $action, $data = []) {
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'action' => $action,
            'data' => $data
        ];
        
        add_post_meta($post_id, '_avatar_parlante_schedule_log', $log_entry);
    }

    /**
     * Sanitiza los ajustes antes de guardar
     */
    public function sanitize_settings($input) {
        $output = [];
        
        if (isset($input['enable_auto_reports'])) {
            $output['enable_auto_reports'] = (bool) $input['enable_auto_reports'];
        }
        
        if (isset($input['report_frequency'])) {
            $output['report_frequency'] = sanitize_text_field($input['report_frequency']);
        }
        
        if (isset($input['report_recipients'])) {
            $output['report_recipients'] = sanitize_textarea_field($input['report_recipients']);
        }
        
        return $output;
    }

    /**
     * Carga los assets necesarios
     */
    public function enqueue_assets($hook) {
        if ($hook !== 'business-smart-card-agent_page_avatar_parlante_schedule') {
            return;
        }

        wp_enqueue_style(
            'business-smart-card-agent-schedule',
            plugins_url('assets/css/schedule.css', dirname(__FILE__)),
            [],
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/css/schedule.css')
        );

        wp_enqueue_script(
            'business-smart-card-agent-schedule',
            plugins_url('assets/js/schedule.js', dirname(__FILE__)),
            ['jquery', 'jquery-ui-datepicker'],
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/js/schedule.js'),
            true
        );

        wp_localize_script(
            'business-smart-card-agent-schedule',
            'scheduleData',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('avatar_parlante_nonce'),
                'i18n' => [
                    'loading' => __('Cargando...', 'business-smart-card-agent'),
                    'saving' => __('Guardando...', 'business-smart-card-agent'),
                    'renewing' => __('Renovando...', 'business-smart-card-agent'),
                    'confirm_deactivate' => __('¿Desactivar esta programación?', 'business-smart-card-agent'),
                    'confirm_activate' => __('¿Activar esta programación?', 'business-smart-card-agent'),
                    'confirm_renew' => __('¿Renovar esta programación?', 'business-smart-card-agent'),
                    'no_posts' => __('No se encontraron publicaciones', 'business-smart-card-agent')
                ]
            ]
        );

        // Estilos para el datepicker
        wp_enqueue_style('jquery-ui', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
    }
}

new Schedule_Manager();