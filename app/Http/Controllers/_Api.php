<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class _Api extends Controller
{
    /**
     * API Version Information
     *
     * Endpoint Público
     *
     * Este método proporciona información básica sobre la versión y estado de la API.
     *
     * Útil para verificar el correcto funcionamiento de la API y obtener detalles
     * sobre el entorno de ejecución, versiones de componentes y timestamp actual.
     */
    public function version()
    {
        return response()->json([
            'app_name' => env('APP_NAME'),
            'version' => env('APP_VERSION'),
            'laravel_version' => app()->version(),
            'php_version' => phpversion(),
            'environment' => env('APP_ENV'),
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * API Routes Documentation
     *
     * Endpoint Público
     *
     * Este método lista todas las rutas disponibles en la API para verificación y documentación.
     *
     * Proporciona un mapeo completo de endpoints, métodos HTTP permitidos, acciones del controlador
     * y middleware aplicado. Útil para verificar el correcto funcionamiento y configuración de rutas.
     */
    public function routes()
    {
        $routes = collect(app('router')->getRoutes())->filter(function ($route) {
            // Solo rutas del grupo 'api'
            return $route->getAction('middleware') && in_array('api', (array) $route->getAction('middleware'));
        })->map(function ($route) {
            return [
                'uri' => $route->uri(),
                'methods' => $route->methods(),
                'action' => $route->getActionName(),
                'middleware' => $route->gatherMiddleware(),
            ];
        })->values();

        // Agregar uri_base al inicio manteniendo el orden original
        $response = $routes->prepend([
            'uri_base' => request()->getSchemeAndHttpHost() . '/'
        ]);

        return response()->json($response);
    }
}
