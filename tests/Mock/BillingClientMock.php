<?php

namespace App\Tests\Mock;

use App\Exception\InvalidCredentialsException;
use App\Security\User;
use App\Service\BillingClient;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class BillingClientMock extends BillingClient
{
    private $user = [
        'email' => 'user@mail.ru',
        'password' => '123456',
        'roles' => ['ROLE_USER'],
        'balance' => 1259.99,
    ];
    private $admin = [
        'email' => 'admin@mail.ru',
        'password' => 'password',
        'roles' => ['ROLE_SUPER_ADMIN'],
        'balance' => 99999.99,
    ];


    public function __construct()
    {
        // Вызываем родительский конструктор с фиктивными значениями
        
        echo "\nМокается";
    }




    public function getCourses(): array
    {
        return [
            [
                "code" => "python",
                "type" => 'pay',
                "price" => 400
            ],
            [
                "code" => "c++",
                "type" => 'pay',
                "price" => 123
            ],
            [
                "code" => "basic",
                "type" => 'rent',
                "price" => 800
            ],
            [
                "code" => "Kafka",
                "type" => 'pay',
                "price" => 350.99
            ],
            [
                "code" => "ML",
                "type" => 'free',
                "price" => 0.00
            ],
        ];
    }

   


    public function request(
        string $method = 'GET',
        ?string $url = null,
        array $data = [],
        array $headers = [],
        string $token = ''
    ): array {
        
        switch ($url) {
            case '/api/v1/auth':
                if (isset($data['email'], $data['password']) &&
                    (
                        ($data['email'] === $this->user['email'] && $data['password'] === $this->user['password']) ||
                        ($data['email'] === $this->admin['email'] && $data['password'] === $this->admin['password'])
                    )
                ) {
                    $user = $data['email'] === $this->user['email'] ? $this->user : $this->admin;
                    return [
                        'token' => $this->getMockJwt($user),
                        'refresh_token' => 'mock_refresh_token',
                    ];
                }
                throw new InvalidCredentialsException('Неверные учетные данные');
    
            case '/api/v1/register':
                // Предположим, что регистрация всегда успешна
                $user = [
                    'email' => $data['email'],
                    'roles' => ['ROLE_USER'],
                ];
                return [
                    'token' => $this->getMockJwt($user),
                ];
    
            case '/api/v1/users/current':
                // Accept both base64 and JWT tokens for compatibility
                $jwtParts = explode('.', $token);
                if (count($jwtParts) === 3) {
                    $payload = json_decode(base64_decode($jwtParts[1]), true);
                    if ($payload && isset($payload['email'])) {
                        if ($payload['email'] === $this->user['email']) {
                            return $this->user;
                        }
                        if ($payload['email'] === $this->admin['email']) {
                            return $this->admin;
                        }
                    }
                }
                if ($token === base64_encode($this->user['email'] . ':' . $this->user['password'])) {
                    return $this->user;
                }
                if ($token === base64_encode($this->admin['email'] . ':' . $this->admin['password'])) {
                    return $this->admin;
                }
                throw new AuthenticationException('Неверный токен');
    
            case '/api/v1/courses':
                return $this->getCourses();
    
            case (str_starts_with($url, '/api/v1/transactions')):
                // Возвращаем фиктивные транзакции
                return [
                    [
                        'type' => 'payment',
                        'course_code' => 'python',
                        'expires_at' => null,
                    ],
                    [
                        'type' => 'payment',
                        'course_code' => 'basic',
                        'expires_at' => (new \DateTimeImmutable('+3 days'))->format(DATE_ATOM),
                    ],
                ];
    
            default:
                return ['message' => 'Mocked default response'];
        }
    }

    private function getMockJwt(array $user): string
    {
        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode([
            'email' => $user['email'],
            'roles' => $user['roles'],
            'exp' => time() + 3600,
        ]));
        $signature = 'mocked_signature';
        return $header . '.' . $payload . '.' . $signature;
    }
}
