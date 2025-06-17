<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmación de Eliminación de Cuenta</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .header {
            background-color: #dc3545;
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
        }

        .content {
            padding: 30px;
        }

        .code-box {
            background-color: #f8f9fa;
            border: 2px solid #dc3545;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
        }

        .code {
            font-size: 28px;
            font-weight: bold;
            color: #dc3545;
            letter-spacing: 3px;
            font-family: 'Courier New', monospace;
        }

        .warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }

        .footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 14px;
            color: #6c757d;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #dc3545;
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
            <h1>⚠️ Confirmación de Eliminación de Cuenta</h1>
        </div>

        <div class="content">
            <h2>Hola {{ $userName }},</h2>

            <p>Has solicitado eliminar tu cuenta permanentemente. Esta acción es <strong>irreversible</strong> y
                resultará en:</p>

            <ul>
                <li>Eliminación completa de tu perfil</li>
                <li>Eliminación de todos tus archivos PDF</li>
                <li>Eliminación de todos los permisos compartidos</li>
                <li>Eliminación del historial de inicios de sesión</li>
            </ul>

            <div class="warning">
                <strong>⚠️ ADVERTENCIA:</strong> Una vez confirmada la eliminación, no podrás recuperar tu cuenta ni tus
                archivos. Esta acción es permanente e irreversible.
            </div>

            <p>Para <strong>confirmar la eliminación</strong> de tu cuenta, utiliza el siguiente código de verificación:
            </p>

            <div class="code-box">
                <div class="code">{{ $deletionCode }}</div>
                <p style="margin: 10px 0 0 0; font-size: 14px; color: #6c757d;">
                    Este código expira en 30 minutos
                </p>
            </div>

            <p><strong>Si no solicitaste eliminar tu cuenta, ignora este email.</strong> Tu cuenta permanecerá activa y
                segura.</p>

            <p>Si tienes dudas o necesitas ayuda, contacta con nuestro equipo de soporte antes de proceder.</p>
        </div>

        <div class="footer">
            <p>Este es un email automático, por favor no responder.</p>
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.</p>
        </div>
    </div>
</body>

</html>
