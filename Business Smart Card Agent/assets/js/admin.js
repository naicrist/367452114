( function( $ ) {
    'use strict';

    /**
     * Objeto principal para el admin del Avatar Parlante
     */
    const AvatarParlanteAdmin = {

        /**
         * Inicializar todas las funcionalidades
         */
        init: function() {
            this.bindEvents();
            this.initDatePickers();
            this.initVoicePreview();
            this.initTabs();
            this.toggleReportFilters();
        },

        /**
         * Vincular eventos
         */
        bindEvents: function() {
            // Guardar configuración general
            $( '#avatar-settings-form' ).on( 'submit', this.saveSettings );

            // Guardar programación
            $( '#save-schedule' ).on( 'click', this.saveSchedule );

            // Generar reporte
            $( '#generate-report' ).on( 'click', this.generateReport );

            // Selector de posts para programación
            $( '#schedule-post-type' ).on( 'change', this.loadPostsForScheduling );

            // Probador de voz
            $( '#test-voice-btn' ).on( 'click', this.testVoiceSettings );

            // Toggle de filtros de reportes
            $( '#toggle-report-filters' ).on( 'click', this.toggleReportFilters );
        },

        /**
         * Inicializar datepickers
         */
        initDatePickers: function() {
            $( '.datepicker' ).datepicker( {
                dateFormat: 'yy-mm-dd',
                minDate: 0
            } );
        },

        /**
         * Inicializar vista previa de voz
         */
        initVoicePreview: function() {
            if ( 'speechSynthesis' in window ) {
                $( '#voice-test-container' ).show();
            }
        },

        /**
         * Inicializar sistema de pestañas
         */
        initTabs: function() {
            $( '.nav-tab-wrapper a' ).on( 'click', function( e ) {
                e.preventDefault();
                
                // Activar pestaña
                $( '.nav-tab-wrapper a' ).removeClass( 'nav-tab-active' );
                $( this ).addClass( 'nav-tab-active' );
                
                // Mostrar contenido correspondiente
                const tab = $( this ).attr( 'href' );
                $( '.avatar-tab-content' ).hide();
                $( tab ).show();
            } );
        },

        /**
         * Mostrar/ocultar filtros de reportes
         */
        toggleReportFilters: function() {
            $( '#report-filters' ).toggle();
        },

        /**
         * Guardar configuración general
         */
        saveSettings: function( e ) {
            e.preventDefault();

            const $form = $( this );
            const $submitBtn = $form.find( '.submit .button-primary' );
            const originalText = $submitBtn.val();

            // Mostrar estado de guardando
            $submitBtn.val( avatarParlanteAdmin.i18n.saving ).prop( 'disabled', true );

            $.post( 
                avatarParlanteAdmin.ajax_url,
                {
                    action: 'avatar_parlante_save_settings',
                    data: $form.serialize(),
                    nonce: avatarParlanteAdmin.nonce
                }
            )
            .done( function( response ) {
                if ( response.success ) {
                    // Mostrar mensaje de éxito
                    $( '#setting-error-settings_updated' ).remove();
                    $form.before( 
                        '<div id="setting-error-settings_updated" class="notice notice-success settings-error is-dismissible">' +
                        '<p><strong>' + response.data.message + '</strong></p>' +
                        '</div>'
                    );
                } else {
                    // Mostrar error
                    $( '#setting-error-settings_error' ).remove();
                    $form.before( 
                        '<div id="setting-error-settings_error" class="notice notice-error settings-error is-dismissible">' +
                        '<p><strong>' + response.data.message + '</strong></p>' +
                        '</div>'
                    );
                }
            } )
            .fail( function() {
                $( '#setting-error-settings_error' ).remove();
                $form.before( 
                    '<div id="setting-error-settings_error" class="notice notice-error settings-error is-dismissible">' +
                    '<p><strong>' + avatarParlanteAdmin.i18n.error + '</strong></p>' +
                    '</div>'
                );
            } )
            .always( function() {
                $submitBtn.val( originalText ).prop( 'disabled', false );
            } );
        },

        /**
         * Cargar posts para programación
         */
        loadPostsForScheduling: function() {
            const postType = $( this ).val();
            const $postsSelect = $( '#schedule-post-id' );

            // Mostrar carga
            $postsSelect.html( '<option value="">' + avatarParlanteAdmin.i18n.loading + '</option>' );

            $.post(
                avatarParlanteAdmin.ajax_url,
                {
                    action: 'avatar_parlante_get_posts',
                    post_type: postType,
                    nonce: avatarParlanteAdmin.nonce
                }
            )
            .done( function( response ) {
                if ( response.success ) {
                    let options = '<option value="">' + avatarParlanteAdmin.i18n.select_post + '</option>';
                    
                    $.each( response.data.posts, function( id, title ) {
                        options += '<option value="' + id + '">' + title + '</option>';
                    } );
                    
                    $postsSelect.html( options );
                }
            } );
        },

        /**
         * Guardar programación
         */
        saveSchedule: function() {
            const $button = $( this );
            const originalText = $button.val();

            // Validar formulario
            const postId = $( '#schedule-post-id' ).val();
            if ( ! postId ) {
                alert( avatarParlanteAdmin.i18n.select_post_error );
                return;
            }

            $button.val( avatarParlanteAdmin.i18n.saving ).prop( 'disabled', true );

            $.post(
                avatarParlanteAdmin.ajax_url,
                {
                    action: 'avatar_parlante_save_schedule',
                    post_id: postId,
                    start_date: $( '#schedule-start-date' ).val(),
                    end_date: $( '#schedule-end-date' ).val(),
                    is_active: $( '#schedule-is-active' ).is( ':checked' ) ? 1 : 0,
                    nonce: avatarParlanteAdmin.nonce
                }
            )
            .done( function( response ) {
                if ( response.success ) {
                    alert( response.data.message );
                } else {
                    alert( response.data.message );
                }
            } )
            .always( function() {
                $button.val( originalText ).prop( 'disabled', false );
            } );
        },

        /**
         * Generar reporte
         */
        generateReport: function() {
            const $button = $( this );
            const originalText = $button.val();

            $button.val( avatarParlanteAdmin.i18n.generating ).prop( 'disabled', true );

            // Construir URL para descarga
            let url = avatarParlanteAdmin.ajax_url + '?action=avatar_parlante_generate_report';
            url += '&nonce=' + avatarParlanteAdmin.nonce;
            
            // Añadir filtros
            const filters = {
                post_id: $( '#report-post-id' ).val(),
                start_date: $( '#report-start-date' ).val(),
                end_date: $( '#report-end-date' ).val(),
                interaction_type: $( '#report-interaction-type' ).val()
            };
            
            Object.keys( filters ).forEach( function( key ) {
                if ( filters[ key ] ) {
                    url += '&' + key + '=' + encodeURIComponent( filters[ key ] );
                }
            } );

            // Forzar descarga
            window.location.href = url;
            
            // Restaurar botón después de un breve retraso
            setTimeout( function() {
                $button.val( originalText ).prop( 'disabled', false );
            }, 2000 );
        },

        /**
         * Probar configuración de voz
         */
        testVoiceSettings: function() {
            const $button = $( this );
            const originalText = $button.val();
            const testText = $( '#voice-test-text' ).val() || avatarParlanteAdmin.i18n.test_voice_default;

            if ( ! ( 'speechSynthesis' in window ) ) {
                alert( avatarParlanteAdmin.i18n.voice_not_supported );
                return;
            }

            $button.val( avatarParlanteAdmin.i18n.speaking ).prop( 'disabled', true );

            const utterance = new SpeechSynthesisUtterance( testText );
            
            // Configurar voz
            utterance.rate = parseFloat( $( '#voice-speed' ).val() );
            utterance.pitch = parseFloat( $( '#voice-pitch' ).val() );
            
            // Seleccionar voz
            const voices = window.speechSynthesis.getVoices();
            const lang = $( '#voice-lang' ).val();
            const voiceType = $( '#voice-type' ).val();
            
            const preferredVoice = voices.find( function( voice ) {
                return voice.lang === lang && 
                       ( voiceType === 'female' ? voice.name.includes( 'Female' ) : voice.name.includes( 'Male' ) );
            } );
            
            if ( preferredVoice ) {
                utterance.voice = preferredVoice;
            }

            utterance.onend = function() {
                $button.val( originalText ).prop( 'disabled', false );
            };

            window.speechSynthesis.speak( utterance );
        }
    };

    // Inicializar cuando el DOM esté listo
    $( document ).ready( function() {
        AvatarParlanteAdmin.init();
    } );

} )( jQuery );