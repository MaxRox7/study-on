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
                if (isset($data['username'], $data['password']) &&
                    (
                        ($data['username'] === $this->user['email'] && $data['password'] === $this->user['password']) ||
                        ($data['username'] === $this->admin['email'] && $data['password'] === $this->admin['password'])
                    )
                ) {
                    return [
                        'token' => base64_encode($data['username'] . ':' . $data['password']),
                    ];
                }
                throw new InvalidCredentialsException('Неверные учетные данные');
    
            case '/api/v1/register':
                // Предположим, что регистрация всегда успешна
                return [
                    'token' => base64_encode($data['email'] . ':' . $data['password']),
                ];
    
            case '/api/v1/users/current':
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
}
