@extends('app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}">
    <style>
        /* ===== ANIMACIONES ESPECÍFICAS DE WELCOME ===== */
        .main-content {
            animation: fadeInUp 0.8s ease-out;
        }

        .logo-container {
            animation: fadeIn 1s ease-out;
        }

        .code-block::before {
            animation: pulse 3s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 0.3;
            }

            50% {
                opacity: 0.8;
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 0.3;
            }

            50% {
                opacity: 0.8;
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        /* Responsive Design para móviles */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            main {
                flex-direction: column;
                gap: 2rem;
                padding: 0;
            }

            .left-section {
                text-align: center;
            }

            .right-section {
                width: 100%;
            }

            .logo {
                max-width: 120px;
            }

            .title {
                font-size: 2.2rem;
            }

            .subtitle {
                font-size: 1rem;
            }

            .features {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .feature-card {
                padding: 1.2rem;
            }

            .tech-title {
                font-size: 0.8rem;
            }

            .tech-list {
                gap: 0.4rem;
            }

            .tech-item {
                font-size: 0.75rem;
                padding: 0.3rem 0.6rem;
            }

            footer {
                padding: 1.2rem 1rem;
            }

            .code-block {
                font-size: 0.8rem;
                padding: 0.6rem;
                flex-direction: column;
                gap: 0.5rem;
            }

            .copy-button,
            .docs-button {
                font-size: 0.7rem;
                padding: 0.3rem 0.5rem;
            }
        }

        /* Responsive Design para tablets */
        @media (max-width: 1024px) and (min-width: 769px) {
            main {
                gap: 2.5rem;
                padding: 1.5rem;
            }

            .title {
                font-size: 2.5rem;
            }

            .subtitle {
                font-size: 1rem;
            }

            .features {
                gap: 1.2rem;
            }
        }
    </style>
@endpush

@section('content')
    <div class="container">
        <main class="main-content">
            <!-- Sección Izquierda: Logo, Título y Subtítulo -->
            <div class="left-section">
                <div class="logo-container">
                    <img src="{{ asset('logos/twodrive_png.png') }}" alt="TwoDrive Logo" class="logo">
                </div>

                <h1 class="title">{{ env('APP_NAME') }}</h1>
                <p class="subtitle">Sistema de <span class="highlight">Gestión Segura</span> de PDFs</p>
                <!-- Sección Enlaces API y Documentación -->
                <h3 class="api-title">Punto de Acceso API</h3>
                <div class="code-block">
                    <a href="{{ request()->getSchemeAndHttpHost() }}/api" target="_blank" class="api-link" id="apiUrl">
                        {{ request()->getSchemeAndHttpHost() }}/api
                    </a>
                    <button class="copy-button" onclick="copyToClipboard('apiUrl')">
                        📋 Copiar
                    </button>
                </div>

                <h3 class="api-title">Documentación API(solo en local-desarrollo)</h3>
                <div class="code-block">
                    <a href="{{ request()->getSchemeAndHttpHost() }}/docs/api" target="_blank" class="docs-link"
                        id="docsUrl">
                        {{ request()->getSchemeAndHttpHost() }}/docs/api
                    </a>
                    <button class="docs-button" onclick="copyToClipboard('docsUrl')">
                        📋 Copiar
                    </button>
                </div>
            </div>

            <!-- Sección Derecha: Características -->
            <div class="right-section">
                <div class="features">
                    <div class="feature-card">
                        <div class="feature-icon">🔐</div>
                        <h3 class="feature-title">Seguridad Avanzada</h3>
                        <p class="feature-description">Autenticación JWT, control de permisos y auditoría completa de
                            accesos</p>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon">📄</div>
                        <h3 class="feature-title">Gestión de PDFs</h3>
                        <p class="feature-description">Sistema completo para manejo, almacenamiento y control de documentos
                            PDF</p>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon">👥</div>
                        <h3 class="feature-title">Control de Usuarios</h3>
                        <p class="feature-description">Gestión avanzada de usuarios y permisos granulares por documento</p>
                    </div>

                    <div class="feature-card">
                        <div class="feature-icon">📊</div>
                        <h3 class="feature-title">API RESTful</h3>
                        <p class="feature-description">Endpoints bien documentados y seguros para integración con frontend
                        </p>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer con Stack Tecnológico -->
        <div class="footer">
            <div class="tech-stack">
                <h3 class="tech-title">Stack Tecnológico</h3>
                <div class="tech-list">
                    <span class="tech-item">Laravel 11</span>
                    <span class="tech-item">PHP 8.2.18</span>
                    <span class="tech-item">JWT Auth</span>
                    <span class="tech-item">MySQL</span>
                    <span class="tech-item">RESTful API</span>
                    <span class="tech-item">MVC Pattern</span>
                    <span class="tech-item">AES encryption</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        function copyToClipboard(elementId) {
            const url = document.getElementById(elementId).textContent.trim();
            const button = event.target;

            navigator.clipboard.writeText(url).then(function() {
                // Cambiar el texto y estilo del botón temporalmente
                const originalText = button.innerHTML;
                button.innerHTML = '✓ Copiado';
                button.classList.add('copied');

                // Restaurar después de 2 segundos
                setTimeout(function() {
                    button.innerHTML = originalText;
                    button.classList.remove('copied');
                }, 2000);
            }).catch(function(err) {
                console.error('Error al copiar: ', err);
                // Fallback para navegadores más antiguos
                const textArea = document.createElement('textarea');
                textArea.value = url;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);

                // Feedback visual
                const originalText = button.innerHTML;
                button.innerHTML = '✓ Copiado';
                button.classList.add('copied');

                setTimeout(function() {
                    button.innerHTML = originalText;
                    button.classList.remove('copied');
                }, 2000);
            });
        }
    </script>
@endsection
