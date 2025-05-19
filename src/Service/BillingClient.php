<?php
namespace App\Service;

use App\Exception\BillingUnavailableException;
use Psr\Log\LoggerInterface; // Импортируем правильный логгер

class BillingClient
{
    private string $billingUrl;
    private LoggerInterface $logger;

    public function __construct(
        string $billingUrl,
        LoggerInterface $logger // Внедрение логгера для отслеживания ошибок
    ) {
        $this->billingUrl = $billingUrl;
        $this->logger = $logger; // Инициализация логгера
    }

    /**
     * Отправка HTTP-запроса через cURL.
     *
     * @throws BillingUnavailableException
     * @throws \Exception
     */
    public function request(
        string $method = 'GET',
        string $url = null,
        array $data = [],
        array $headers = [],
        string $token = ''
    ): array {
        // Логирование перед отправкой запроса
        $this->logger->info('Sending request to: ' . $this->billingUrl . $url);
        $this->logger->info('Request Data: ' . json_encode($data));

        // Установка заголовков
        $headers[] = 'Authorization: Bearer ' . $token;
        $headers[] = 'Content-Type: application/json';

        $curlOptions = [
            CURLOPT_URL => $this->billingUrl . $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($method === 'POST') {
            $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data, JSON_UNESCAPED_UNICODE);
        }

        $curl = curl_init();

        try {
            // Выполнение cURL-запроса
            curl_setopt_array($curl, $curlOptions);
            $response = curl_exec($curl);

            // Если запрос не удался
            if ($response === false) {
                $this->logger->error('cURL error: ' . curl_error($curl));
                throw new BillingUnavailableException('Ошибка при выполнении запроса cURL: ' . curl_error($curl), curl_errno($curl));
            }
        } catch (\Exception $exception) {
            curl_close($curl); // Закрытие соединения в случае исключения
            $this->logger->error('Exception during request: ' . $exception->getMessage());
            throw new \Exception('Ошибка на стороне сервера');
        } finally {
            curl_close($curl); // Обязательно закрываем cURL-соединение
        }

        // Декодирование ответа
        $decodedResponse = json_decode($response, true);

        // Если сервер вернул пустой ответ или некорректный JSON
        if ($decodedResponse === null) {
            $this->logger->error('Invalid response from server: ' . $response);
            throw new BillingUnavailableException('Сервис вернул некорректный ответ.');
        }

        return $decodedResponse;
    }

    /**
     * Аутентификация пользователя.
     */
    public function auth(array $data): array
    {
        return $this->request(
            method: 'POST',
            url: '/api/v1/auth',
            data: $data
        );
    }

    /**
     * Регистрация пользователя.
     */
    public function register(array $data): array
    {
        return $this->request(
            method: 'POST',
            url: '/api/v1/register',
            data: $data
        );
    }

    /**
     * Получение данных текущего пользователя.
     */
    public function getCurrentUser(string $token): array
    {
        return $this->request(
            url: '/api/v1/users/current',
            token: $token
        );
    }

    /**
     * Получение списка курсов из billing
     */
    public function getCourses(): array
    {
        return $this->request(
            method: 'GET',
            url: '/api/v1/courses'
        );
    }

    /**
     * Получение истории транзакций пользователя
     */
    public function getTransactions(string $token, array $filters = []): array
    {
        $query = http_build_query(['filter' => $filters]);
        $url = '/api/v1/transactions' . ($query ? ('?' . $query) : '');
        return $this->request(
            url: $url,
            token: $token
        );
    }

    /**
     * Проверяет, покупал ли пользователь курс (или аренда не истекла)
     * Возвращает массив: ['paid' => bool, 'expires_at' => ?string]
     */
    public function hasUserPaidCourse(string $token, string $courseCode): array
    {
        $transactions = $this->getTransactions($token, ['type' => 'payment', 'course_code' => $courseCode]);
        foreach ($transactions as $tr) {
            if ($tr['type'] === 'payment') {
                if (empty($tr['expires_at'])) {
                    return ['paid' => true, 'expires_at' => null]; // Куплен навсегда
                }
                if (isset($tr['expires_at']) && $tr['expires_at'] > (new \DateTimeImmutable())->format(DATE_ATOM)) {
                    return ['paid' => true, 'expires_at' => $tr['expires_at']]; // Аренда активна
                }
            }
        }
        return ['paid' => false, 'expires_at' => null];
    }
}
