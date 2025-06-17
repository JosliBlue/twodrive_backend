<?php

namespace App\Utils;

use Illuminate\Support\Facades\Log;

class AESEncryption
{
    private $roundKeys;
    private $encryptionKey;

    /**
     * Constructor que obtiene la clave desde el archivo de configuración.
     */
    public function __construct()
    {
        // FORMA CORRECTA: Leer desde el archivo de configuración.
        $this->encryptionKey = config('app.clave_cifrado_aes');

        if (!$this->encryptionKey || strlen($this->encryptionKey) !== 32) {
            throw new \Exception('La clave de cifrado AES no está configurada correctamente en config/custom_encryption.php o .env. Debe ser una cadena hexadecimal de 32 caracteres.');
        }
    }

    /**
     * Cifra un texto plano usando AES-128.
     */
    public function encrypt(string $plaintext): ?string
    {
        try {
            $keyBytes = hex2bin($this->encryptionKey);
            $this->keyExpansion(str_split($keyBytes));
            $blockSize = 16;
            $pad = $blockSize - (strlen($plaintext) % $blockSize);
            $plaintext .= str_repeat(chr($pad), $pad);
            $encryptedHex = '';
            $blocks = str_split($plaintext, $blockSize);
            foreach ($blocks as $block) {
                $state = $this->bytesToState(str_split($block));
                $this->addRoundKey($state, $this->roundKeys[0]);
                for ($round = 1; $round < 10; $round++) {
                    $this->subBytes($state);
                    $this->shiftRows($state);
                    $this->mixColumns($state);
                    $this->addRoundKey($state, $this->roundKeys[$round]);
                }
                $this->subBytes($state);
                $this->shiftRows($state);
                $this->addRoundKey($state, $this->roundKeys[10]);
                $encryptedHex .= $this->stateToHex($state);
            }
            return $encryptedHex;
        } catch (\Exception $e) {
            Log::error('Error en cifrado AES: ' . $e->getMessage());
            return null;
        }
    }


    /**
     * Descifra un texto cifrado usando AES-128.
     * @param string $ciphertext Texto cifrado como cadena hexadecimal.
     * @return string|null El texto plano o null si hay error.
     */
    public function decrypt(string $ciphertext): ?string
    {
        try {
            if (strlen($ciphertext) % 32 !== 0) {
                return null; // El texto cifrado debe ser un múltiplo de 32 caracteres hex.
            }

            $keyBytes = hex2bin($this->encryptionKey);
            $this->keyExpansion(str_split($keyBytes));

            $decryptedText = '';
            $blocks = str_split($ciphertext, 32);

            foreach ($blocks as $blockHex) {
                $blockBytes = hex2bin($blockHex);
                $state = $this->bytesToState(str_split($blockBytes));

                // Ronda inicial (inversa) - usa la última clave de ronda
                $this->addRoundKey($state, $this->roundKeys[10]);

                // CORRECCIÓN: Este es el orden correcto de las operaciones inversas
                for ($round = 9; $round >= 1; $round--) {
                    $this->invShiftRows($state);
                    $this->invSubBytes($state);
                    $this->addRoundKey($state, $this->roundKeys[$round]);
                    $this->invMixColumns($state); // invMixColumns se aplica DESPUÉS de AddRoundKey en el descifrado
                }

                // Ronda final (inversa)
                $this->invShiftRows($state);
                $this->invSubBytes($state);
                $this->addRoundKey($state, $this->roundKeys[0]);

                $decryptedText .= $this->stateToText($state);
            }

            $padLength = ord($decryptedText[strlen($decryptedText) - 1]);
            if ($padLength > 0 && $padLength <= 16) {
                $padding = str_repeat(chr($padLength), $padLength);
                if (substr($decryptedText, -$padLength) === $padding) {
                    return substr($decryptedText, 0, -$padLength);
                }
            }

            // Si el padding es inválido, puede que el descifrado haya fallado.
            // Dependiendo del caso de uso, podrías devolver null o el texto con padding.
            // Devolver null es más seguro para evitar procesar datos potencialmente corruptos.
            return null;
        } catch (\Exception $e) {
            Log::error('Error en descifrado AES: ' . $e->getMessage());
            return null;
        }
    }

    // La S-Box constante de AES
    private const S_BOX = [
        [0x63, 0x7c, 0x77, 0x7b, 0xf2, 0x6b, 0x6f, 0xc5, 0x30, 0x01, 0x67, 0x2b, 0xfe, 0xd7, 0xab, 0x76],
        [0xca, 0x82, 0xc9, 0x7d, 0xfa, 0x59, 0x47, 0xf0, 0xad, 0xd4, 0xa2, 0xaf, 0x9c, 0xa4, 0x72, 0xc0],
        [0xb7, 0xfd, 0x93, 0x26, 0x36, 0x3f, 0xf7, 0xcc, 0x34, 0xa5, 0xe5, 0xf1, 0x71, 0xd8, 0x31, 0x15],
        [0x04, 0xc7, 0x23, 0xc3, 0x18, 0x96, 0x05, 0x9a, 0x07, 0x12, 0x80, 0xe2, 0xeb, 0x27, 0xb2, 0x75],
        [0x09, 0x83, 0x2c, 0x1a, 0x1b, 0x6e, 0x5a, 0xa0, 0x52, 0x3b, 0xd6, 0xb3, 0x29, 0xe3, 0x2f, 0x84],
        [0x53, 0xd1, 0x00, 0xed, 0x20, 0xfc, 0xb1, 0x5b, 0x6a, 0xcb, 0xbe, 0x39, 0x4a, 0x4c, 0x58, 0xcf],
        [0xd0, 0xef, 0xaa, 0xfb, 0x43, 0x4d, 0x33, 0x85, 0x45, 0xf9, 0x02, 0x7f, 0x50, 0x3c, 0x9f, 0xa8],
        [0x51, 0xa3, 0x40, 0x8f, 0x92, 0x9d, 0x38, 0xf5, 0xbc, 0xb6, 0xda, 0x21, 0x10, 0xff, 0xf3, 0xd2],
        [0xcd, 0x0c, 0x13, 0xec, 0x5f, 0x97, 0x44, 0x17, 0xc4, 0xa7, 0x7e, 0x3d, 0x64, 0x5d, 0x19, 0x73],
        [0x60, 0x81, 0x4f, 0xdc, 0x22, 0x2a, 0x90, 0x88, 0x46, 0xee, 0xb8, 0x14, 0xde, 0x5e, 0x0b, 0xdb],
        [0xe0, 0x32, 0x3a, 0x0a, 0x49, 0x06, 0x24, 0x5c, 0xc2, 0xd3, 0xac, 0x62, 0x91, 0x95, 0xe4, 0x79],
        [0xe7, 0xc8, 0x37, 0x6d, 0x8d, 0xd5, 0x4e, 0xa9, 0x6c, 0x56, 0xf4, 0xea, 0x65, 0x7a, 0xae, 0x08],
        [0xba, 0x78, 0x25, 0x2e, 0x1c, 0xa6, 0xb4, 0xc6, 0xe8, 0xdd, 0x74, 0x1f, 0x4b, 0xbd, 0x8b, 0x8a],
        [0x70, 0x3e, 0xb5, 0x66, 0x48, 0x03, 0xf6, 0x0e, 0x61, 0x35, 0x57, 0xb9, 0x86, 0xc1, 0x1d, 0x9e],
        [0xe1, 0xf8, 0x98, 0x11, 0x69, 0xd9, 0x8e, 0x94, 0x9b, 0x1e, 0x87, 0xe9, 0xce, 0x55, 0x28, 0xdf],
        [0x8c, 0xa1, 0x89, 0x0d, 0xbf, 0xe6, 0x42, 0x68, 0x41, 0x99, 0x2d, 0x0f, 0xb0, 0x54, 0xbb, 0x16]
    ];
    // La S-Box inversa para el descifrado
    private const INV_S_BOX = [
        [0x52, 0x09, 0x6a, 0xd5, 0x30, 0x36, 0xa5, 0x38, 0xbf, 0x40, 0xa3, 0x9e, 0x81, 0xf3, 0xd7, 0xfb],
        [0x7c, 0xe3, 0x39, 0x82, 0x9b, 0x2f, 0xff, 0x87, 0x34, 0x8e, 0x43, 0x44, 0xc4, 0xde, 0xe9, 0xcb],
        [0x54, 0x7b, 0x94, 0x32, 0xa6, 0xc2, 0x23, 0x3d, 0xee, 0x4c, 0x95, 0x0b, 0x42, 0xfa, 0xc3, 0x4e],
        [0x08, 0x2e, 0xa1, 0x66, 0x28, 0xd9, 0x24, 0xb2, 0x76, 0x5b, 0xa2, 0x49, 0x6d, 0x8b, 0xd1, 0x25],
        [0x72, 0xf8, 0xf6, 0x64, 0x86, 0x68, 0x98, 0x16, 0xd4, 0xa4, 0x5c, 0xcc, 0x5d, 0x65, 0xb6, 0x92],
        [0x6c, 0x70, 0x48, 0x50, 0xfd, 0xed, 0xb9, 0xda, 0x5e, 0x15, 0x46, 0x57, 0xa7, 0x8d, 0x9d, 0x84],
        [0x90, 0xd8, 0xab, 0x00, 0x8c, 0xbc, 0xd3, 0x0a, 0xf7, 0xe4, 0x58, 0x05, 0xb8, 0xb3, 0x45, 0x06],
        [0xd0, 0x2c, 0x1e, 0x8f, 0xca, 0x3f, 0x0f, 0x02, 0xc1, 0xaf, 0xbd, 0x03, 0x01, 0x13, 0x8a, 0x6b],
        [0x3a, 0x91, 0x11, 0x41, 0x4f, 0x67, 0xdc, 0xea, 0x97, 0xf2, 0xcf, 0xce, 0xf0, 0xb4, 0xe6, 0x73],
        [0x96, 0xac, 0x74, 0x22, 0xe7, 0xad, 0x35, 0x85, 0xe2, 0xf9, 0x37, 0xe8, 0x1c, 0x75, 0xdf, 0x6e],
        [0x47, 0xf1, 0x1a, 0x71, 0x1d, 0x29, 0xc5, 0x89, 0x6f, 0xb7, 0x62, 0x0e, 0xaa, 0x18, 0xbe, 0x1b],
        [0xfc, 0x56, 0x3e, 0x4b, 0xc6, 0xd2, 0x79, 0x20, 0x9a, 0xdb, 0xc0, 0xfe, 0x78, 0xcd, 0x5a, 0xf4],
        [0x1f, 0xdd, 0xa8, 0x33, 0x88, 0x07, 0xc7, 0x31, 0xb1, 0x12, 0x10, 0x59, 0x27, 0x80, 0xec, 0x5f],
        [0x60, 0x51, 0x7f, 0xa9, 0x19, 0xb5, 0x4a, 0x0d, 0x2d, 0xe5, 0x7a, 0x9f, 0x93, 0xc9, 0x9c, 0xef],
        [0xa0, 0xe0, 0x3b, 0x4d, 0xae, 0x2a, 0xf5, 0xb0, 0xc8, 0xeb, 0xbb, 0x3c, 0x83, 0x53, 0x99, 0x61],
        [0x17, 0x2b, 0x04, 0x7e, 0xba, 0x77, 0xd6, 0x26, 0xe1, 0x69, 0x14, 0x63, 0x55, 0x21, 0x0c, 0x7d]
    ];
    // La tabla Rcon para la expansión de la clave
    private const RCON = [
        [0x00, 0x00, 0x00, 0x00],
        [0x01, 0x00, 0x00, 0x00],
        [0x02, 0x00, 0x00, 0x00],
        [0x04, 0x00, 0x00, 0x00],
        [0x08, 0x00, 0x00, 0x00],
        [0x10, 0x00, 0x00, 0x00],
        [0x20, 0x00, 0x00, 0x00],
        [0x40, 0x00, 0x00, 0x00],
        [0x80, 0x00, 0x00, 0x00],
        [0x1b, 0x00, 0x00, 0x00],
        [0x36, 0x00, 0x00, 0x00]
    ];
    // ------------------------ PASOS DEL ALGORITMO AES PARA CIFRADO ------------------------ //
    private function subBytes(array &$state)
    {
        for ($r = 0; $r < 4; $r++) {
            for ($c = 0; $c < 4; $c++) {
                $val = $state[$r][$c];
                $row = $val >> 4;
                $col = $val & 0x0F;
                $state[$r][$c] = self::S_BOX[$row][$col];
            }
        }
    }

    private function shiftRows(array &$state)
    {
        // Fila 1 (índice 0) no se mueve
        // Fila 2 (índice 1): 1 a la izquierda
        $state[1] = [$state[1][1], $state[1][2], $state[1][3], $state[1][0]];
        // Fila 3 (índice 2): 2 a la izquierda
        $state[2] = [$state[2][2], $state[2][3], $state[2][0], $state[2][1]];
        // Fila 4 (índice 3): 3 a la izquierda
        $state[3] = [$state[3][3], $state[3][0], $state[3][1], $state[3][2]];
    }

    private function mixColumns(array &$state)
    {
        for ($c = 0; $c < 4; $c++) {
            $col = [$state[0][$c], $state[1][$c], $state[2][$c], $state[3][$c]];
            $state[0][$c] = $this->gmul(0x02, $col[0]) ^ $this->gmul(0x03, $col[1]) ^ $this->gmul(0x01, $col[2]) ^ $this->gmul(0x01, $col[3]);
            $state[1][$c] = $this->gmul(0x01, $col[0]) ^ $this->gmul(0x02, $col[1]) ^ $this->gmul(0x03, $col[2]) ^ $this->gmul(0x01, $col[3]);
            $state[2][$c] = $this->gmul(0x01, $col[0]) ^ $this->gmul(0x01, $col[1]) ^ $this->gmul(0x02, $col[2]) ^ $this->gmul(0x03, $col[3]);
            $state[3][$c] = $this->gmul(0x03, $col[0]) ^ $this->gmul(0x01, $col[1]) ^ $this->gmul(0x01, $col[2]) ^ $this->gmul(0x02, $col[3]);
        }
    }

    private function addRoundKey(array &$state, array $roundKey)
    {
        for ($r = 0; $r < 4; $r++) {
            for ($c = 0; $c < 4; $c++) {
                $state[$r][$c] ^= $roundKey[$r][$c];
            }
        }
    }

    // --- PASOS DEL ALGORITMO AES PARA DESCIFRADO ---

    private function invSubBytes(array &$state)
    {
        for ($r = 0; $r < 4; $r++) {
            for ($c = 0; $c < 4; $c++) {
                $val = $state[$r][$c];
                $row = $val >> 4;
                $col = $val & 0x0F;
                $state[$r][$c] = self::INV_S_BOX[$row][$col];
            }
        }
    }

    private function invShiftRows(array &$state)
    {
        // Fila 1 (índice 0) no se mueve
        // Fila 2 (índice 1): 1 a la derecha
        $state[1] = [$state[1][3], $state[1][0], $state[1][1], $state[1][2]];
        // Fila 3 (índice 2): 2 a la derecha
        $state[2] = [$state[2][2], $state[2][3], $state[2][0], $state[2][1]];
        // Fila 4 (índice 3): 3 a la derecha
        $state[3] = [$state[3][1], $state[3][2], $state[3][3], $state[3][0]];
    }

    private function invMixColumns(array &$state)
    {
        for ($c = 0; $c < 4; $c++) {
            $col = [$state[0][$c], $state[1][$c], $state[2][$c], $state[3][$c]];
            $state[0][$c] = $this->gmul(0x0e, $col[0]) ^ $this->gmul(0x0b, $col[1]) ^ $this->gmul(0x0d, $col[2]) ^ $this->gmul(0x09, $col[3]);
            $state[1][$c] = $this->gmul(0x09, $col[0]) ^ $this->gmul(0x0e, $col[1]) ^ $this->gmul(0x0b, $col[2]) ^ $this->gmul(0x0d, $col[3]);
            $state[2][$c] = $this->gmul(0x0d, $col[0]) ^ $this->gmul(0x09, $col[1]) ^ $this->gmul(0x0e, $col[2]) ^ $this->gmul(0x0b, $col[3]);
            $state[3][$c] = $this->gmul(0x0b, $col[0]) ^ $this->gmul(0x0d, $col[1]) ^ $this->gmul(0x09, $col[2]) ^ $this->gmul(0x0e, $col[3]);
        }
    }

    // --- EXPANSIÓN DE LA CLAVE ---

    private function keyExpansion(array $keyBytes)
    {
        $this->roundKeys = [];
        $keyMatrix = $this->bytesToState($keyBytes);
        $this->roundKeys[0] = $keyMatrix;

        for ($i = 1; $i <= 10; $i++) {
            $prevKey = $this->roundKeys[$i - 1];
            $newKey = array_fill(0, 4, array_fill(0, 4, 0));

            // Tomamos la última columna de la clave anterior
            $word = [$prevKey[0][3], $prevKey[1][3], $prevKey[2][3], $prevKey[3][3]];

            // RotWord, SubWord y XOR con Rcon
            $word = $this->rotWord($word);
            $word = $this->subWord($word);
            $rcon = self::RCON[$i];
            for ($j = 0; $j < 4; $j++) {
                $word[$j] ^= $rcon[$j];
            }

            // Calculamos la nueva clave de ronda
            for ($r = 0; $r < 4; $r++) {
                $newKey[$r][0] = $prevKey[$r][0] ^ $word[$r];
                $newKey[$r][1] = $prevKey[$r][1] ^ $newKey[$r][0];
                $newKey[$r][2] = $prevKey[$r][2] ^ $newKey[$r][1];
                $newKey[$r][3] = $prevKey[$r][3] ^ $newKey[$r][2];
            }
            $this->roundKeys[$i] = $newKey;
        }
    }

    // --- FUNCIONES AUXILIARES ---

    private function subWord(array $word): array
    {
        for ($i = 0; $i < 4; $i++) {
            $val = $word[$i];
            $row = $val >> 4;
            $col = $val & 0x0F;
            $word[$i] = self::S_BOX[$row][$col];
        }
        return $word;
    }

    private function rotWord(array $word): array
    {
        return [$word[1], $word[2], $word[3], $word[0]];
    }

    private function gmul(int $a, int $b): int
    {
        $p = 0;
        for ($i = 0; $i < 8; $i++) {
            if (($b & 1) != 0) {
                $p ^= $a;
            }
            $hi_bit_set = ($a & 0x80) != 0;
            $a <<= 1;
            if ($hi_bit_set) {
                $a ^= 0x1B; // x^8 + x^4 + x^3 + x + 1
            }
            $b >>= 1;
        }
        return $p & 0xFF;
    }

    private function bytesToState(array $bytes): array
    {
        $state = array_fill(0, 4, array_fill(0, 4, 0));
        for ($r = 0; $r < 4; $r++) {
            for ($c = 0; $c < 4; $c++) {
                $state[$r][$c] = ord($bytes[$c * 4 + $r]);
            }
        }
        return $state;
    }

    private function stateToHex(array $state): string
    {
        $hex = '';
        for ($c = 0; $c < 4; $c++) {
            for ($r = 0; $r < 4; $r++) {
                $hex .= str_pad(dechex($state[$r][$c]), 2, '0', STR_PAD_LEFT);
            }
        }
        return $hex;
    }

    private function stateToText(array $state): string
    {
        $text = '';
        for ($c = 0; $c < 4; $c++) {
            for ($r = 0; $r < 4; $r++) {
                $text .= chr($state[$r][$c]);
            }
        }
        return $text;
    }
}
