@extends('app')

@push('styles')
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            user-select: none;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 50%, #16213e 100%);
            color: #e2e8f0;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .container {
            min-height: 100vh;
            display: flex;
            flex-direction: column;

            position: relative;
        }

        .background-pattern {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 118, 117, 0.1) 0%, transparent 50%);
            z-index: -1;
        }

        .main-content {
            display: flex;
            flex: 1;
            gap: 4rem;
            z-index: 1;
            max-width: 1400px;
            width: 100%;
            margin: 0 auto;
            animation: fadeInUp 1s ease-out;
            align-items: stretch;
            padding: 2rem;
        }

        .left-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            text-align: left;
            min-height: 100%;
        }

        .right-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 100%;
        }

        .logo-container {
            margin-bottom: 3rem;
            animation: fadeIn 1.5s ease-out;
        }

        .logo {
            max-width: 200px;
            height: auto;
            filter: drop-shadow(0 10px 30px rgba(0, 0, 0, 0.3));
            transition: transform 0.3s ease;
        }

        .logo:hover {
            transform: scale(1.05);
        }

        .title {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.02em;
            line-height: 1.1;
        }

        .subtitle {
            font-size: 1.5rem;
            font-weight: 300;
            color: #94a3b8;
            margin-bottom: 2rem;
            letter-spacing: 0.01em;
        }

        .highlight {
            color: #f59e0b;
            font-weight: 600;
        }

        .features {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .feature-card {
            background: rgba(30, 41, 59, 0.4);
            border: 1px solid rgba(148, 163, 184, 0.1);
            border-radius: 16px;
            padding: 1.5rem;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            border-color: rgba(148, 163, 184, 0.2);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .feature-card:hover::before {
            transform: scaleX(1);
        }

        .feature-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            display: block;
        }

        .feature-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #e2e8f0;
        }

        .feature-description {
            font-size: 0.95rem;
            color: #94a3b8;
            line-height: 1.6;
        }

        .tech-stack {
            padding: 0;
            background: transparent;
            border: none;
        }

        .tech-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #cbd5e1;
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        .tech-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            justify-content: center;
        }

        .tech-item {
            background: rgba(51, 65, 85, 0.6);
            color: #e2e8f0;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            border: 1px solid rgba(148, 163, 184, 0.1);
            transition: all 0.3s ease;
        }

        .tech-item:hover {
            background: rgba(67, 56, 202, 0.3);
            border-color: #667eea;
            transform: translateY(-2px);
        }

        .footer {
            text-align: center;
            padding: 2rem;
            color: #64748b;
            font-size: 0.9rem;
            border-top: 1px solid rgba(148, 163, 184, 0.1);
            margin-top: auto;
        }

        .api-links-container {
            display: flex;
            gap: 1.5rem;
            margin: 1.5rem auto;
            max-width: 900px;
            justify-content: center;
        }

        .api-endpoint {
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 8px;
            padding: 1rem;
            flex: 1;
            backdrop-filter: blur(10px);
        }

        .docs-endpoint {
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 8px;
            padding: 1rem;
            flex: 1;
            backdrop-filter: blur(10px);
        }

        .api-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: #cbd5e1;
            margin-top: 0.75rem;
            padding-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            text-align: left;
        }

        .code-block {
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(148, 163, 184, 0.1);
            border-radius: 6px;
            padding: 0.75rem;
            font-family: 'Fira Code', 'Courier New', monospace;
            font-size: 1rem;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }

        .api-link {
            color: #10b981;
            text-decoration: none;
            transition: all 0.3s ease;
            flex: 1;
        }

        .api-link:hover {
            color: #34d399;
            text-shadow: 0 0 10px rgba(16, 185, 129, 0.5);
        }

        .copy-button {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid #10b981;
            border-radius: 4px;
            padding: 0.4rem 0.6rem;
            color: #10b981;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            white-space: nowrap;
        }

        .copy-button:hover {
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
            transform: translateY(-1px);
        }

        .copy-button:active {
            transform: translateY(0);
        }

        .copy-button.copied {
            background: rgba(34, 197, 94, 0.3);
            color: #22c55e;
            border-color: #22c55e;
        }

        .docs-link {
            color: #3b82f6;
            text-decoration: none;
            transition: all 0.3s ease;
            flex: 1;
        }

        .docs-link:hover {
            color: #60a5fa;
            text-shadow: 0 0 10px rgba(59, 130, 246, 0.5);
        }

        .docs-button {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid #3b82f6;
            border-radius: 4px;
            padding: 0.4rem 0.6rem;
            color: #3b82f6;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            white-space: nowrap;
        }

        .docs-button:hover {
            background: rgba(59, 130, 246, 0.2);
            color: #60a5fa;
            transform: translateY(-1px);
        }

        .docs-button:active {
            transform: translateY(0);
        }

        .docs-button.copied {
            background: rgba(34, 197, 94, 0.3);
            color: #22c55e;
            border-color: #22c55e;
        }

        .code-block::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, #10b981, #3b82f6);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 0.5;
            }

            50% {
                opacity: 1;
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
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

            .main-content {
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
                max-width: 150px;
            }

            .title {
                font-size: 2.5rem;
            }

            .subtitle {
                font-size: 1.2rem;

            }

            .features {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .feature-card {
                padding: 1.5rem;
            }

            .tech-title {
                font-size: 1rem;
            }

            .tech-list {
                gap: 0.5rem;
            }

            .tech-item {
                font-size: 0.8rem;
                padding: 0.4rem 0.8rem;
            }

            .footer {
                padding: 1.5rem 1rem;
            }

            .api-links-container {
                flex-direction: column;
                gap: 1rem;
                margin: 1rem 0;
            }

            .api-endpoint,
            .docs-endpoint {
                margin: 0;
                padding: 0.75rem;
                max-width: 100%;
            }

            .code-block {
                font-size: 0.9rem;
                padding: 0.6rem;
                flex-direction: column;
                gap: 0.6rem;
            }

            .copy-button,
            .docs-button {
                font-size: 0.75rem;
                padding: 0.35rem 0.5rem;
            }
        }

        /* Responsive Design para tablets */
        @media (max-width: 1024px) and (min-width: 769px) {
            .main-content {
                gap: 3rem;
                padding: 0;
            }

            .title {
                font-size: 3rem;
            }

            .subtitle {
                font-size: 1.3rem;
            }

            .features {
                gap: 1.5rem;
            }
        }
    </style>
@endpush

@section('content')
    <div class="container">
        <div class="background-pattern"></div>

        <div class="main-content">
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
        </div>

        <!-- Footer con Stack Tecnológico -->
        <footer class="footer">
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
        </footer>
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
