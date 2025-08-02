jQuery(document).ready(function($) {
    // Variables globales
    var currentSchedule = null;
    
    // Inicializar datepicker
    $('#start-date').datepicker({
        dateFormat: 'yy-mm-dd',
        onSelect: calculateEndDate
    });
    
    // Cargar publicaciones cuando se selecciona un tipo
    $('#schedule-post-type').change(function() {
        var postType = $(this).val();
        
        if (!postType) {
            $('#schedule-post-id').prop('disabled', true).html('<option value="">Seleccione un tipo primero</option>');
            return;
        }
        
        $('#schedule-post-id').prop('disabled', true).html('<option value="">Cargando...</option>');
        
        $.ajax({
            url: scheduleData.ajax_url,
            type: 'POST',
            data: {
                action: 'avatar_parlante_get_posts',
                post_type: postType,
                nonce: scheduleData.nonce
            },
            success: function(response) {
                if (response.success) {
                    var options = '<option value="">Seleccionar publicación</option>';
                    
                    $.each(response.data.posts, function(index, post) {
                        var disabled = post.has_schedule ? ' disabled' : '';
                        var notice = post.has_schedule ? ' (ya programada)' : '';
                        options += `<option value="${post.id}"${disabled}>${post.text}${notice}</option>`;
                    });
                    
                    $('#schedule-post-id').html(options).prop('disabled', false);
                } else {
                    alert(response.data);
                }
            },
            error: function() {
                alert(scheduleData.i18n.error_loading);
            }
        });
    });
    
    // Cuando se selecciona una publicación
    $('#schedule-post-id').change(function() {
        var postId = $(this).val();
        if (!postId) return;
        
        // Verificar si ya tiene programación
        $.ajax({
            url: scheduleData.ajax_url,
            type: 'POST',
            data: {
                action: 'avatar_parlante_get_schedule',
                post_id: postId,
                nonce: scheduleData.nonce
            },
            success: function(response) {
                if (response.success && response.data.schedule) {
                    currentSchedule = response.data.schedule;
                    loadScheduleData(currentSchedule);
                    $('#renew-schedule').prop('disabled', false);
                } else {
                    currentSchedule = null;
                    resetForm();
                    $('#renew-schedule').prop('disabled', true);
                }
                $('#save-schedule').prop('disabled', false);
            }
        });
    });
    
    // Calcular fecha final cuando cambia duración o fecha inicio
    $('#schedule-duration, #start-date').change(calculateEndDate);
    
    // Guardar programación
    $('#save-schedule').click(function() {
        if (!validateForm()) return;
        
        var action = currentSchedule ? 'avatar_parlante_update_schedule' : 'avatar_parlante_save_schedule';
        var button = $(this);
        var originalText = button.text();
        
        button.text(scheduleData.i18n.saving).prop('disabled', true);
        
        $.ajax({
            url: scheduleData.ajax_url,
            type: 'POST',
            data: {
                action: action,
                post_id: $('#schedule-post-id').val(),
                invoice: $('#invoice-number').val(),
                duration: $('#schedule-duration').val(),
                start_date: $('#start-date').val(),
                end_date: $('#end-date').val(),
                nonce: scheduleData.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    currentSchedule = response.data.schedule;
                    loadScheduleList();
                } else {
                    alert(response.data);
                }
                button.text(originalText).prop('disabled', false);
            },
            error: function() {
                alert(scheduleData.i18n.error_saving);
                button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Renovar programación
    $('#renew-schedule').click(function() {
        if (!confirm(scheduleData.i18n.confirm_renew) || !validateForm()) return;
        
        var button = $(this);
        var originalText = button.text();
        
        button.text(scheduleData.i18n.renewing).prop('disabled', true);
        
        $.ajax({
            url: scheduleData.ajax_url,
            type: 'POST',
            data: {
                action: 'avatar_parlante_renew_schedule',
                post_id: $('#schedule-post-id').val(),
                invoice: $('#invoice-number').val(),
                duration: $('#schedule-duration').val(),
                start_date: $('#start-date').val(),
                end_date: $('#end-date').val(),
                nonce: scheduleData.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    currentSchedule = response.data.schedule;
                    loadScheduleList();
                } else {
                    alert(response.data);
                }
                button.text(originalText).prop('disabled', false);
            },
            error: function() {
                alert(scheduleData.i18n.error_renewing);
                button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Cargar listado de programaciones
    function loadScheduleList() {
        var table = $('#schedules-table tbody');
        table.html('<tr><td colspan="8" class="loading-text">' + scheduleData.i18n.loading + '</td></tr>');
        
        $.ajax({
            url: scheduleData.ajax_url,
            type: 'POST',
            data: {
                action: 'avatar_parlante_get_schedules',
                status: $('#filter-status').val(),
                invoice: $('#search-invoice').val(),
                nonce: scheduleData.nonce
            },
            success: function(response) {
                if (response.success && response.data.schedules.length) {
                    var rows = '';
                    
                    $.each(response.data.schedules, function(index, schedule) {
                        rows += `
                            <tr data-post-id="${schedule.post_id}">
                                <td>${schedule.post_title}</td>
                                <td>${schedule.post_type}</td>
                                <td>${schedule.invoice}</td>
                                <td>${schedule.duration}</td>
                                <td>${schedule.period}</td>
                                <td><span class="status-badge ${schedule.status_class}">${schedule.status}</span></td>
                                <td>${schedule.days_remaining}</td>
                                <td>
                                    <button class="button toggle-schedule" data-action="${schedule.is_active ? 'deactivate' : 'activate'}">
                                        ${schedule.is_active ? 'Desactivar' : 'Activar'}
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                    
                    table.html(rows);
                } else {
                    table.html('<tr><td colspan="8" class="no-results">' + scheduleData.i18n.no_posts + '</td></tr>');
                }
            }
        });
    }
    
    // Delegación de eventos para botones de acción en la tabla
    $('#schedules-table').on('click', '.toggle-schedule', function() {
        var button = $(this);
        var postId = button.closest('tr').data('post-id');
        var action = button.data('action');
        var confirmMessage = action === 'activate' 
            ? scheduleData.i18n.confirm_activate 
            : scheduleData.i18n.confirm_deactivate;
        
        if (!confirm(confirmMessage)) return;
        
        $.ajax({
            url: scheduleData.ajax_url,
            type: 'POST',
            data: {
                action: 'avatar_parlante_toggle_schedule',
                post_id: postId,
                action_type: action,
                nonce: scheduleData.nonce
            },
            success: function(response) {
                if (response.success) {
                    loadScheduleList();
                } else {
                    alert(response.data);
                }
            }
        });
    });
    
    // Filtrar listado
    $('#filter-status, #search-invoice').change(loadScheduleList);
    
    // Funciones auxiliares
    function calculateEndDate() {
        var duration = parseInt($('#schedule-duration').val());
        var startDate = $('#start-date').val();
        
        if (startDate && duration) {
            var endDate = new Date(startDate);
            endDate.setFullYear(endDate.getFullYear() + duration);
            
            var formattedDate = endDate.toISOString().split('T')[0];
            $('#end-date').val(formattedDate);
            
            updateStatusIndicator(startDate, formattedDate);
        }
    }
    
    function updateStatusIndicator(startDate, endDate) {
        var today = new Date().toISOString().split('T')[0];
        var statusBadge = $('#schedule-status');
        var daysRemaining = $('#days-remaining');
        
        if (today < startDate) {
            statusBadge.removeClass().addClass('status-badge pending').text('Pendiente');
        } else if (today > endDate) {
            statusBadge.removeClass().addClass('status-badge expired').text('Expirado');
        } else {
            statusBadge.removeClass().addClass('status-badge active').text('Activo');
        }
        
        // Calcular días restantes
        var end = new Date(endDate);
        var todayObj = new Date(today);
        var diffTime = end - todayObj;
        var diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        if (diffDays > 0) {
            daysRemaining.text('(' + diffDays + ' días restantes)').show();
        } else {
            daysRemaining.text('').hide();
        }
    }
    
    function loadScheduleData(schedule) {
        $('#invoice-number').val(schedule.invoice);
        $('#schedule-duration').val(schedule.duration);
        $('#start-date').val(schedule.start_date);
        $('#end-date').val(schedule.end_date);
        updateStatusIndicator(schedule.start_date, schedule.end_date);
    }
    
    function resetForm() {
        $('#invoice-number').val('');
        $('#schedule-duration').val('1');
        $('#start-date').val(new Date().toISOString().split('T')[0]);
        calculateEndDate();
    }
    
    function validateForm() {
        if (!$('#schedule-post-id').val()) {
            alert('Seleccione una publicación');
            return false;
        }
        
        if (!$('#invoice-number').val()) {
            alert('Ingrese un número de factura');
            return false;
        }
        
        return true;
    }
    
    // Cargar listado inicial
    loadScheduleList();
});