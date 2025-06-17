<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Email</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }

        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            width: 150px;
            height: auto;
            margin-bottom: 20px;
        }

        h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .code-container {
            background-color: #f8f9fa;
            border: 2px solid #28a745;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
        }

        .code {
            font-size: 32px;
            font-weight: bold;
            color: #28a745;
            letter-spacing: 5px;
            font-family: 'Courier New', monospace;
        }

        .info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            color: #0c5460;
        }

        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            color: #666;
            font-size: 14px;
        }

        .welcome {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            color: #155724;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <img src="{{ asset('logos/twodrive_png.png') }}" alt="Logo" class="logo">
            <h1>¡Bienvenido a TwoDrive!</h1>
            <p>Hola <strong>{{ $userName }}</strong>,</p>
        </div>

        <div class="welcome">
            <h3>🎉 ¡Gracias por registrarte!</h3>
            <p>Tu cuenta ha sido creada exitosamente. Para completar el proceso de registro y activar tu cuenta,
                necesitamos verificar tu dirección de email.</p>
        </div>

        <p>Tu código de verificación es:</p>

        <div class="code-container">
            <div class="code">{{ $code }}</div>
        </div>

        <div class="info">
            <strong>📋 Instrucciones:</strong>
            <ul>
                <li>Ingresa este código en la página de verificación</li>
                <li>Este código expira en <strong>30 minutos</strong></li>
                <li>Una vez verificado, podrás acceder a todas las funcionalidades</li>
                <li>Si no solicitaste este registro, puedes ignorar este email</li>
            </ul>
        </div>

        <p><strong>¿Qué puedes hacer después de verificar tu cuenta?</strong></p>
        <ul>
            <li>✅ Iniciar sesión de forma segura</li>
            <li>✅ Activar autenticación de dos factores</li>
            <li>✅ Acceder a todas las funcionalidades del sistema</li>
            <li>✅ Gestionar tu perfil y configuraciones</li>
        </ul>

        <div class="footer">
            <p>Si tienes problemas con la verificación, contacta a nuestro equipo de soporte.</p>
            <p>Este es un email automático, por favor no respondas a este mensaje.</p>
            <p><strong>Sistema de Seguridad TwoDrive</strong></p>
            <p>© {{ date('Y') }} Todos los derechos reservados</p>
        </div>
    </div>
</body>

</html>
