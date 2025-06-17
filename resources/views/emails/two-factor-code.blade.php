<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Código de Verificación de Dos Factores</title>
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
            border: 2px solid #007bff;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
        }

        .code {
            font-size: 32px;
            font-weight: bold;
            color: #007bff;
            letter-spacing: 5px;
            font-family: 'Courier New', monospace;
        }

        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            color: #856404;
        }

        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            color: #666;
            font-size: 14px;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 0;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <img src="{{ asset('logos/twodrive_png.png') }}" alt="Logo" class="logo">
            <h1>Código de Verificación</h1>
            <p>Hola <strong>{{ $userName }}</strong>,</p>
        </div>

        <p>Has solicitado iniciar sesión en tu cuenta con autenticación de dos factores activada.</p>

        <p>Tu código de verificación es:</p>

        <div class="code-container">
            <div class="code">{{ $code }}</div>
        </div>

        <div class="warning">
            <strong>⚠️ Importante:</strong>
            <ul>
                <li>Este código expira en <strong>10 minutos</strong></li>
                <li>Solo úsalo si realmente iniciaste sesión</li>
                <li>Nunca compartas este código con nadie</li>
                <li>Si no solicitaste este código, ignora este email</li>
            </ul>
        </div>

        <p>Si tienes problemas para acceder a tu cuenta, contacta a nuestro equipo de soporte.</p>

        <div class="footer">
            <p>Este es un email automático, por favor no respondas a este mensaje.</p>
            <p><strong>Sistema de Seguridad TwoDrive</strong></p>
            <p>© {{ date('Y') }} Todos los derechos reservados</p>
        </div>
    </div>
</body>

</html>
