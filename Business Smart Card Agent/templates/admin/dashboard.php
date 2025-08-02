<?php
/**
 * Dashboard principal del plugin Business Smart Card Agent
 */
class Dashboard {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_save_dashboard_settings', array($this, 'save_settings_ajax'));
    }

    /**
     * Añadir menú de administración
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Agente Empresarial', 'Business Smart Card Agent'),
            __('Agente Empresarial', 'Business Smart Card Agent'),
            'manage_options',
            'inteligent_card_dashboard',
            array($this, 'render_dashboard'),
            'dashicons-businessperson',
            6
        );
    }

    /**
     * Renderizar el dashboard
     */
    public function render_dashboard() {
        $voice_settings = get_option('avatar_parlante_voice_settings');
        $portfolio_posts = $this->get_portfolio_posts();
        ?>
        <div class="wrap Business Smart Card Agent-dashboard">
            <!-- Header -->
            <div class="dashboard-header">
                <h1><?php _e('Panel de Control Agente Empresarial', 'Business Smart Card Agent'); ?></h1>
                <div class="voice-status">
                    <span class="status-indicator active"></span>
                    <span><?php _e('Sistema Operativo', 'Business Smart Card Agent'); ?></span>
                </div>
            </div>

            <!-- Sección de Configuración de Voz -->
            <div class="dashboard-card">
                <h2><?php _e('Configuración de Voz', 'Business Smart Card Agent'); ?></h2>
                <div class="voice-controls">
                    <div class="control-group">
                        <label><?php _e('Idioma:', 'Business Smart Card Agent'); ?></label>
                        <select id="voice-lang" class="voice-select">
                            <option value="es-ES" <?php selected($voice_settings['voice_lang'], 'es-ES'); ?>>Español</option>
                            <option value="en-US" <?php selected($voice_settings['voice_lang'], 'en-US'); ?>>Inglés</option>
                        </select>
                    </div>
                    <div class="control-group">
                        <label><?php _e('Tipo:', 'Business Smart Card Agent'); ?></label>
                        <select id="voice-type" class="voice-select">
                            <option value="female" <?php selected($voice_settings['voice_type'], 'female'); ?>>Femenina</option>
                            <option value="male" <?php selected($voice_settings['voice_type'], 'male'); ?>>Masculina</option>
                        </select>
                    </div>
                    <div class="control-group range-control">
                        <label><?php _e('Velocidad:', 'Business Smart Card Agent'); ?> <span id="speed-value"><?php echo $voice_settings['voice_speed']; ?></span></label>
                        <input type="range" id="voice-speed" min="0.5" max="2" step="0.1" value="<?php echo $voice_settings['voice_speed']; ?>">
                    </div>
                    <button id="test-voice-btn" class="button button-primary"><?php _e('Probar Voz', 'Business Smart Card Agent'); ?></button>
                </div>
            </div>

            <!-- Gestión de Publicaciones -->
            <div class="dashboard-card">
                <h2><?php _e('Publicaciones del Portafolio', 'Business Smart Card Agent'); ?></h2>
                <div class="portfolio-management">
                    <div class="post-selector">
                        <label><?php _e('Seleccionar Publicación:', 'Business Smart Card Agent'); ?></label>
                        <select id="portfolio-post" class="widefat">
                            <?php foreach ($portfolio_posts as $post) : ?>
                                <option value="<?php echo $post->ID; ?>"><?php echo $post->post_title; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="post-fields">
                        <div class="field-group">
                            <label><?php _e('Número de Factura:', 'Business Smart Card Agent'); ?></label>
                            <input type="text" id="invoice-number" class="regular-text">
                        </div>

                        <div class="field-group range-control">
                            <label><?php _e('Duración:', 'Business Smart Card Agent'); ?> <span id="duration-value">1</span> año(s)</label>
                            <input type="range" id="duration-years" min="1" max="10" value="1">
                        </div>

                        <div class="field-group">
                            <label><?php _e('Fecha Inicio:', 'Business Smart Card Agent'); ?></label>
                            <input type="date" id="start-date" class="regular-text">
                        </div>

                        <div class="field-group status-display">
                            <label><?php _e('Estado:', 'Business Smart Card Agent'); ?></label>
                            <span id="status-badge" class="badge inactive">✖ Inactivo</span>
                        </div>

                        <div class="action-buttons">
                            <button id="renew-btn" class="button"><?php _e('Renovar', 'Business Smart Card Agent'); ?></button>
                            <button id="save-settings" class="button button-primary"><?php _e('Guardar', 'Business Smart Card Agent'); ?></button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reportes Rápidos -->
            <div class="dashboard-card">
                <h2><?php _e('Reportes', 'Business Smart Card Agent'); ?></h2>
                <div class="quick-stats">
                    <div class="stat-card">
                        <h3><?php _e('Publicaciones Activas', 'Business Smart Card Agent'); ?></h3>
                        <div class="stat-value" id="active-posts">0</div>
                    </div>
                    <div class="stat-card">
                        <h3><?php _e('Por Vencer (7 días)', 'Business Smart Card Agent'); ?></h3>
                        <div class="stat-value" id="expiring-posts">0</div>
                    </div>
                    <div class="stat-card">
                        <h3><?php _e('Interacciones Hoy', 'Business Smart Card Agent'); ?></h3>
                        <div class="stat-value" id="today-interactions">0</div>
                    </div>
                </div>
                <button id="generate-full-report" class="button"><?php _e('Generar Reporte Completo', 'Business Smart Card Agent'); ?></button>
            </div>
        </div>

        <!-- JavaScript para funcionalidad -->
        <script>
        jQuery(document).ready(function($) {
            // Control deslizante de velocidad de voz
            $('#voice-speed').on('input', function() {
                $('#speed-value').text($(this).val());
            });

            // Control deslizante de duración
            $('#duration-years').on('input', function() {
                $('#duration-value').text($(this).val());
                update_status_badge();
            });

            // Actualizar estado al cambiar fecha
            $('#start-date').on('change', function() {
                update_status_badge();
            });

            function update_status_badge() {
                const startDate = new Date($('#start-date').val());
                const years = parseInt($('#duration-years').val());
                
                if (isNaN(startDate.getTime())) return;
                
                const endDate = new Date(startDate);
                endDate.setFullYear(startDate.getFullYear() + years);
                const today = new Date();
                
                const isActive = today <= endDate;
                $('#status-badge')
                    .toggleClass('active', isActive)
                    .toggleClass('inactive', !isActive)
                    .text(isActive ? '✔ Activo' : '✖ Inactivo');
            }

            // Cargar datos de la publicación seleccionada
            $('#portfolio-post').on('change', function() {
                const postId = $(this).val();
                // AJAX para cargar los datos guardados
                $.post(ajaxurl, {
                    action: 'load_portfolio_data',
                    post_id: postId,
                    nonce: '<?php echo wp_create_nonce('inteligent_card_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        const data = response.data;
                        $('#invoice-number').val(data.invoice_number);
                        $('#duration-years').val(data.duration).trigger('input');
                        $('#start-date').val(data.start_date).trigger('change');
                    }
                });
            });

            // Guardar configuración
            $('#save-settings').on('click', function() {
                const data = {
                    post_id: $('#portfolio-post').val(),
                    invoice_number: $('#invoice-number').val(),
                    duration: $('#duration-years').val(),
                    start_date: $('#start-date').val(),
                    nonce: '<?php echo wp_create_nonce('inteligent_card_nonce'); ?>'
                };
                
                $.post(ajaxurl, {
                    action: 'save_dashboard_settings',
                    data: data
                }, function(response) {
                    alert(response.data.message);
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Obtener publicaciones del portafolio
     */
    private function get_portfolio_posts() {
        return get_posts([
            'post_type' => 'portfolio',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
    }

    /**
     * Guardar configuración via AJAX
     */
    public function save_settings_ajax() {
        check_ajax_referer('inteligent_card_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para esta acción', 'Business Smart Card Agent'));
        }

        $settings = [
            'post_id' => intval($_POST['post_id']),
            'invoice_number' => sanitize_text_field($_POST['invoice_number']),
            'duration' => intval($_POST['duration']),
            'start_date' => sanitize_text_field($_POST['start_date'])
        ];

        update_post_meta($settings['post_id'], '_inteligent_card_settings', $settings);

        wp_send_json_success([
            'message' => __('Configuración guardada correctamente', 'Business Smart Card Agent')
        ]);
    }
}

new Dashboard();