<?php
// Añadir menú en el panel de WordPress
add_action('admin_menu', 'avatar_parlante_menu_admin');

function avatar_parlante_menu_admin() {
    add_menu_page(
        'Business Smart Card Agent',
        'Business Smart Card Agent',
        'manage_options',
        'business-smart-card-agent',
        'avatar_parlante_pagina_admin',
        'dashicons-admin-comments'
    );
}

// Página de configuración
function avatar_parlante_pagina_admin() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Guardar configuración
    if (isset($_POST['guardar_config'])) {
        update_option('avatar_auto_lectura', sanitize_text_field($_POST['auto_lectura']));
        update_option('avatar_tema', sanitize_text_field($_POST['tema']));
        echo '<div class="notice notice-success"><p>Configuración guardada!</p></div>';
    }

    // Obtener valores guardados
    $autoLectura = get_option('avatar_auto_lectura', 'no');
    $tema = get_option('avatar_tema', 'claro');
    ?>
    <div class="wrap">
        <h1>Configuración del Business Smart Card Agent</h1>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th scope="row">Auto-Lectura</th>
                    <td>
                        <label>
                            <input type="radio" name="auto_lectura" value="si" <?php checked($autoLectura, 'si'); ?>>
                            Sí (el avatar leerá automáticamente)
                        </label><br>
                        <label>
                            <input type="radio" name="auto_lectura" value="no" <?php checked($autoLectura, 'no'); ?>>
                            No (activar manualmente)
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Tema del Avatar</th>
                    <td>
                        <select name="tema">
                            <option value="claro" <?php selected($tema, 'claro'); ?>>Claro</option>
                            <option value="oscuro" <?php selected($tema, 'oscuro'); ?>>Oscuro</option>
                        </select>
                    </td>
                </tr>
            </table>
            <?php submit_button('Guardar Cambios', 'primary', 'guardar_config'); ?>
        </form>
    </div>
    <?php
}