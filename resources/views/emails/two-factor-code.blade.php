<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Código de Verificación 2FA - TwoDrive</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Inter", sans-serif;
            background: #0a0f1c;
            color: #e1e8f0;
            line-height: 1.6;
            padding: 10px 0;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 12px;
            backdrop-filter: blur(10px);
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(29, 78, 216, 0.15);
        }

        .email-header {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(29, 78, 216, 0.2) 100%);
            padding: 40px 30px;
            text-align: center;
            border-bottom: 1px solid rgba(59, 130, 246, 0.1);
        }

        .email-logo {
            max-width: 160px;
            height: auto;
            filter: drop-shadow(0 4px 12px rgba(29, 78, 216, 0.3));
            margin-bottom: 20px;
        }

        .email-title {
            font-size: 2rem;
            font-weight: 600;
            color: #3b82f6;
            margin-bottom: 10px;
            letter-spacing: -0.02em;
        }

        .email-subtitle {
            font-size: 1.1rem;
            color: #94a3b8;
            font-weight: 400;
        }

        .email-content {
            padding: 30px;
        }

        .security-card {
            background: rgba(29, 78, 216, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }

        .security-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
            display: block;
        }

        .security-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #60a5fa;
            margin-bottom: 10px;
        }

        .code-container {
            background: rgba(0, 0, 0, 0.3);
            border: 2px solid #3b82f6;
            border-radius: 8px;
            padding: 25px;
            text-align: center;
            margin: 25px 0;
            position: relative;
        }

        .code-container::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 1px;
            background: linear-gradient(90deg, transparent, #3b82f6, transparent);
        }

        .verification-code {
            font-size: 2.2rem;
            font-weight: 700;
            color: #60a5fa;
            letter-spacing: 8px;
            font-family: "Inter", monospace;
            text-shadow: 0 2px 4px rgba(29, 78, 216, 0.3);
        }

        .info-card {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(59, 130, 246, 0.1);
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }

        .info-title {
            font-weight: 600;
            color: #e1e8f0;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .feature-list {
            list-style: none;
            padding: 0;
            margin: 15px 0;
        }

        .feature-list li {
            padding: 8px 0;
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .feature-list li::before {
            content: "✓";
            color: #3b82f6;
            font-weight: bold;
            font-size: 1.1rem;
        }

        .warning-card {
            background: rgba(251, 146, 60, 0.1);
            border: 1px solid rgba(251, 146, 60, 0.3);
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }

        .warning-title {
            font-weight: 600;
            color: #fb923c;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .warning-list {
            list-style: none;
            padding: 0;
            margin: 15px 0;
        }

        .warning-list li {
            padding: 8px 0;
            color: #fbbf24;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .warning-list li::before {
            content: "⚠";
            color: #fb923c;
            font-weight: bold;
            font-size: 1rem;
        }

        .email-footer {
            background: rgba(15, 23, 42, 0.6);
            border-top: 1px solid rgba(59, 130, 246, 0.1);
            padding: 25px;
            text-align: center;
            color: #64748b;
            font-size: 0.9rem;
        }

        .footer-brand {
            color: #3b82f6;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .text-highlight {
            color: #60a5fa;
            font-weight: 500;
        }

        .text-warning {
            color: #fbbf24;
            font-weight: 500;
        }

        @media (max-width: 640px) {
            .email-container {
                margin: 10px;
                border-radius: 8px;
            }

            .email-header {
                padding: 30px 20px;
            }

            .email-content {
                padding: 20px;
            }

            .email-title {
                font-size: 1.7rem;
            }

            .verification-code {
                font-size: 1.8rem;
                letter-spacing: 6px;
            }
        }
    </style>
</head>

<body>
    <div class="email-container">
        <div class="email-header">
            <img src="{{ $message->embed(public_path('logos/twodrive_png.png')) }}" alt="TwoDrive Logo" class="email-logo"
                style="max-width: 120px; width: 100%; height: auto; display: block; margin: 0 auto;">
            <h1 class="email-title">Inicio de sesion con 2FA</h1>
            <p class="email-subtitle">Hola <span class="text-highlight">{{ $userName }}</span>,</p>
        </div>

        <div class="email-content">
            <p>Has solicitado iniciar sesión en tu cuenta con autenticación de dos factores activada. Para completar el
                proceso de autenticación, necesitamos verificar tu identidad.</p>
            <p>Tu código de verificación es:</p>

            <div class="code-container">
                <div class="verification-code">{{ $code }}</div>
            </div>

            <div class="info-card">
                <div class="info-title">
                    📋 Instrucciones
                </div>
                <ul class="feature-list">
                    <li>Ingresa este código en la página de autenticación</li>
                    <li>Este código expira en<span class="text-highlight">&#8203;10 minutos</span></li>
                    <li>Solo úsalo si realmente iniciaste sesión</li>
                    <li>Si no solicitaste este código, puedes ignorar este email</li>
                </ul>
            </div>
        </div>

        <div class="email-footer">
            <p>Este es un email automático, por favor no respondas a este mensaje.</p>
            <p class="footer-brand">Sistema de Seguridad TwoDrive</p>
            <p>© {{ date('Y') }} Todos los derechos reservados</p>
        </div>
    </div>
</body>

</html>
