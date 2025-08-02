<?php
/**
 * Sistema avanzado de reportes para el plugin Business Smart Card Agent
 * 
 * @package AvatarParlante
 */

defined('ABSPATH') or die('Acceso denegado');

class Reports_Manager {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_generate_voice_report', [$this, 'generate_report_ajax']);
        add_action('wp_ajax_export_voice_report', [$this, 'export_report_ajax']);
        add_action('wp_ajax_toggle_publication_status', [$this, 'toggle_publication_status']);
        add_action('wp_ajax_renew_publication', [$this, 'renew_publication']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Añade el menú de administración
     */
    public function add_admin_menu() {
        add_submenu_page(
            'avatar_parlante',
            __('Gestión de Publicaciones', 'business-smart-card-agent'),
            __('Publicaciones', 'business-smart-card-agent'),
            'manage_options',
            'avatar_parlante_publications',
            [$this, 'render_publications_page']
        );
    }

    /**
     * Registra las opciones de configuración
     */
    public function register_settings() {
        register_setting(
            'avatar_parlante_publication_settings',
            'avatar_parlante_publication_settings',
            [$this, 'sanitize_settings']
        );
    }

    /**
     * Renderiza la página principal de publicaciones
     */
    public function render_publications_page() {
        $publications = $this->get_all_publications();
        $voice_settings = get_option('avatar_parlante_voice_settings', []);
        ?>
        <div class="wrap publications-manager">
            <h1><?php _e('Gestión de Publicaciones', 'business-smart-card-agent'); ?></h1>
            
            <!-- Filtros de Publicaciones -->
            <div class="publication-filters">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="publication-status"><?php _e('Estado:', 'business-smart-card-agent'); ?></label>
                        <select id="publication-status" class="regular-text">
                            <option value="all"><?php _e('Todos', 'business-smart-card-agent'); ?></option>
                            <option value="active"><?php _e('Activos', 'business-smart-card-agent'); ?></option>
                            <option value="inactive"><?php _e('Inactivos', 'business-smart-card-agent'); ?></option>
                            <option value="expired"><?php _e('Expirados', 'business-smart-card-agent'); ?></option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="publication-type"><?php _e('Tipo:', 'business-smart-card-agent'); ?></label>
                        <select id="publication-type" class="regular-text">
                            <option value="all"><?php _e('Todos', 'business-smart-card-agent'); ?></option>
                            <option value="post"><?php _e('Artículos', 'business-smart-card-agent'); ?></option>
                            <option value="page"><?php _e('Páginas', 'business-smart-card-agent'); ?></option>
                            <option value="portfolio"><?php _e('Portafolio', 'business-smart-card-agent'); ?></option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <button id="apply-filters" class="button button-primary">
                            <?php _e('Aplicar Filtros', 'business-smart-card-agent'); ?>
                        </button>
                        <button id="reset-filters" class="button">
                            <?php _e('Restablecer', 'business-smart-card-agent'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Tabla de Publicaciones -->
            <div class="publication-list">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('ID', 'business-smart-card-agent'); ?></th>
                            <th><?php _e('Título', 'business-smart-card-agent'); ?></th>
                            <th><?php _e('Tipo', 'business-smart-card-agent'); ?></th>
                            <th><?php _e('N° Factura', 'business-smart-card-agent'); ?></th>
                            <th><?php _e('Fecha Inicio', 'business-smart-card-agent'); ?></th>
                            <th><?php _e('Duración', 'business-smart-card-agent'); ?></th>
                            <th><?php _e('Fecha Fin', 'business-smart-card-agent'); ?></th>
                            <th><?php _e('Estado', 'business-smart-card-agent'); ?></th>
                            <th><?php _e('Config. Voz', 'business-smart-card-agent'); ?></th>
                            <th><?php _e('Acciones', 'business-smart-card-agent'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($publications as $pub) : 
                            $is_active = $this->is_publication_active($pub);
                            $expiry_date = $this->calculate_expiry_date($pub);
                        ?>
                        <tr data-publication-id="<?php echo esc_attr($pub->ID); ?>">
                            <td><?php echo esc_html($pub->ID); ?></td>
                            <td><?php echo esc_html($pub->post_title); ?></td>
                            <td><?php echo esc_html($pub->post_type); ?></td>
                            <td>
                                <input type="text" class="invoice-number" 
                                       value="<?php echo esc_attr(get_post_meta($pub->ID, '_publication_invoice', true)); ?>"
                                       data-original-value="<?php echo esc_attr(get_post_meta($pub->ID, '_publication_invoice', true)); ?>">
                            </td>
                            <td>
                                <input type="date" class="start-date" 
                                       value="<?php echo esc_attr(get_post_meta($pub->ID, '_publication_start_date', true)); ?>">
                            </td>
                            <td>
                                <select class="duration">
                                    <?php for ($i = 1; $i <= 10; $i++) : ?>
                                        <option value="<?php echo $i; ?>" 
                                            <?php selected(get_post_meta($pub->ID, '_publication_duration', true), $i); ?>>
                                            <?php printf(_n('%d año', '%d años', $i, 'business-smart-card-agent'), $i); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </td>
                            <td class="expiry-date"><?php echo esc_html($expiry_date); ?></td>
                            <td class="status">
                                <span class="status-badge <?php echo $is_active ? 'active' : 'inactive'; ?>">
                                    <?php echo $is_active ? __('Activo', 'business-smart-card-agent') : __('Inactivo', 'business-smart-card-agent'); ?>
                                </span>
                            </td>
                            <td>
                                <button class="button configure-voice" 
                                        data-publication="<?php echo esc_attr($pub->ID); ?>"
                                        data-voice-config="<?php echo esc_attr(wp_json_encode($voice_settings)); ?>">
                                    <?php _e('Configurar', 'business-smart-card-agent'); ?>
                                </button>
                            </td>
                            <td>
                                <button class="button button-primary save-publication" 
                                        data-publication="<?php echo esc_attr($pub->ID); ?>">
                                    <?php _e('Guardar', 'business-smart-card-agent'); ?>
                                </button>
                                <button class="button renew-publication" 
                                        data-publication="<?php echo esc_attr($pub->ID); ?>">
                                    <?php _e('Renovar', 'business-smart-card-agent'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Modal de Configuración de Voz -->
            <div id="voice-config-modal" class="modal" style="display:none;">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <h2><?php _e('Configuración de Voz para Publicación', 'business-smart-card-agent'); ?></h2>
                    
                    <div class="voice-settings-container">
                        <div class="voice-setting">
                            <label for="modal-voice-lang"><?php _e('Idioma:', 'business-smart-card-agent'); ?></label>
                            <select id="modal-voice-lang" class="regular-text">
                                <option value="es-ES">Español</option>
                                <option value="en-US">English</option>
                            </select>
                        </div>
                        
                        <div class="voice-setting">
                            <label for="modal-voice-type"><?php _e('Tipo de Voz:', 'business-smart-card-agent'); ?></label>
                            <select id="modal-voice-type" class="regular-text">
                                <option value="female">Femenina</option>
                                <option value="male">Masculina</option>
                            </select>
                        </div>
                        
                        <div class="voice-setting">
                            <label for="modal-voice-speed"><?php _e('Velocidad:', 'business-smart-card-agent'); ?></label>
                            <input type="range" id="modal-voice-speed" min="0.5" max="2" step="0.1" value="1">
                            <span id="modal-speed-value">1.0</span>
                        </div>
                        
                        <div class="voice-setting">
                            <label for="modal-voice-pitch"><?php _e('Tono:', 'business-smart-card-agent'); ?></label>
                            <input type="range" id="modal-voice-pitch" min="0.5" max="2" step="0.1" value="1">
                            <span id="modal-pitch-value">1.0</span>
                        </div>
                        
                        <div class="voice-test">
                            <textarea id="modal-voice-test-text" rows="3" placeholder="<?php esc_attr_e('Texto de prueba...', 'business-smart-card-agent'); ?>"></textarea>
                            <button id="modal-test-voice" class="button"><?php _e('Probar Voz', 'business-smart-card-agent'); ?></button>
                        </div>
                    </div>
                    
                    <div class="modal-actions">
                        <button id="save-voice-config" class="button button-primary"><?php _e('Guardar Configuración', 'business-smart-card-agent'); ?></button>
                        <button id="cancel-voice-config" class="button"><?php _e('Cancelar', 'business-smart-card-agent'); ?></button>
                    </div>
                </div>
            </div>
            
            <!-- Sección de Reportes -->
            <div class="reports-section">
                <h2><?php _e('Generar Reportes', 'business-smart-card-agent'); ?></h2>
                
                <div class="report-actions">
                    <button id="generate-voice-report" class="button button-primary">
                        <?php _e('Generar Reporte de Voz', 'business-smart-card-agent'); ?>
                    </button>
                    <button id="export-voice-report" class="button">
                        <?php _e('Exportar a CSV', 'business-smart-card-agent'); ?>
                    </button>
                </div>
                
                <div id="report-results" class="report-results">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('ID', 'business-smart-card-agent'); ?></th>
                                <th><?php _e('Publicación', 'business-smart-card-agent'); ?></th>
                                <th><?php _e('N° Factura', 'business-smart-card-agent'); ?></th>
                                <th><?php _e('Tipo', 'business-smart-card-agent'); ?></th>
                                <th><?php _e('Fecha Activación', 'business-smart-card-agent'); ?></th>
                                <th><?php _e('Duración', 'business-smart-card-agent'); ?></th>
                                <th><?php _e('Estado', 'business-smart-card-agent'); ?></th>
                                <th><?php _e('Configuración Voz', 'business-smart-card-agent'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="8" class="no-results">
                                    <?php _e('Utilice los botones para generar un reporte', 'business-smart-card-agent'); ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Obtiene todas las publicaciones disponibles
     */
    private function get_all_publications() {
        $args = [
            'post_type' => ['post', 'page', 'portfolio'],
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ];
        
        return get_posts($args);
    }

    /**
     * Verifica si una publicación está activa
     */
    private function is_publication_active($publication) {
        $start_date = get_post_meta($publication->ID, '_publication_start_date', true);
        $duration = (int) get_post_meta($publication->ID, '_publication_duration', true);
        
        if (empty($start_date) return false;
        
        $start = new DateTime($start_date);
        $end = clone $start;
        $end->add(new DateInterval("P{$duration}Y"));
        $today = new DateTime();
        
        return ($today >= $start && $today <= $end);
    }

    /**
     * Calcula la fecha de expiración
     */
    private function calculate_expiry_date($publication) {
        $start_date = get_post_meta($publication->ID, '_publication_start_date', true);
        $duration = (int) get_post_meta($publication->ID, '_publication_duration', true);
        
        if (empty($start_date)) return __('No definida', 'business-smart-card-agent');
        
        $start = new DateTime($start_date);
        $start->add(new DateInterval("P{$duration}Y"));
        return $start->format('Y-m-d');
    }

    /**
     * AJAX: Genera reporte basado en filtros
     */
    public function generate_report_ajax() {
        check_ajax_referer('avatar_parlante_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para esta acción', 'business-smart-card-agent'));
        }

        $publications = $this->get_all_publications();
        $report_data = [];

        foreach ($publications as $pub) {
            $is_active = $this->is_publication_active($pub);
            $expiry_date = $this->calculate_expiry_date($pub);
            
            $voice_config = get_post_meta($pub->ID, '_voice_settings', true) ?: [];
            
            $report_data[] = [
                'ID' => $pub->ID,
                'title' => $pub->post_title,
                'type' => $pub->post_type,
                'invoice' => get_post_meta($pub->ID, '_publication_invoice', true),
                'start_date' => get_post_meta($pub->ID, '_publication_start_date', true),
                'duration' => get_post_meta($pub->ID, '_publication_duration', true) . ' ' . __('años', 'business-smart-card-agent'),
                'expiry_date' => $expiry_date,
                'status' => $is_active ? __('Activo', 'business-smart-card-agent') : __('Inactivo', 'business-smart-card-agent'),
                'voice_config' => !empty($voice_config) ? __('Configurada', 'business-smart-card-agent') : __('Sin configurar', 'business-smart-card-agent')
            ];
        }

        wp_send_json_success([
            'html' => $this->render_report_table($report_data)
        ]);
    }

    /**
     * AJAX: Exporta reporte a CSV
     */
    public function export_report_ajax() {
        check_ajax_referer('avatar_parlante_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para esta acción', 'business-smart-card-agent'));
        }

        $publications = $this->get_all_publications();
        $report_data = [];

        foreach ($publications as $pub) {
            $is_active = $this->is_publication_active($pub);
            $expiry_date = $this->calculate_expiry_date($pub);
            
            $voice_config = get_post_meta($pub->ID, '_voice_settings', true) ?: [];
            
            $report_data[] = [
                'ID' => $pub->ID,
                'title' => $pub->post_title,
                'type' => $pub->post_type,
                'invoice' => get_post_meta($pub->ID, '_publication_invoice', true),
                'start_date' => get_post_meta($pub->ID, '_publication_start_date', true),
                'duration' => get_post_meta($pub->ID, '_publication_duration', true) . ' ' . __('años', 'business-smart-card-agent'),
                'expiry_date' => $expiry_date,
                'status' => $is_active ? __('Activo', 'business-smart-card-agent') : __('Inactivo', 'business-smart-card-agent'),
                'voice_config' => !empty($voice_config) ? __('Configurada', 'business-smart-card-agent') : __('Sin configurar', 'business-smart-card-agent')
            ];
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=reporte_publicaciones_' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        
        // Encabezados
        fputcsv($output, [
            __('ID', 'business-smart-card-agent'),
            __('Publicación', 'business-smart-card-agent'),
            __('N° Factura', 'business-smart-card-agent'),
            __('Tipo', 'business-smart-card-agent'),
            __('Fecha Activación', 'business-smart-card-agent'),
            __('Duración', 'business-smart-card-agent'),
            __('Fecha Fin', 'business-smart-card-agent'),
            __('Estado', 'business-smart-card-agent'),
            __('Configuración Voz', 'business-smart-card-agent')
        ]);
        
        // Datos
        foreach ($report_data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }

    /**
     * AJAX: Cambia estado de publicación
     */
    public function toggle_publication_status() {
        check_ajax_referer('avatar_parlante_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para esta acción', 'business-smart-card-agent'));
        }

        $publication_id = absint($_POST['publication_id']);
        $start_date = sanitize_text_field($_POST['start_date']);
        $duration = absint($_POST['duration']);
        
        update_post_meta($publication_id, '_publication_start_date', $start_date);
        update_post_meta($publication_id, '_publication_duration', $duration);
        
        $is_active = $this->is_publication_active(get_post($publication_id));
        
        wp_send_json_success([
            'status' => $is_active ? __('Activo', 'business-smart-card-agent') : __('Inactivo', 'business-smart-card-agent'),
            'status_class' => $is_active ? 'active' : 'inactive',
            'expiry_date' => $this->calculate_expiry_date(get_post($publication_id))
        ]);
    }

    /**
     * AJAX: Renueva una publicación
     */
    public function renew_publication() {
        check_ajax_referer('avatar_parlante_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para esta acción', 'business-smart-card-agent'));
        }

        $publication_id = absint($_POST['publication_id']);
        $invoice = sanitize_text_field($_POST['invoice']);
        $duration = absint($_POST['duration']);
        
        // Establecer nueva fecha de inicio (hoy)
        $start_date = date('Y-m-d');
        
        update_post_meta($publication_id, '_publication_invoice', $invoice);
        update_post_meta($publication_id, '_publication_start_date', $start_date);
        update_post_meta($publication_id, '_publication_duration', $duration);
        
        $is_active = $this->is_publication_active(get_post($publication_id));
        $expiry_date = $this->calculate_expiry_date(get_post($publication_id));
        
        wp_send_json_success([
            'start_date' => $start_date,
            'expiry_date' => $expiry_date,
            'status' => $is_active ? __('Activo', 'business-smart-card-agent') : __('Inactivo', 'business-smart-card-agent'),
            'status_class' => $is_active ? 'active' : 'inactive'
        ]);
    }

    /**
     * Renderiza la tabla de reportes
     */
    private function render_report_table($data) {
        if (empty($data)) {
            return '<tr><td colspan="8" class="no-results">' . __('No se encontraron resultados', 'business-smart-card-agent') . '</td></tr>';
        }
        
        $html = '';
        foreach ($data as $row) {
            $html .= '<tr>';
            $html .= '<td>' . esc_html($row['ID']) . '</td>';
            $html .= '<td>' . esc_html($row['title']) . '</td>';
            $html .= '<td>' . esc_html($row['invoice']) . '</td>';
            $html .= '<td>' . esc_html($row['type']) . '</td>';
            $html .= '<td>' . esc_html($row['start_date']) . '</td>';
            $html .= '<td>' . esc_html($row['duration']) . '</td>';
            $html .= '<td>' . esc_html($row['expiry_date']) . '</td>';
            $html .= '<td>' . esc_html($row['voice_config']) . '</td>';
            $html .= '</tr>';
        }
        
        return $html;
    }

    /**
     * Sanitiza los ajustes antes de guardar
     */
    public function sanitize_settings($input) {
        $output = [];
        
        if (isset($input['enable_auto_reports'])) {
            $output['enable_auto_reports'] = (bool) $input['enable_auto_reports'];
        }
        
        if (isset($input['auto_report_frequency'])) {
            $output['auto_report_frequency'] = sanitize_text_field($input['auto_report_frequency']);
        }
        
        if (isset($input['auto_report_recipients'])) {
            $output['auto_report_recipients'] = sanitize_textarea_field($input['auto_report_recipients']);
        }
        
        return $output;
    }

    /**
     * Carga los assets necesarios
     */
    public function enqueue_assets($hook) {
        if ($hook !== 'business-smart-card-agent_page_avatar_parlante_publications') {
            return;
        }

        wp_enqueue_style(
            'business-smart-card-agent-publications',
            plugins_url('assets/css/publications.css', dirname(__FILE__)),
            [],
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/css/publications.css')
        );

        wp_enqueue_script(
            'business-smart-card-agent-publications',
            plugins_url('assets/js/publications.js', dirname(__FILE__)),
            ['jquery', 'jquery-ui-datepicker'],
            filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/js/publications.js'),
            true
        );

        wp_localize_script(
            'business-smart-card-agent-publications',
            'publicationsData',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('avatar_parlante_nonce'),
                'i18n' => [
                    'saving' => __('Guardando...', 'business-smart-card-agent'),
                    'renewing' => __('Renovando...', 'business-smart-card-agent'),
                    'testing_voice' => __('Probando voz...', 'business-smart-card-agent'),
                    'saving_voice' => __('Guardando configuración de voz...', 'business-smart-card-agent'),
                    'generating_report' => __('Generando reporte...', 'business-smart-card-agent'),
                    'exporting_report' => __('Exportando reporte...', 'business-smart-card-agent')
                ]
            ]
        );

        // Estilos para el datepicker
        wp_enqueue_style('jquery-ui', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
    }
}

new Reports_Manager();