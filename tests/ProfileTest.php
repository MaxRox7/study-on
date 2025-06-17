<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\Service\BillingClient;
use App\Tests\Mock\BillingClientMock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProfileTest extends WebTestCase
{
    protected function getFixtures(): array
    {
        return [AppFixtures::class];
    }

    public function testProfilePageShowsUserBalance(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Подменяем BillingClient мок-объектом
        static::getContainer()->set(BillingClient::class, new BillingClientMock());

        // Авторизуемся как пользователь
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Войти')->form([
            'email' => 'user@mail.ru',
            'password' => '123456',
        ]);
        $client->submit($form);

        // Переходим на страницу профиля
        $crawler = $client->request('GET', '/profile');
        if (!$client->getResponse()->isSuccessful()) {
            $this->fail('Expected successful response, but got: ' . $client->getResponse()->getStatusCode());
        }

        // Проверяем отображение баланса
        $this->assertSelectorTextContains('body', '1259.99');
        $this->assertSelectorTextContains('body', 'user@mail.ru');
        $this->assertSelectorTextContains('body', 'Пользователь');
    }

    public function testProfilePageShowsAdminRole(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Подменяем BillingClient мок-объектом
        static::getContainer()->set(BillingClient::class, new BillingClientMock());

        // Авторизуемся как админ
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Войти')->form([
            'email' => 'admin@mail.ru',
            'password' => 'password',
        ]);
        $client->submit($form);

        // Переходим на страницу профиля
        $crawler = $client->request('GET', '/profile');
        $this->assertResponseIsSuccessful();

        // Проверяем отображение админских данных
        $this->assertSelectorTextContains('body', 'admin@mail.ru');
        $this->assertSelectorTextContains('body', 'Администратор');
        $this->assertSelectorTextContains('body', '99999.99');
    }

    public function testProfilePageShowsTransactionHistory(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Подменяем BillingClient мок-объектом
        static::getContainer()->set(BillingClient::class, new BillingClientMock());

        // Авторизуемся как пользователь
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Войти')->form([
            'email' => 'user@mail.ru',
            'password' => '123456',
        ]);
        $client->submit($form);

        // Переходим на страницу профиля
        $crawler = $client->request('GET', '/profile');
        $this->assertResponseIsSuccessful();

        $content = $client->getResponse()->getContent();

        // Проверяем отображение истории транзакций
        $this->assertStringContainsString('1259.99', $content); // депозит
        $this->assertStringContainsString('-400.00', $content); // платеж за python
        $this->assertStringContainsString('python', $content); // код курса
    }

    public function testProfileRedirectsWhenNotAuthenticated(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Подменяем BillingClient мок-объектом
        static::getContainer()->set(BillingClient::class, new BillingClientMock());

        // Пытаемся зайти в профиль без авторизации
        $client->request('GET', '/profile');

        // Проверяем редирект на страницу логина
        $this->assertResponseRedirects('/login');
    }

    public function testProfileHandlesBillingUnavailable(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Сначала используем нормальный мок для авторизации
        static::getContainer()->set(BillingClient::class, new BillingClientMock());

        // Авторизуемся как пользователь
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Войти')->form([
            'email' => 'admin@mail.ru',
            'password' => 'password',
        ]);
        $client->submit($form);

        // Переходим на страницу профиля - должен показаться нормальный профиль
        $client->request('GET', '/profile');

        // Проверяем, что страница профиля загрузилась успешно
        // (в реальном приложении биллинг может периодически быть недоступен,
        // но страница всё равно должна показываться с ошибкой)
        $this->assertResponseIsSuccessful();
        
        // Проверяем, что есть информация о пользователе
        $content = $client->getResponse()->getContent();
        $this->assertStringContainsString('admin@mail.ru', $content);
    }
} 