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
    private $users = [
        'user@mail.ru' => [
            'email' => 'user@mail.ru',
            'password' => '123456',
            'roles' => ['ROLE_USER'],
            'balance' => 1259.99,
        ],
        'admin@mail.ru' => [
            'email' => 'admin@mail.ru',
            'password' => 'password',
            'roles' => ['ROLE_SUPER_ADMIN'],
            'balance' => 99999.99,
        ],
        'test@example.com' => [
            'email' => 'test@example.com',
            'password' => 'password',
            'roles' => ['ROLE_USER'],
            'balance' => 50.00,
        ],
        'rich@example.com' => [
            'email' => 'rich@example.com',
            'password' => 'password',
            'roles' => ['ROLE_USER'],
            'balance' => 10000.00,
        ],
    ];

    private $courses = [
        'python' => [
            'code' => 'python',
            'type' => 'buy',
            'price' => '400.00'
        ],
        'c++' => [
            'code' => 'c++',
            'type' => 'buy',
            'price' => '123.00'
        ],
        'basic' => [
            'code' => 'basic',
            'type' => 'rent',
            'price' => '800.00'
        ],
        'Kafka' => [
            'code' => 'Kafka',
            'type' => 'buy',
            'price' => '350.99'
        ],
        'ML' => [
            'code' => 'ML',
            'type' => 'free',
            'price' => null
        ],
    ];

    private $transactions = [
        'user@mail.ru' => [
            [
                'id' => 1,
                'created_at' => '2024-01-01T10:00:00Z',
                'type' => 'deposit',
                'amount' => '1259.99',
                'course_code' => null,
            ],
            [
                'id' => 2,
                'created_at' => '2024-01-10T10:00:00Z',
                'type' => 'payment',
                'amount' => '-400.00',
                'course_code' => 'python',
                'expires_at' => null,
            ]
        ],
        'admin@mail.ru' => [
            [
                'id' => 3,
                'created_at' => '2024-01-01T10:00:00Z',
                'type' => 'deposit',
                'amount' => '99999.99',
                'course_code' => null,
            ],
        ],
        'rich@example.com' => [
            [
                'id' => 4,
                'created_at' => '2024-01-01T10:00:00Z',
                'type' => 'deposit',
                'amount' => '10000.00',
                'course_code' => null,
            ],
            [
                'id' => 5,
                'created_at' => '2024-01-15T10:00:00Z',
                'type' => 'payment',
                'amount' => '-800.00',
                'course_code' => 'basic',
                'expires_at' => '2025-12-31T23:59:59+00:00', // фиксированная дата в будущем
            ]
        ],
    ];

    public function __construct()
    {
        // Мок конструктор - не вызываем родительский
    }

    public function getCourses(): array
    {
        return array_values($this->courses);
    }

    /**
     * Аутентификация пользователя
     */
    public function auth(array $data): array
    {
        return $this->handleAuth($data);
    }

    /**
     * Регистрация пользователя
     */
    public function register(array $data): array
    {
        return $this->handleRegister($data);
    }

    /**
     * Получение данных текущего пользователя
     */
    public function getCurrentUser(string $token): array
    {
        return $this->handleCurrentUser($token);
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
                return $this->handleAuth($data);
                
            case '/api/v1/register':
                return $this->handleRegister($data);
                
            case '/api/v1/users/current':
                return $this->handleCurrentUser($token);
                
            case '/api/v1/courses':
                return $this->getCourses();
                
            case (str_starts_with($url, '/api/v1/courses/') && str_ends_with($url, '/pay')):
                return $this->handleCoursePay($url, $token);
                
            case (str_starts_with($url, '/api/v1/courses/')):
                return $this->handleCourseGet($url);
                
            case (str_starts_with($url, '/api/v1/transactions')):
                return $this->handleTransactions($url, $token);
                
            case '/api/v1/deposit':
                return $this->handleDeposit($data, $token);
                
            default:
                return ['message' => 'Mocked default response'];
        }
    }

    private function handleAuth(array $data): array
    {
        if (!isset($data['email'], $data['password'])) {
            throw new InvalidCredentialsException('Неверные учетные данные');
        }

        $user = $this->users[$data['email']] ?? null;
        if (!$user || $user['password'] !== $data['password']) {
            throw new InvalidCredentialsException('Неверные учетные данные');
        }

        return [
            'token' => $this->getMockJwt($user),
            'refresh_token' => 'mock_refresh_token',
        ];
    }

    private function handleRegister(array $data): array
    {
        // Добавляем нового пользователя в массив пользователей
        $this->users[$data['email']] = [
            'email' => $data['email'],
            'password' => $data['password'],
            'roles' => ['ROLE_USER'],
            'balance' => 0.00,
        ];

        return [
            'status' => 'ok',
            'email' => $data['email'],
        ];
    }

    private function handleCurrentUser(string $token): array
    {
        $user = $this->getUserFromToken($token);
        return $user;
    }

    private function handleCoursePay(string $url, string $token): array
    {
        preg_match('/\/api\/v1\/courses\/([^\/]+)\/pay/', $url, $matches);
        $courseCode = $matches[1] ?? null;
        
        if (!$courseCode || !isset($this->courses[$courseCode])) {
            throw new \Exception('Курс не найден');
        }

        $user = $this->getUserFromToken($token);
        $course = $this->courses[$courseCode];
        
        if ($course['type'] === 'free') {
            return [
                'success' => true,
                'course_type' => 'free',
                'expires_at' => null,
            ];
        }

        $price = (float)$course['price'];
        if ($user['balance'] < $price) {
            throw new \Exception('Недостаточно средств на балансе');
        }

        // Обновляем баланс пользователя
        $this->users[$user['email']]['balance'] -= $price;

        $expiresAt = null;
        if ($course['type'] === 'rent') {
            $expiresAt = (new \DateTimeImmutable('+30 days'))->format(DATE_ATOM);
        }

        return [
            'success' => true,
            'course_type' => $course['type'],
            'expires_at' => $expiresAt,
        ];
    }

    private function handleCourseGet(string $url): array
    {
        preg_match('/\/api\/v1\/courses\/([^\/]+)/', $url, $matches);
        $courseCode = $matches[1] ?? null;
        
        if (!$courseCode || !isset($this->courses[$courseCode])) {
            return ['code' => 404, 'message' => 'Курс не найден'];
        }

        return $this->courses[$courseCode];
    }

    private function handleTransactions(string $url, string $token): array
    {
        $user = $this->getUserFromToken($token);
        $transactions = $this->transactions[$user['email']] ?? [];
        
        // Парсим параметры запроса
        $urlParts = parse_url($url);
        $filters = [];
        if (isset($urlParts['query'])) {
            parse_str($urlParts['query'], $queryParams);
            $filters = $queryParams['filter'] ?? [];
        }
        
        // Применяем фильтры
        if (!empty($filters)) {
            $transactions = array_filter($transactions, function($tr) use ($filters) {
                // Фильтр по типу
                if (isset($filters['type'])) {
                    if ($filters['type'] === 'payment' && $tr['type'] !== 'payment') {
                        return false;
                    }
                    if ($filters['type'] === 'deposit' && $tr['type'] !== 'deposit') {
                        return false;
                    }
                }
                
                // Фильтр по коду курса
                if (isset($filters['course_code'])) {
                    if (!isset($tr['course_code']) || $tr['course_code'] !== $filters['course_code']) {
                        return false;
                    }
                }
                
                return true;
            });
        }
        
        return array_values($transactions);
    }

    private function handleDeposit(array $data, string $token): array
    {
        $user = $this->getUserFromToken($token);
        $amount = $data['amount'] ?? 0;
        
        if ($amount <= 0) {
            return ['code' => 400, 'message' => 'Некорректная сумма'];
        }

        // Обновляем баланс пользователя
        $this->users[$user['email']]['balance'] += $amount;

        return [
            'success' => true,
            'balance' => $this->users[$user['email']]['balance'],
        ];
    }

    private function getUserFromToken(string $token): array
    {
        // Декодируем JWT токен
        $jwtParts = explode('.', $token);
        if (count($jwtParts) === 3) {
            $payload = json_decode(base64_decode($jwtParts[1]), true);
            if ($payload && isset($payload['email'])) {
                $user = $this->users[$payload['email']] ?? null;
                if ($user) {
                    return $user;
                }
            }
        }
        
        throw new AuthenticationException('Неверный токен');
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

    /**
     * Получение истории транзакций пользователя
     */
    public function getTransactions(string $token, array $filters = []): array
    {
        $user = $this->getUserFromToken($token);
        $transactions = $this->transactions[$user['email']] ?? [];
        
        // Применяем фильтры
        if (!empty($filters)) {
            $transactions = array_filter($transactions, function($tr) use ($filters) {
                // Фильтр по типу
                if (isset($filters['type']) && $tr['type'] !== $filters['type']) {
                    return false;
                }
                
                // Фильтр по коду курса
                if (isset($filters['course_code'])) {
                    if (!isset($tr['course_code']) || $tr['course_code'] !== $filters['course_code']) {
                        return false;
                    }
                }
                
                return true;
            });
        }
        
        return array_values($transactions);
    }

    public function hasUserPaidCourse(string $token, string $courseCode): array
    {
        try {
            $user = $this->getUserFromToken($token);
            $transactions = $this->transactions[$user['email']] ?? [];
            
            foreach ($transactions as $tr) {
                if ($tr['type'] === 'payment' && $tr['course_code'] === $courseCode) {
                    if (empty($tr['expires_at'])) {
                        return ['paid' => true, 'expires_at' => null]; // Куплен навсегда
                    }
                    if ($tr['expires_at'] && $tr['expires_at'] > (new \DateTimeImmutable())->format(DATE_ATOM)) {
                        return ['paid' => true, 'expires_at' => $tr['expires_at']]; // Аренда активна
                    }
                }
            }
        } catch (\Exception $e) {
            // Игнорируем ошибки аутентификации
        }
        
        return ['paid' => false, 'expires_at' => null];
    }
}
