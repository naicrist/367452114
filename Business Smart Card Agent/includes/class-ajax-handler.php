<?php
/**
 * Maneja todas las peticiones AJAX del plugin
 */
class Ajax_Handler {

    public function __construct() {
        // Configuración
        add_action('wp_ajax_avatar_parlante_save_settings', array($this, 'save_settings'));
        add_action('wp_ajax_avatar_parlante_test_voice', array($this, 'test_voice'));
        
        // Programación
        add_action('wp_ajax_avatar_parlante_get_posts', array($this, 'get_posts_for_scheduling'));
        add_action('wp_ajax_avatar_parlante_save_schedule', array($this, 'save_schedule'));
        
        // Reportes
        add_action('wp_ajax_avatar_parlante_generate_report', array($this, 'generate_report'));
        add_action('wp_ajax_avatar_parlante_get_chart_data', array($this, 'get_chart_data'));
        
        // Frontend
        add_action('wp_ajax_avatar_parlante_answer', array($this, 'answer_question'));
        add_action('wp_ajax_nopriv_avatar_parlante_answer', array($this, 'answer_question'));
        add_action('wp_ajax_avatar_parlante_log_interaction', array($this, 'log_interaction'));
    }

    /**
     * Guardar configuración general
     */
    public function save_settings() {
        check_ajax_referer('avatar_parlante_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para realizar esta acción', 'avatar-parlante'));
        }

        $settings = array(
            'default_avatar' => sanitize_text_field($_POST['default_avatar']),
            'voice_speed' => floatval($_POST['voice_speed']),
            'voice_pitch' => floatval($_POST['voice_pitch']),
            'voice_lang' => sanitize_text_field($_POST['voice_lang']),
            'autoplay' => isset($_POST['autoplay']) ? true : false,
            'enable_reporting' => isset($_POST['enable_reporting']) ? true : false
        );

        update_option('avatar_parlante_settings', $settings);

        wp_send_json_success(__('Configuración guardada correctamente', 'avatar-parlante'));
    }

    /**
     * Probar configuración de voz
     */
    public function test_voice() {
        check_ajax_referer('avatar_parlante_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para realizar esta acción', 'avatar-parlante'));
        }

        $text = sanitize_text_field($_POST['text']);
        $settings = get_option('avatar_parlante_voice_settings', array());

        wp_send_json_success(array(
            'text' => $text,
            'settings' => $settings
        ));
    }

    /**
     * Obtener posts para programación
     */
    public function get_posts_for_scheduling() {
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
     * Guardar programación
     */
    public function save_schedule() {
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

        wp_send_json_success(__('Programación guardada correctamente', 'avatar-parlante'));
    }

    /**
     * Generar reporte
     */
    public function generate_report() {
        check_ajax_referer('avatar_parlante_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para realizar esta acción', 'avatar-parlante'));
        }

        $filters = array(
            'post_id' => isset($_GET['post_id']) ? intval($_GET['post_id']) : null,
            'start_date' => isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : null,
            'end_date' => isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : null,
            'interaction_type' => isset($_GET['interaction_type']) ? sanitize_text_field($_GET['interaction_type']) : null
        );

        $reports = new Reports();
        $results = $reports->get_interactions($filters);
        $reports->generate_csv($results);
        exit;
    }

    /**
     * Obtener datos para gráfico
     */
    public function get_chart_data() {
        check_ajax_referer('avatar_parlante_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('No tienes permisos para realizar esta acción', 'avatar-parlante'));
        }

        $filters = array(
            'start_date' => isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : null,
            'end_date' => isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : null
        );

        $reports = new Reports();
        $results = $reports->get_interactions($filters);
        $chart_data = $reports->prepare_chart_data($results);

        wp_send_json_success($chart_data);
    }

    /**
     * Responder a una pregunta del usuario
     */
    public function answer_question() {
        check_ajax_referer('avatar_parlante_nonce', 'nonce');

        $question = sanitize_text_field($_POST['question']);
        $post_id = intval($_POST['post_id']);

        // Buscar respuesta en el contenido
        $answer = $this->find_answer_in_content($question, $post_id);

        if (!$answer) {
            // Respuesta por defecto si no se encuentra
            $fallback_responses = array(
                __('No tengo información sobre eso en este momento.', 'avatar-parlante'),
                __('Puedo buscar más información si lo deseas.', 'avatar-parlante'),
                __('¿Podrías reformular tu pregunta?', 'avatar-parlante')
            );
            
            $answer = $fallback_responses[array_rand($fallback_responses)];
            $interaction_type = 'fallback_response';
        } else {
            $interaction_type = 'question_answered';
        }

        // Registrar interacción
        Reports::log_interaction($post_id, $interaction_type, array(
            'question' => $question,
            'answer' => $answer
        ));

        wp_send_json_success(array(
            'answer' => $answer
        ));
    }

    /**
     * Buscar respuesta en el contenido
     */
    private function find_answer_in_content($question, $post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }

        // Implementación básica de búsqueda
        $content = strip_tags($post->post_content);
        $keywords = explode(' ', strtolower($question));
        $matches = 0;

        foreach ($keywords as $keyword) {
            if (strlen($keyword) > 3 && strpos(strtolower($content), $keyword) !== false) {
                $matches++;
            }
        }

        // Si hay suficientes coincidencias, devolver un extracto
        if ($matches >= count($keywords) / 2) {
            return wp_trim_words($content, 30);
        }

        return false;
    }

    /**
     * Registrar interacción
     */
    public function log_interaction() {
        check_ajax_referer('avatar_parlante_nonce', 'nonce');

        $post_id = intval($_POST['post_id']);
        $interaction_type = sanitize_text_field($_POST['interaction_type']);
        $interaction_data = isset($_POST['interaction_data']) ? $_POST['interaction_data'] : array();

        $interaction_id = Reports::log_interaction($post_id, $interaction_type, $interaction_data);

        wp_send_json_success(array(
            'interaction_id' => $interaction_id
        ));
    }
}