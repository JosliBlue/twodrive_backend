<?php

namespace App\Http\Controllers;

use App\Utils\AESEncryption;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class _CifradoAES extends Controller
{
    private $aesEncryption;

    /**
     * Constructor - Instancia la clase AESEncryption
     */
    public function __construct()
    {
        $this->aesEncryption = new AESEncryption();
    }

    /**
     * Cifra un texto usando AES-128
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function cifrar(Request $request)
    {
        try {
            // Validar la entrada
            $validator = Validator::make($request->all(), [
                'texto' => 'required|string|max:10000' // Límite de 10KB para evitar textos excesivamente largos
            ], [
                'texto.required' => 'El campo texto es requerido',
                'texto.string' => 'El texto debe ser una cadena de caracteres',
                'texto.max' => 'El texto no puede exceder los 10,000 caracteres'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $textoPlano = $request->input('texto');

            // Cifrar el texto usando la instancia de AESEncryption
            $textoCifrado = $this->aesEncryption->encrypt($textoPlano);

            if ($textoCifrado === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al cifrar el texto. Verifique la configuración de la clave de cifrado.'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Texto cifrado exitosamente',
                'data' => [
                    'texto_original' => $textoPlano,
                    'texto_cifrado' => $textoCifrado,
                    'longitud_original' => strlen($textoPlano),
                    'longitud_cifrado' => strlen($textoCifrado)
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error en cifrado: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor al cifrar el texto'
            ], 500);
        }
    }

    /**
     * Descifra un texto usando AES-128
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function descifrar(Request $request)
    {
        try {
            // Validar la entrada
            $validator = Validator::make($request->all(), [
                'texto' => 'required|string|regex:/^[0-9a-fA-F]+$/' // Solo caracteres hexadecimales
            ], [
                'texto.required' => 'El campo texto es requerido',
                'texto.string' => 'El texto debe ser una cadena de caracteres',
                'texto.regex' => 'El texto cifrado debe contener solo caracteres hexadecimales (0-9, a-f, A-F)'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $textoCifrado = $request->input('texto');

            // Validar que la longitud sea múltiplo de 32 (16 bytes en hex = 32 caracteres)
            if (strlen($textoCifrado) % 32 !== 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'El texto cifrado debe tener una longitud múltiplo de 32 caracteres hexadecimales'
                ], 422);
            }

            // Descifrar el texto usando la instancia de AESEncryption
            $textoDescifrado = $this->aesEncryption->decrypt($textoCifrado);

            if ($textoDescifrado === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al descifrar el texto. Verifique que el texto cifrado sea válido y la clave de cifrado sea correcta.'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Texto descifrado exitosamente',
                'data' => [
                    'texto_cifrado' => $textoCifrado,
                    'texto_descifrado' => $textoDescifrado,
                    'longitud_cifrado' => strlen($textoCifrado),
                    'longitud_descifrado' => strlen($textoDescifrado)
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error en descifrado: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor al descifrar el texto'
            ], 500);
        }
    }

    /**
     * Obtiene información detallada sobre el sistema de cifrado AES implementado
     *
     * @return JsonResponse Información completa del sistema de cifrado
     */
    public function info(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Información del sistema de cifrado AES',
            'data' => [
                // ALGORITMO: Especifica la variante de AES utilizada
                // Opciones: AES-128, AES-192, AES-256 (según tamaño de clave)
                'algoritmo' => 'AES-128',

                // MODO DE OPERACIÓN: Define cómo se procesan múltiples bloques
                // Opciones comunes: ECB, CBC, CFB, OFB, CTR, GCM, CCM
                // Esta implementación: Básica sin modo específico (equivale a ECB conceptualmente)
                'modo' => 'Implementación básica (sin modo de operación)',

                // RONDAS: Número de iteraciones del algoritmo AES
                // AES-128: 10 rondas, AES-192: 12 rondas, AES-256: 14 rondas
                'rondas' => '10 rondas de transformación',

                // TAMAÑO DE CLAVE: Longitud de la clave de cifrado
                // AES-128: 128 bits (16 bytes), AES-192: 192 bits (24 bytes), AES-256: 256 bits (32 bytes)
                'tamaño_clave' => '128 bits (16 bytes)',

                // TAMAÑO DE BLOQUE: Siempre 128 bits (16 bytes) para todas las variantes AES
                // Este valor es fijo en el estándar AES
                'tamaño_bloque' => '128 bits (16 bytes)',

                // ESQUEMA DE PADDING: Método para completar bloques incompletos
                // Opciones: PKCS7, PKCS5, ANSI X9.23, ISO 10126, Zero Padding
                'padding' => 'PKCS7',

                // FORMATO DE SALIDA: Codificación del texto cifrado
                // Opciones: Hexadecimal, Base64, Binario, Custom
                'formato_salida' => 'Hexadecimal',

                // TRANSFORMACIONES: Operaciones criptográficas aplicadas en cada ronda
                // SubBytes: Sustitución no lineal usando S-Box
                // ShiftRows: Desplazamiento circular de filas
                // MixColumns: Mezcla de columnas (salvo última ronda)
                // AddRoundKey: XOR con clave de ronda
                'transformaciones' => 'SubBytes, ShiftRows, MixColumns, AddRoundKey',

                // INFORMACIÓN TÉCNICA ADICIONAL
                'detalles_tecnicos' => [
                    'sbox' => 'S-Box estándar AES (sustitución Rijndael)',
                    'expansion_clave' => 'KeyExpansion con Rcon para 11 claves de ronda (0-10)',
                    'operaciones_galois' => 'Multiplicación en GF(2^8) para MixColumns',
                    'padding_validacion' => 'Validación estricta de PKCS7 en descifrado'
                ],

                // LIMITACIONES DE SEGURIDAD
                'limitaciones' => [
                    'sin_iv' => 'No utiliza Vector de Inicialización (IV)',
                    'patrones_repetitivos' => 'Bloques idénticos producen cifrado idéntico',
                    'sin_autenticacion' => 'No incluye verificación de integridad (MAC)',
                    'implementacion_academica' => 'No optimizada para resistir ataques de canal lateral'
                ],

                // CASOS DE USO RECOMENDADOS
                'uso_recomendado' => [
                    'educacion' => 'Aprendizaje de conceptos criptográficos',
                    'prototipado' => 'Desarrollo y pruebas iniciales',
                    'demostraciones' => 'Ejemplos académicos y didácticos'
                ],

                // ADVERTENCIA DE SEGURIDAD
                'advertencia' => 'Esta implementación es solo para fines educativos. NO usar en producción.',
                'alternativas_produccion' => 'Para producción usar: OpenSSL, libsodium, o implementaciones certificadas FIPS 140-2'
            ]
        ], 200);
    }
}
