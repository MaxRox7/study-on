<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\Service\BillingClient;
use App\Tests\Mock\BillingClientMock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CoursePaymentTest extends WebTestCase
{
    protected function getFixtures(): array
    {
        return [AppFixtures::class];
    }

    public function testPayForCourseSuccess(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Подменяем BillingClient мок-объектом
        static::getContainer()->set(BillingClient::class, new BillingClientMock());

        // Авторизуемся как пользователь с достаточным балансом
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Войти')->form([
            'email' => 'user@mail.ru',
            'password' => '123456',
        ]);
        $client->submit($form);

        // Имитируем POST-запрос оплаты курса python (стоит 400, у пользователя баланс 1259.99)
        $client->request('POST', '/courses/python/pay');

        // Проверяем успешный редирект
        $this->assertResponseRedirects();
        $client->followRedirect();

        // Проверяем наличие сообщения об успехе
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.alert-success');
    }

    public function testPayForCourseInsufficientFunds(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Подменяем BillingClient мок-объектом
        $billingMock = new BillingClientMock();
        static::getContainer()->set(BillingClient::class, $billingMock);

        // Авторизуемся как пользователь с недостаточным балансом
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Войти')->form([
            'email' => 'user@mail.ru',
            'password' => '123456',
        ]);
        $client->submit($form);

        // Пытаемся несколько раз оплатить курс, чтобы потратить все деньги
        $client->request('POST', '/courses/python/pay'); // -400
        $client->request('POST', '/courses/basic/pay'); // -800 
        $client->request('POST', '/courses/python/pay'); // снова -400, но уже должно не хватить

        // Проверяем редирект
        $this->assertResponseRedirects();
        $client->followRedirect();

        // Проверяем наличие сообщения об ошибке
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.alert-danger');
    }

    public function testPayForCourseWithoutAuth(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Подменяем BillingClient мок-объектом
        static::getContainer()->set(BillingClient::class, new BillingClientMock());

        // Пытаемся оплатить курс без авторизации
        $client->request('POST', '/courses/python/pay');

        // Проверяем редирект на страницу курсов с сообщением об ошибке
        $this->assertResponseRedirects('/courses');
        $client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.alert-danger');
    }

    public function testPayForFreeCourse(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Подменяем BillingClient мок-объектом
        static::getContainer()->set(BillingClient::class, new BillingClientMock());

        // Авторизуемся как обычный пользователь
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Войти')->form([
            'email' => 'user@mail.ru',
            'password' => '123456',
        ]);
        $client->submit($form);

        // Имитируем POST-запрос оплаты бесплатного курса ML
        $client->request('POST', '/courses/ML/pay');

        // Проверяем успешный редирект
        $this->assertResponseRedirects();
        $client->followRedirect();

        // Проверяем наличие сообщения об успехе
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.alert-success');
    }

    public function testPayForNonExistentCourse(): void
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

        // Пытаемся оплатить несуществующий курс
        $client->request('POST', '/courses/NONEXISTENT/pay');

        // Проверяем редирект и сообщение об ошибке
        $this->assertResponseRedirects();
        $client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.alert-danger');
    }
} 