( function( $ ) {
    'use strict';

    /**
     * Objeto principal para el frontend del Avatar Parlante
     */
    const AvatarParlanteFrontend = {

        // Variables de estado
        synth: null,
        currentUtterance: null,
        isListening: false,
        recognition: null,

        /**
         * Inicializar todas las funcionalidades
         */
        init: function() {
            // Verificar compatibilidad
            if ( ! this.checkCompatibility() ) {
                return;
            }

            // Inicializar componentes
            this.synth = window.speechSynthesis;
            this.initElements();
            this.initVoices();
            this.bindEvents();
            this.initAvatar();

            // Autoplay si está configurado
            if ( this.$container.data( 'autoplay' ) === 'yes' ) {
                this.readContent();
            }
        },

        /**
         * Verificar compatibilidad del navegador
         */
        checkCompatibility: function() {
            if ( ! ( 'speechSynthesis' in window ) ) {
                this.$container.html( 
                    '<div class="avatar-error">' + 
                    avatarParlanteFrontend.i18n.browser_not_supported + 
                    '</div>' 
                );
                return false;
            }
            return true;
        },

        /**
         * Inicializar elementos del DOM
         */
        initElements: function() {
            this.$container = $( '.avatar-parlante-container' );
            this.$avatar = $( '.avatar-image', this.$container );
            this.$readButton = $( '.avatar-read-button', this.$container );
            this.$stopButton = $( '.avatar-stop-button', this.$container );
            this.$questionInput = $( '.avatar-question-input', this.$container );
            this.$askButton = $( '.avatar-ask-button', this.$container );
            this.$responseContainer = $( '.avatar-response', this.$container );
            this.$micButton = $( '.avatar-mic-button', this.$container );
        },

        /**
         * Cargar voces disponibles
         */
        initVoices: function() {
            // Cargar voces cuando estén disponibles
            this.synth.onvoiceschanged = () => {
                this.voices = this.synth.getVoices();
                
                // Configurar voz preferida
                this.setPreferredVoice();
            };

            // Obtener voces inmediatamente si ya están cargadas
            this.voices = this.synth.getVoices();
            if ( this.voices.length > 0 ) {
                this.setPreferredVoice();
            }
        },

        /**
         * Configurar voz preferida según la configuración
         */
        setPreferredVoice: function() {
            if ( ! this.voices || this.voices.length === 0 ) return;

            const settings = avatarParlanteFrontend.settings;
            this.preferredVoice = this.voices.find( voice => 
                voice.lang === settings.voice_lang && 
                ( settings.voice_type === 'female' ? 
                    voice.name.includes( 'Female' ) : 
                    voice.name.includes( 'Male' ) )
            );
        },

        /**
         * Inicializar animaciones del avatar
         */
        initAvatar: function() {
            // Precargar imagen del avatar seleccionado
            const avatar = this.$container.data( 'avatar' ) || 
                         avatarParlanteFrontend.settings.default_avatar;
            this.$avatar.css( 'background-image', `url(${avatarParlanteFrontend.assets_url}/images/avatars/${avatar}.png)` );
        },

        /**
         * Vincular eventos
         */
        bindEvents: function() {
            // Botón de leer contenido
            this.$readButton.on( 'click', () => this.readContent() );

            // Botón de detener
            this.$stopButton.on( 'click', () => this.stopSpeaking() );

            // Botón de preguntar
            this.$askButton.on( 'click', () => this.askQuestion() );

            // Botón de micrófono (reconocimiento de voz)
            if ( 'webkitSpeechRecognition' in window ) {
                this.initSpeechRecognition();
                this.$micButton.on( 'click', () => this.toggleSpeechRecognition() );
            } else {
                this.$micButton.hide();
            }

            // Tecla Enter en el campo de pregunta
            this.$questionInput.on( 'keypress', ( e ) => {
                if ( e.which === 13 ) {
                    this.askQuestion();
                }
            } );

            // Evento cuando termina de hablar
            $( document ).on( 'avatarFinishedSpeaking', () => {
                this.$avatar.removeClass( 'avatar-speaking' );
                this.$stopButton.hide();
                this.$readButton.show();
            } );
        },

        /**
         * Inicializar reconocimiento de voz (si está disponible)
         */
        initSpeechRecognition: function() {
            this.recognition = new webkitSpeechRecognition();
            this.recognition.continuous = false;
            this.recognition.interimResults = false;
            this.recognition.lang = avatarParlanteFrontend.settings.voice_lang;

            this.recognition.onstart = () => {
                this.isListening = true;
                this.$micButton.addClass( 'active' );
                this.$responseContainer.html( 
                    '<div class="avatar-listening">' + 
                    avatarParlanteFrontend.i18n.listening + 
                    '</div>' 
                );
            };

            this.recognition.onresult = ( event ) => {
                const transcript = event.results[0][0].transcript;
                this.$questionInput.val( transcript );
                this.askQuestion();
            };

            this.recognition.onerror = ( event ) => {
                console.error( 'Error en reconocimiento de voz:', event.error );
                this.$responseContainer.html( 
                    '<div class="avatar-error">' + 
                    avatarParlanteFrontend.i18n.recognition_error + 
                    '</div>' 
                );
            };

            this.recognition.onend = () => {
                this.isListening = false;
                this.$micButton.removeClass( 'active' );
            };
        },

        /**
         * Activar/desactivar reconocimiento de voz
         */
        toggleSpeechRecognition: function() {
            if ( this.isListening ) {
                this.recognition.stop();
            } else {
                this.recognition.start();
            }
        },

        /**
         * Leer el contenido de la página
         */
        readContent: function() {
            // Obtener contenido de la página
            const content = this.getPageContent();

            if ( ! content ) {
                this.showError( avatarParlanteFrontend.i18n.no_content );
                return;
            }

            // Leer el contenido
            this.speak( content, 'content' );
        },

        /**
         * Obtener contenido de la página para leer
         */
        getPageContent: function() {
            // Intentar encontrar el contenido principal
            let content = '';

            // Selectores posibles donde podría estar el contenido
            const selectors = [
                '.avatar-content-source',
                'article',
                '.post-content',
                '.entry-content',
                '#content',
                '#main'
            ];

            // Buscar en los selectores hasta encontrar contenido
            selectors.some( selector => {
                const $element = $( selector );
                if ( $element.length ) {
                    content = $element.text().trim();
                    return content !== '';
                }
                return false;
            } );

            return content || '';
        },

        /**
         * Hacer una pregunta al avatar
         */
        askQuestion: function() {
            const question = this.$questionInput.val().trim();

            if ( ! question ) {
                this.showError( avatarParlanteFrontend.i18n.empty_question );
                return;
            }

            // Mostrar estado de "pensando"
            this.$responseContainer.html( 
                '<div class="avatar-thinking">' + 
                avatarParlanteFrontend.i18n.thinking + 
                '</div>' 
            );

            // Enviar pregunta al servidor
            $.post(
                avatarParlanteFrontend.ajax_url,
                {
                    action: 'avatar_parlante_answer',
                    question: question,
                    post_id: this.$container.data( 'post-id' ),
                    nonce: avatarParlanteFrontend.nonce
                }
            )
            .done( response => this.handleQuestionResponse( response, question ) )
            .fail( () => this.showError( avatarParlanteFrontend.i18n.connection_error ) );
        },

        /**
         * Procesar respuesta a la pregunta
         */
        handleQuestionResponse: function( response, question ) {
            if ( response.success ) {
                // Mostrar respuesta
                this.$responseContainer.html( 
                    '<div class="avatar-question">' + 
                    '<strong>' + avatarParlanteFrontend.i18n.question + '</strong>: ' + 
                    question + 
                    '</div>' +
                    '<div class="avatar-answer">' + 
                    '<strong>' + avatarParlanteFrontend.i18n.answer + '</strong>: ' + 
                    response.data.answer + 
                    '</div>'
                );

                // Leer la respuesta en voz alta
                this.speak( response.data.answer, 'answer' );
            } else {
                // Mostrar error o respuesta por defecto
                const fallbackResponses = [
                    avatarParlanteFrontend.i18n.no_answer_1,
                    avatarParlanteFrontend.i18n.no_answer_2,
                    avatarParlanteFrontend.i18n.no_answer_3
                ];
                
                const randomResponse = fallbackResponses[
                    Math.floor( Math.random() * fallbackResponses.length )
                ];
                
                this.$responseContainer.html( 
                    '<div class="avatar-answer">' + randomResponse + '</div>' 
                );
                this.speak( randomResponse, 'fallback' );
            }
        },

        /**
         * Sintetizar voz
         */
        speak: function( text, type = 'content' ) {
            // Cancelar si ya está hablando
            if ( this.synth.speaking ) {
                this.synth.cancel();
            }

            // Crear nuevo utterance
            this.currentUtterance = new SpeechSynthesisUtterance( text );

            // Configurar voz
            const settings = avatarParlanteFrontend.settings;
            this.currentUtterance.rate = settings.voice_speed;
            this.currentUtterance.pitch = settings.voice_pitch;
            
            // Usar voz preferida si está disponible
            if ( this.preferredVoice ) {
                this.currentUtterance.voice = this.preferredVoice;
            }

            // Eventos
            this.currentUtterance.onstart = () => {
                this.$avatar.addClass( 'avatar-speaking' );
                this.$readButton.hide();
                this.$stopButton.show();
                
                // Disparar evento personalizado
                $( document ).trigger( 'avatarStartedSpeaking', [ type ] );
            };

            this.currentUtterance.onend = () => {
                $( document ).trigger( 'avatarFinishedSpeaking', [ type ] );
            };

            this.currentUtterance.onerror = ( event ) => {
                console.error( 'Error en síntesis de voz:', event );
                this.showError( avatarParlanteFrontend.i18n.speech_error );
                $( document ).trigger( 'avatarFinishedSpeaking', [ type ] );
            };

            // Comenzar a hablar
            this.synth.speak( this.currentUtterance );
        },

        /**
         * Detener de hablar
         */
        stopSpeaking: function() {
            if ( this.synth.speaking ) {
                this.synth.cancel();
                $( document ).trigger( 'avatarFinishedSpeaking' );
            }
        },

        /**
         * Mostrar mensaje de error
         */
        showError: function( message ) {
            this.$responseContainer.html( 
                '<div class="avatar-error">' + message + '</div>' 
            );
        }
    };

    // Inicializar cuando el DOM esté listo
    $( document ).ready( function() {
        AvatarParlanteFrontend.init();
    } );

} )( jQuery );