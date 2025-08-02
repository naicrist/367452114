<?php
/**
 * Gestiona la programación de activación del avatar
 */
class Schedule_Manager {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_get_schedule_data', array($this, 'get_schedule_data_ajax'));
        add_action('wp_ajax_save_schedule', array($this, 'save_schedule_ajax'));
        add_action('avatar_parlante_daily_check', array($this, 'check_scheduled_items'));
    }

    /**
     * Añadir menú de administración
     */
    public function add_admin_menu() {
        add_submenu_page(
            'avatar_parlante',
            __('Programación del Avatar', 'avatar-parlante'),
            __('Programación', 'avatar-parlante'),
            'manage_options',
            'avatar_parlante_schedule',
            array($this, 'render_schedule_page')
        );
    }

    /**
     * Renderizar página de programación
     */
    public function render_schedule_page() {
        $post_types = get_post_types(array('public' => true), 'objects');
        ?>
        <div class="wrap">
            <h1><?php _e('Programación del Avatar', 'avatar-parlante'); ?></h1>
            
            <div class="avatar-form-section">
                <h2><?php _e('Programar Activación', 'avatar-parlante'); ?></h2>
                
                <div class="schedule-controls">
                    <div class="schedule-field">
                        <label for="schedule-post-type">
                            <?php _e('Tipo de Contenido', 'avatar-parlante'); ?>
                        </label>
                        <select id="schedule-post-type" class="regular-text">
                            <?php foreach ($post_types as $type) : ?>
                                <option value="<?php echo esc_attr($type->name); ?>">
                                    <?php echo esc_html($type->labels->singular_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="schedule-field">
                        <label for="schedule-post-id">
                            <?php _e('Contenido', 'avatar-parlante'); ?>
                        </label>
                        <select id="schedule-post-id" class="regular-text" disabled>
                            <option value=""><?php _e('Selecciona un tipo primero', 'avatar-parlante'); ?></option>
                        </select>
                    </div>
                </div>
                
                <div class="schedule-controls">
                    <div class="schedule-field">
                        <label for="schedule-start-date">
                            <?php _e('Fecha de Inicio', 'avatar-parlante'); ?>
                        </label>
                        <input type="text" id="schedule-start-date" class="datepicker regular-text" 
                               placeholder="<?php _e('Selecciona una fecha', 'avatar-parlante'); ?>">
                    </div>
                    
                    <div class="schedule-field">
                        <label for="schedule-end-date">
                            <?php _e('Fecha de Finalización', 'avatar-parlante'); ?>
                        </label>
                        <input type="text" id="schedule-end-date" class="datepicker regular-text" 
                               placeholder="<?php _e('Opcional', 'avatar-parlante'); ?>">
                    </div>
                </div>
                
                <div class="schedule-controls">
                    <label>
                        <input type="checkbox" id="schedule-is-active" checked>
                        <?php _e('Activar programación', 'avatar-parlante'); ?>
                    </label>
                </div>
                
                <button id="save-schedule" class="button button-primary">
                    <?php _e('Guardar Programación', 'avatar-parlante'); ?>
                </button>
            </div>
            
            <div class="avatar-form-section">
                <h2><?php _e('Programaciones Activas', 'avatar-parlante'); ?></h2>
                <div id="schedule-list-container">
                    <?php $this->render_schedule_list(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Obtener datos de programación via AJAX
     */
    public function get_schedule_data_ajax() {
        check_ajax_referer('avatar_parlante_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para realizar esta acción', 'avatar-parlante'));
        }

        $post_type = sanitize_text_field($_POST['post_type']);
        $posts = get_posts(array(
            'post_type' => $post_type,
            'posts_per_page' => -1,
            'fields' => 'id=>title'
        ));

        wp_send_json_success(array(
            'posts' => $posts
        ));
    }

    /**
     * Guardar programación via AJAX
     */
    public function save_schedule_ajax() {
        check_ajax_referer('avatar_parlante_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para realizar esta acción', 'avatar-parlante'));
        }

        $post_id = intval($_POST['post_id']);
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $schedule = array(
            'start_date' => $start_date,
            'end_date' => $end_date,
            'is_active' => $is_active
        );

        update_post_meta($post_id, '_avatar_parlante_schedule', $schedule);

        wp_send_json_success(array(
            'message' => __('Programación guardada correctamente', 'avatar-parlante'),
            'schedule_list' => $this->render_schedule_list()
        ));
    }

    /**
     * Renderizar lista de programaciones
     */
    private function render_schedule_list() {
        $scheduled_posts = $this->get_scheduled_posts();
        
        if (empty($scheduled_posts)) {
            echo '<p>' . __('No hay programaciones activas.', 'avatar-parlante') . '</p>';
            return;
        }
        
        echo '<ul class="schedule-list">';
        foreach ($scheduled_posts as $post) {
            $schedule = get_post_meta($post->ID, '_avatar_parlante_schedule', true);
            echo '<li>';
            echo '<strong>' . esc_html($post->post_title) . '</strong> (' . esc_html($post->post_type) . ')';
            echo '<br>' . __('Inicio:', 'avatar-parlante') . ' ' . esc_html($schedule['start_date']);
            if ($schedule['end_date']) {
                echo '<br>' . __('Fin:', 'avatar-parlante') . ' ' . esc_html($schedule['end_date']);
            }
            echo '</li>';
        }
        echo '</ul>';
    }

    /**
     * Verificar programaciones diariamente
     */
    public function check_scheduled_items() {
        $scheduled_posts = $this->get_scheduled_posts();
        
        foreach ($scheduled_posts as $post) {
            $schedule = get_post_meta($post->ID, '_avatar_parlante_schedule', true);
            $current_time = current_time('timestamp');
            $end_time = strtotime($schedule['end_date']);
            
            if ($schedule['end_date'] && $current_time > $end_time) {
                $schedule['is_active'] = 0;
                update_post_meta($post->ID, '_avatar_parlante_schedule', $schedule);
            }
        }
    }

    /**
     * Verificar si el avatar está activo para un post
     */
    public static function is_active_for_post($post_id) {
        $schedule = get_post_meta($post_id, '_avatar_parlante_schedule', true);
        
        if (!$schedule || !$schedule['is_active']) {
            return false;
        }
        
        $current_time = current_time('timestamp');
        $start_time = strtotime($schedule['start_date']);
        $end_time = $schedule['end_date'] ? strtotime($schedule['end_date']) : null;
        
        if ($start_time && $current_time < $start_time) {
            return false;
        }
        
        if ($end_time && $current_time > $end_time) {
            return false;
        }
        
        return true;
    }

    /**
     * Obtener posts programados
     */
    private function get_scheduled_posts() {
        $args = array(
            'post_type' => get_post_types(array('public' => true)),
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_avatar_parlante_schedule',
                    'compare' => 'EXISTS'
                )
            )
        );
        
        return get_posts($args);
    }
}