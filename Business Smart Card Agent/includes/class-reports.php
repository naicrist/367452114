<?php
/**
 * Gestiona los reportes y estadísticas del avatar
 */
class Reports {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_generate_report', array($this, 'generate_report_ajax'));
        add_action('avatar_parlante_daily_report', array($this, 'generate_daily_report'));
    }

    /**
     * Añadir menú de administración
     */
    public function add_admin_menu() {
        add_submenu_page(
            'avatar_parlante',
            __('Reportes del Avatar', 'avatar-parlante'),
            __('Reportes', 'avatar-parlante'),
            'manage_options',
            'avatar_parlante_reports',
            array($this, 'render_reports_page')
        );
    }

    /**
     * Renderizar página de reportes
     */
    public function render_reports_page() {
        $post_types = get_post_types(array('public' => true), 'objects');
        ?>
        <div class="wrap">
            <h1><?php _e('Reportes del Avatar', 'avatar-parlante'); ?></h1>
            
            <div class="avatar-form-section">
                <h2><?php _e('Generar Reporte', 'avatar-parlante'); ?></h2>
                
                <div class="report-filters">
                    <div class="filter-row">
                        <div class="filter-field">
                            <label for="report-post-type">
                                <?php _e('Tipo de Contenido', 'avatar-parlante'); ?>
                            </label>
                            <select id="report-post-type" class="regular-text">
                                <option value=""><?php _e('Todos los tipos', 'avatar-parlante'); ?></option>
                                <?php foreach ($post_types as $type) : ?>
                                    <option value="<?php echo esc_attr($type->name); ?>">
                                        <?php echo esc_html($type->labels->singular_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-field">
                            <label for="report-post-id">
                                <?php _e('Contenido Específico', 'avatar-parlante'); ?>
                            </label>
                            <select id="report-post-id" class="regular-text" disabled>
                                <option value=""><?php _e('Selecciona un tipo primero', 'avatar-parlante'); ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filter-row">
                        <div class="filter-field">
                            <label for="report-start-date">
                                <?php _e('Fecha de Inicio', 'avatar-parlante'); ?>
                            </label>
                            <input type="text" id="report-start-date" class="datepicker regular-text">
                        </div>
                        
                        <div class="filter-field">
                            <label for="report-end-date">
                                <?php _e('Fecha de Finalización', 'avatar-parlante'); ?>
                            </label>
                            <input type="text" id="report-end-date" class="datepicker regular-text">
                        </div>
                    </div>
                    
                    <div class="filter-row">
                        <div class="filter-field">
                            <label for="report-interaction-type">
                                <?php _e('Tipo de Interacción', 'avatar-parlante'); ?>
                            </label>
                            <select id="report-interaction-type" class="regular-text">
                                <option value=""><?php _e('Todas las interacciones', 'avatar-parlante'); ?></option>
                                <option value="read_content"><?php _e('Lectura de contenido', 'avatar-parlante'); ?></option>
                                <option value="question_answered"><?php _e('Preguntas respondidas', 'avatar-parlante'); ?></option>
                                <option value="fallback_response"><?php _e('Respuestas por defecto', 'avatar-parlante'); ?></option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button id="generate-report" class="button button-primary">
                        <?php _e('Generar Reporte', 'avatar-parlante'); ?>
                    </button>
                    
                    <button id="toggle-chart" class="button">
                        <?php _e('Ver Gráfico', 'avatar-parlante'); ?>
                    </button>
                </div>
            </div>
            
            <div class="avatar-form-section">
                <h2><?php _e('Estadísticas', 'avatar-parlante'); ?></h2>
                <div id="report-chart-container" style="display: none;">
                    <canvas id="report-chart" width="400" height="200"></canvas>
                </div>
                
                <div id="report-stats-container">
                    <?php $this->render_stats_summary(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Generar reporte via AJAX
     */
    public function generate_report_ajax() {
        check_ajax_referer('avatar_parlante_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para realizar esta acción', 'avatar-parlante'));
        }

        $filters = array(
            'post_id' => isset($_POST['post_id']) ? intval($_POST['post_id']) : null,
            'start_date' => isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : null,
            'end_date' => isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : null,
            'interaction_type' => isset($_POST['interaction_type']) ? sanitize_text_field($_POST['interaction_type']) : null
        );

        $results = $this->get_interactions($filters);

        if (isset($_POST['format']) && $_POST['format'] === 'csv') {
            $this->generate_csv($results);
            exit;
        }

        wp_send_json_success(array(
            'html' => $this->render_results_table($results),
            'chart_data' => $this->prepare_chart_data($results)
        ));
    }

    /**
     * Generar reporte diario automático
     */
    public function generate_daily_report() {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $results = $this->get_interactions(array(
            'start_date' => $yesterday . ' 00:00:00',
            'end_date' => $yesterday . ' 23:59:59'
        ));

        if (empty($results)) {
            return;
        }

        $to = get_option('admin_email');
        $subject = sprintf(__('Reporte Diario del Avatar Parlante - %s', 'avatar-parlante'), $yesterday);
        
        ob_start();
        include AVATAR_PARLANTE_PLUGIN_DIR . 'templates/admin/email-report.php';
        $message = ob_get_clean();

        wp_mail($to, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));
    }

    /**
     * Renderizar resumen de estadísticas
     */
    private function render_stats_summary() {
        $stats = $this->get_stats_summary();
        ?>
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php _e('Interacciones Totales', 'avatar-parlante'); ?></h3>
                <div class="stat-value"><?php echo esc_html($stats['total_interactions']); ?></div>
            </div>
            
            <div class="stat-card">
                <h3><?php _e('Lecturas de Contenido', 'avatar-parlante'); ?></h3>
                <div class="stat-value"><?php echo esc_html($stats['read_content']); ?></div>
            </div>
            
            <div class="stat-card">
                <h3><?php _e('Preguntas Respondidas', 'avatar-parlante'); ?></h3>
                <div class="stat-value"><?php echo esc_html($stats['questions_answered']); ?></div>
            </div>
            
            <div class="stat-card">
                <h3><?php _e('Contenido Más Popular', 'avatar-parlante'); ?></h3>
                <div class="stat-value">
                    <?php echo $stats['most_popular'] ? esc_html($stats['most_popular']) : __('N/A', 'avatar-parlante'); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Obtener interacciones de la base de datos
     */
    private function get_interactions($filters = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'avatar_parlante_interactions';
        
        $where = array();
        $query_params = array();
        
        if (!empty($filters['post_id'])) {
            $where[] = 'post_id = %d';
            $query_params[] = $filters['post_id'];
        }
        
        if (!empty($filters['start_date'])) {
            $where[] = 'created_at >= %s';
            $query_params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $where[] = 'created_at <= %s';
            $query_params[] = $filters['end_date'];
        }
        
        if (!empty($filters['interaction_type'])) {
            $where[] = 'interaction_type = %s';
            $query_params[] = $filters['interaction_type'];
        }
        
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $query = "SELECT * FROM $table_name $where_clause ORDER BY created_at DESC";
        
        if (!empty($query_params)) {
            $query = $wpdb->prepare($query, $query_params);
        }
        
        return $wpdb->get_results($query);
    }

    /**
     * Generar CSV con los resultados
     */
    private function generate_csv($results) {
        $filename = 'reporte-avatar-parlante-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Encabezados
        fputcsv($output, array(
            'ID',
            'ID del Post',
            'Título del Post',
            'Tipo de Interacción',
            'Datos',
            'IP',
            'Agente de Usuario',
            'Fecha'
        ));
        
        // Datos
        foreach ($results as $row) {
            $post_title = get_the_title($row->post_id);
            $interaction_data = maybe_unserialize($row->interaction_data);
            
            fputcsv($output, array(
                $row->id,
                $row->post_id,
                $post_title,
                $row->interaction_type,
                is_array($interaction_data) ? json_encode($interaction_data) : $interaction_data,
                $row->user_ip,
                $row->user_agent,
                $row->created_at
            ));
        }
        
        fclose($output);
    }

    /**
     * Renderizar tabla de resultados
     */
    private function render_results_table($results) {
        if (empty($results)) {
            return '<p>' . __('No se encontraron interacciones con los filtros seleccionados.', 'avatar-parlante') . '</p>';
        }
        
        ob_start();
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Post', 'avatar-parlante'); ?></th>
                    <th><?php _e('Tipo', 'avatar-parlante'); ?></th>
                    <th><?php _e('Datos', 'avatar-parlante'); ?></th>
                    <th><?php _e('Fecha', 'avatar-parlante'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $row) : ?>
                    <tr>
                        <td>
                            <a href="<?php echo get_edit_post_link($row->post_id); ?>" target="_blank">
                                <?php echo esc_html(get_the_title($row->post_id)); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html($this->get_interaction_type_label($row->interaction_type)); ?></td>
                        <td>
                            <?php 
                            $data = maybe_unserialize($row->interaction_data);
                            if (is_array($data)) {
                                echo '<pre>' . esc_html(print_r($data, true)) . '</pre>';
                            } else {
                                echo esc_html($data);
                            }
                            ?>
                        </td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($row->created_at))); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }

    /**
     * Obtener estadísticas resumidas
     */
    private function get_stats_summary() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'avatar_parlante_interactions';
        
        $stats = array(
            'total_interactions' => 0,
            'read_content' => 0,
            'questions_answered' => 0,
            'most_popular' => ''
        );
        
        // Total de interacciones
        $stats['total_interactions'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_name"
        );
        
        // Lecturas de contenido
        $stats['read_content'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE interaction_type = %s",
                'read_content'
            )
        );
        
        // Preguntas respondidas
        $stats['questions_answered'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE interaction_type = %s",
                'question_answered'
            )
        );
        
        // Contenido más popular
        $stats['most_popular'] = $wpdb->get_var(
            "SELECT p.post_title FROM $table_name i 
             JOIN {$wpdb->posts} p ON i.post_id = p.ID 
             GROUP BY i.post_id 
             ORDER BY COUNT(*) DESC 
             LIMIT 1"
        );
        
        return $stats;
    }

    /**
     * Preparar datos para el gráfico
     */
    private function prepare_chart_data($results) {
        $data = array();
        
        if (empty($results)) {
            return $data;
        }
        
        // Agrupar por día y tipo de interacción
        foreach ($results as $row) {
            $date = date('Y-m-d', strtotime($row->created_at));
            
            if (!isset($data[$date])) {
                $data[$date] = array(
                    'read_content' => 0,
                    'question_answered' => 0,
                    'fallback_response' => 0
                );
            }
            
            $data[$date][$row->interaction_type]++;
        }
        
        // Formatear para Chart.js
        $labels = array_keys($data);
        $datasets = array(
            array(
                'label' => __('Lecturas de Contenido', 'avatar-parlante'),
                'data' => array_column($data, 'read_content'),
                'backgroundColor' => '#0073aa'
            ),
            array(
                'label' => __('Preguntas Respondidas', 'avatar-parlante'),
                'data' => array_column($data, 'question_answered'),
                'backgroundColor' => '#46b450'
            ),
            array(
                'label' => __('Respuestas por Defecto', 'avatar-parlante'),
                'data' => array_column($data, 'fallback_response'),
                'backgroundColor' => '#ffb900'
            )
        );
        
        return array(
            'labels' => $labels,
            'datasets' => $datasets
        );
    }

    /**
     * Obtener etiqueta para tipo de interacción
     */
    private function get_interaction_type_label($type) {
        $labels = array(
            'read_content' => __('Lectura de contenido', 'avatar-parlante'),
            'question_answered' => __('Pregunta respondida', 'avatar-parlante'),
            'fallback_response' => __('Respuesta por defecto', 'avatar-parlante')
        );
        
        return isset($labels[$type]) ? $labels[$type] : $type;
    }

    /**
     * Registrar una interacción
     */
    public static function log_interaction($post_id, $interaction_type, $interaction_data = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'avatar_parlante_interactions';
        
        $wpdb->insert($table_name, array(
            'post_id' => $post_id,
            'interaction_type' => $interaction_type,
            'interaction_data' => maybe_serialize($interaction_data),
            'user_ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT']
        ));
        
        return $wpdb->insert_id;
    }

    /**
     * Crear tablas necesarias
     */
    public function create_tables() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'avatar_parlante_interactions';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            interaction_type varchar(50) NOT NULL,
            interaction_data text NOT NULL,
            user_ip varchar(45) NOT NULL,
            user_agent text NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY post_id (post_id),
            KEY interaction_type (interaction_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}