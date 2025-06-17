<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\Entity\Course;
use App\Entity\Lesson;
use App\Service\BillingClient;
use App\Tests\Mock\BillingClientMock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LessonAccessTest extends WebTestCase
{
    protected function getFixtures(): array
    {
        return [AppFixtures::class];
    }

    public function testUserCanAccessLessonOfPurchasedCourse(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Подменяем BillingClient мок-объектом
        static::getContainer()->set(BillingClient::class, new BillingClientMock());

        // Авторизуемся как пользователь, который купил курс python
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Войти')->form([
            'email' => 'user@mail.ru',
            'password' => '123456',
        ]);
        $client->submit($form);

        // Получаем курс и урок из БД
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $course = $entityManager->getRepository(Course::class)->findOneBy([]);

        if ($course && $course->getLessons()->count() > 0) {
            $lesson = $course->getLessons()->first();
            
            // Обновляем символьный код курса на тот, который купил пользователь
            $course->setSymbolCode('python');
            $entityManager->flush();
            
            // Пытаемся получить доступ к уроку
            $client->request('GET', '/lesson/' . $lesson->getIdLesson());
            
            // Проверяем, что доступ разрешен (статус 200)
            if (!$client->getResponse()->isSuccessful()) {
                $this->fail('Expected successful response, but got: ' . $client->getResponse()->getStatusCode());
            }
            $this->assertSelectorTextContains('h1', $lesson->getTitleLesson());
        } else {
            $this->markTestSkipped('Нет курсов или уроков в базе данных');
        }
    }

    public function testUserCannotAccessLessonOfUnpurchasedCourse(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Подменяем BillingClient мок-объектом
        static::getContainer()->set(BillingClient::class, new BillingClientMock());

        // Авторизуемся как пользователь, который НЕ покупал курс c++
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Войти')->form([
            'email' => 'user@mail.ru',
            'password' => '123456',
        ]);
        $client->submit($form);

        // Получаем курс и урок из БД
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $course = $entityManager->getRepository(Course::class)->findOneBy([]);

        if ($course && $course->getLessons()->count() > 0) {
            $lesson = $course->getLessons()->first();
            
            // Обновляем символьный код курса на тот, который НЕ покупал пользователь
            $course->setSymbolCode('c++');
            $entityManager->flush();
            
            // Пытаемся получить доступ к уроку неоплаченного курса
            $client->request('GET', '/lesson/' . $lesson->getIdLesson());
            
            // Проверяем, что доступ запрещен (должно быть исключение AccessDenied)
            $this->assertResponseStatusCodeSame(403);
        } else {
            $this->markTestSkipped('Нет курсов или уроков в базе данных');
        }
    }

    public function testUserCanAccessRentedCourseBeforeExpiry(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Подменяем BillingClient мок-объектом  
        static::getContainer()->set(BillingClient::class, new BillingClientMock());

        // Авторизуемся как пользователь rich, который арендовал курс basic
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Войти')->form([
            'email' => 'rich@example.com',
            'password' => 'password',
        ]);
        $client->submit($form);

        // Получаем курс и урок из БД
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $course = $entityManager->getRepository(Course::class)->findOneBy([]);

        if ($course && $course->getLessons()->count() > 0) {
            $lesson = $course->getLessons()->first();
            
            // Обновляем символьный код курса на арендованный
            $course->setSymbolCode('basic');
            $entityManager->flush();
            
            // Пытаемся получить доступ к уроку арендованного курса
            $client->request('GET', '/lesson/' . $lesson->getIdLesson());
            
            // Проверяем, что доступ разрешен (аренда еще активна)
            if (!$client->getResponse()->isSuccessful()) {
                $this->fail('Expected successful response, but got: ' . $client->getResponse()->getStatusCode());
            }
            $this->assertSelectorTextContains('h1', $lesson->getTitleLesson());
        } else {
            $this->markTestSkipped('Нет курсов или уроков в базе данных');
        }
    }

    public function testAdminCanAccessAnyLesson(): void
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

        // Получаем курс и урок из БД
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $course = $entityManager->getRepository(Course::class)->findOneBy([]);

        if ($course && $course->getLessons()->count() > 0) {
            $lesson = $course->getLessons()->first();
            
            // Устанавливаем любой курс (админ должен иметь доступ ко всем)
            $course->setSymbolCode('any-course');
            $entityManager->flush();
            
            // Пытаемся получить доступ к уроку
            $client->request('GET', '/lesson/' . $lesson->getIdLesson());
            
            // Проверяем, что админ имеет доступ к любому уроку
            if (!$client->getResponse()->isSuccessful()) {
                $this->fail('Expected successful response, but got: ' . $client->getResponse()->getStatusCode());
            }
            $this->assertSelectorTextContains('h1', $lesson->getTitleLesson());
        } else {
            $this->markTestSkipped('Нет курсов или уроков в базе данных');
        }
    }

    public function testUnauthenticatedUserCannotAccessLessons(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Подменяем BillingClient мок-объектом
        static::getContainer()->set(BillingClient::class, new BillingClientMock());

        // Получаем урок из БД
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $lesson = $entityManager->getRepository(Lesson::class)->findOneBy([]);

        if ($lesson) {
            // Пытаемся получить доступ к уроку без авторизации
            $client->request('GET', '/lesson/' . $lesson->getIdLesson());
            
            // Должен быть редирект на страницу логина
            $this->assertResponseRedirects('/login');
        } else {
            $this->markTestSkipped('Нет уроков в базе данных');
        }
    }
} 