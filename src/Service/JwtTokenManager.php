<?php

namespace App\Service;

class JwtTokenManager
{
    /**
     * Декодирует JWT-токен и возвращает payload как массив
     */
    public function decode(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new \InvalidArgumentException('Invalid JWT token');
        }
        $payload = $parts[1];
        $decoded = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Unable to decode JWT payload');
        }
        return $decoded;
    }
}
