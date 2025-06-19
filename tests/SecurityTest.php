<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\Entity\Course;
use App\Entity\Lesson;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Tests\Mock\BillingClientMock;
use App\Service\BillingClient;

class SecurityTest extends WebTestCase
{
    protected function getFixtures(): array
    {
        return [AppFixtures::class];
    }
    
    public function testLoginAndCourseListWithMockBillingClient(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Подменяем BillingClient МОКом
        static::getContainer()->set(BillingClient::class, new BillingClientMock());

        // Отправляем запрос на логин
        $crawler = $client->request('GET', '/login');

        // Отправляем форму логина с правильными данными из MOCK
        $form = $crawler->selectButton('Войти')->form([
            'email' => 'user@mail.ru',
            'password' => '123456',
        ]);

        $client->submit($form);

        // Проверяем, что редирект сработал (например, на /courses)
        $this->assertResponseRedirects('/courses');

        // Переходим по редиректу
        $client->followRedirect();
        // echo $client->getResponse()->getContent(); // Добавьте это для отладки
        // Проверка страницы курсов
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Все курсы');
    }

    public function testUnauthorizedUserCannotAccessLessons(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Подменяем BillingClient МОКом
        static::getContainer()->set(BillingClient::class, new BillingClientMock());

        // Получаем EntityManager из контейнера
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        // Находим любой урок
        $lesson = $entityManager->getRepository(Lesson::class)->findOneBy([]);
        $this->assertNotNull($lesson, 'Не найден урок для теста');

        // Пытаемся получить доступ к уроку без авторизации
        $client->request('GET', '/lesson/' . $lesson->getIdLesson());
        
        // Должен быть редирект на страницу логина
        $this->assertResponseRedirects('/login');
    }

    public function testUnauthorizedUserCanAccessCoursesList(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Подменяем BillingClient МОКом
        static::getContainer()->set(BillingClient::class, new BillingClientMock());

        // Неавторизованный пользователь должен иметь доступ к списку курсов
        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.card-title'); // Проверяем наличие карточек курсов
    }

    public function testUnauthorizedUserCanAccessCoursePage(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Подменяем BillingClient МОКом
        static::getContainer()->set(BillingClient::class, new BillingClientMock());

        // Получаем EntityManager из контейнера
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        // Находим любой курс
        $course = $entityManager->getRepository(Course::class)->findOneBy([]);
        $this->assertNotNull($course, 'Не найден курс для теста');

        // Неавторизованный пользователь должен иметь доступ к странице курса
        $crawler = $client->request('GET', '/courses/' . $course->getIdCourse());
        $this->assertResponseIsSuccessful();
    }

    public function testRegularUserCannotAccessAdminFunctions(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Подменяем BillingClient МОКом
        static::getContainer()->set(BillingClient::class, new BillingClientMock());

        // Авторизуемся как обычный пользователь
        $crawler = $client->request('GET', '/login');
        $submitBtn = $crawler->selectButton('Войти');
        $data = $submitBtn->form([
            'email' => 'user@mail.ru', // Обычный пользователь
            'password' => '123456',
        ]);
        $client->submit($data);

        // Переходим на страницу курсов
        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        // Проверяем, что кнопки админских действий отсутствуют в интерфейсе
        $this->assertSelectorNotExists('a:contains("+ Создать курс")');
        $this->assertSelectorNotExists('a:contains("Редактировать")');
        $this->assertSelectorNotExists('a:contains("Удалить")');
    }

    public function testRegularUserCannotAccessCreateCourseDirectly(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Подменяем BillingClient МОКом
        static::getContainer()->set(BillingClient::class, new BillingClientMock());

        // Авторизуемся как обычный пользователь
        $crawler = $client->request('GET', '/login');
        $submitBtn = $crawler->selectButton('Войти');
        $data = $submitBtn->form([
            'email' => 'user@mail.ru', // Обычный пользователь
            'password' => '123456',
        ]);
        $client->submit($data);

        // Пытаемся получить прямой доступ к созданию курса
        $client->request('GET', '/courses/create');
        
        // Должен быть статус 403 (Forbidden)
        $this->assertResponseStatusCodeSame(403);
    }

    public function testRegularUserCannotAccessEditCourseDirectly(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Подменяем BillingClient МОКом
        static::getContainer()->set(BillingClient::class, new BillingClientMock());

        // Получаем EntityManager из контейнера
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        // Находим любой курс
        $course = $entityManager->getRepository(Course::class)->findOneBy([]);
        $this->assertNotNull($course, 'Не найден курс для теста');

        // Авторизуемся как обычный пользователь
        $crawler = $client->request('GET', '/login');
        $submitBtn = $crawler->selectButton('Войти');
        $data = $submitBtn->form([
            'email' => 'user@mail.ru', // Обычный пользователь
            'password' => '123456',
        ]);
        $client->submit($data);

        // Пытаемся получить прямой доступ к редактированию курса
        $client->request('GET', '/courses/' . $course->getIdCourse() . '/edit');
        
        // Должен быть статус 403 (Forbidden)
        $this->assertResponseStatusCodeSame(403);
    }

    public function testRegularUserCannotDeleteCourseDirectly(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Подменяем BillingClient МОКом
        static::getContainer()->set(BillingClient::class, new BillingClientMock());

        // Получаем EntityManager из контейнера
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        // Находим любой курс
        $course = $entityManager->getRepository(Course::class)->findOneBy([]);
        $this->assertNotNull($course, 'Не найден курс для теста');

        // Авторизуемся как обычный пользователь
        $crawler = $client->request('GET', '/login');
        $submitBtn = $crawler->selectButton('Войти');
        $data = $submitBtn->form([
            'email' => 'user@mail.ru', // Обычный пользователь
            'password' => '123456',
        ]);
        $client->submit($data);

        // Пытаемся получить прямой доступ к удалению курса
        $client->request('GET', '/courses/' . $course->getIdCourse() . '/delete');
        
        // Должен быть статус 403 (Forbidden)
        $this->assertResponseStatusCodeSame(403);
    }

    public function testRegularUserCannotAccessLessonEditDirectly(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Подменяем BillingClient МОКом
        static::getContainer()->set(BillingClient::class, new BillingClientMock());

        // Получаем EntityManager из контейнера
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        // Находим любой урок
        $lesson = $entityManager->getRepository(Lesson::class)->findOneBy([]);
        $this->assertNotNull($lesson, 'Не найден урок для теста');

        // Авторизуемся как обычный пользователь
        $crawler = $client->request('GET', '/login');
        $submitBtn = $crawler->selectButton('Войти');
        $data = $submitBtn->form([
            'email' => 'user@mail.ru', // Обычный пользователь
            'password' => '123456',
        ]);
        $client->submit($data);

        // Пытаемся получить прямой доступ к редактированию урока
        $client->request('GET', '/lesson/' . $lesson->getIdLesson() . '/edit');
        
        // Должен быть статус 403 (Forbidden)
        $this->assertResponseStatusCodeSame(403);
    }

    public function testRegularUserCannotDeleteLessonDirectly(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Подменяем BillingClient МОКом
        static::getContainer()->set(BillingClient::class, new BillingClientMock());

        // Получаем EntityManager из контейнера
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        // Находим любой урок
        $lesson = $entityManager->getRepository(Lesson::class)->findOneBy([]);
        $this->assertNotNull($lesson, 'Не найден урок для теста');

        // Авторизуемся как обычный пользователь
        $crawler = $client->request('GET', '/login');
        $submitBtn = $crawler->selectButton('Войти');
        $data = $submitBtn->form([
            'email' => 'user@mail.ru', // Обычный пользователь
            'password' => '123456',
        ]);
        $client->submit($data);

        // Пытаемся получить прямой доступ к удалению урока
        $client->request('POST', '/lessons/delete/' . $lesson->getIdLesson());
        
        // Должен быть статус 403 (Forbidden)
        $this->assertResponseStatusCodeSame(403);
    }

    public function testAdminCanAccessAllFunctions(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Подменяем BillingClient МОКом
        static::getContainer()->set(BillingClient::class, new BillingClientMock());

        // Авторизуемся как администратор
        $crawler = $client->request('GET', '/login');
        $submitBtn = $crawler->selectButton('Войти');
        $data = $submitBtn->form([
            'email' => 'admin@mail.ru', // Администратор
            'password' => 'password',
        ]);
        $client->submit($data);

        // Переходим на страницу курсов
        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        // Проверяем, что кнопки админских действий присутствуют в интерфейсе
        $this->assertSelectorExists('a:contains("+ Создать курс")');

        // Проверяем доступ к созданию курса
        $client->request('GET', '/courses/create');
        $this->assertResponseIsSuccessful();
    }

    public function testAuthorizedUserCanAccessLessons(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Подменяем BillingClient МОКом
        static::getContainer()->set(BillingClient::class, new BillingClientMock());

        // Получаем EntityManager из контейнера
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        // Находим любой урок
        $lesson = $entityManager->getRepository(Lesson::class)->findOneBy([]);
        $this->assertNotNull($lesson, 'Не найден урок для теста');

        // Авторизуемся как обычный пользователь
        $crawler = $client->request('GET', '/login');
        $submitBtn = $crawler->selectButton('Войти');
        $data = $submitBtn->form([
            'email' => 'user@mail.ru', // Обычный пользователь
            'password' => '123456',
        ]);
        $client->submit($data);

        // Пытаемся получить доступ к уроку с авторизацией
        $client->request('GET', '/lesson/' . $lesson->getIdLesson());
        
        // Может быть успешно (если курс бесплатный) или ошибка доступа (если курс платный)
        // В любом случае не должно быть редиректа на логин
        $response = $client->getResponse();
        $this->assertTrue($response->getStatusCode() === 200 || $response->getStatusCode() === 403, 
            'Ответ должен быть 200 (успех) или 403 (нет доступа), но не редирект на логин');
    }

    public function testRegularUserCanViewCoursePage(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Подменяем BillingClient МОКом
        static::getContainer()->set(BillingClient::class, new BillingClientMock());

        // Получаем EntityManager из контейнера
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        // Находим любой курс
        $course = $entityManager->getRepository(Course::class)->findOneBy([]);
        $this->assertNotNull($course, 'Не найден курс для теста');

        // Авторизуемся как обычный пользователь
        $crawler = $client->request('GET', '/login');
        $submitBtn = $crawler->selectButton('Войти');
        $data = $submitBtn->form([
            'email' => 'user@mail.ru', // Обычный пользователь
            'password' => '123456',
        ]);
        $client->submit($data);

        // Пользователь должен иметь доступ к странице курса
        $crawler = $client->request('GET', '/courses/' . $course->getIdCourse());
        $this->assertResponseIsSuccessful();

        // Но не должен видеть админские кнопки
        $this->assertSelectorNotExists('a:contains("Редактировать")');
        $this->assertSelectorNotExists('a:contains("Удалить")');
    }

    public function testSuccessfulRegistration(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Подменяем BillingClient МОКом
        static::getContainer()->set(BillingClient::class, new BillingClientMock());

        // Отправляем запрос на страницу регистрации
        $crawler = $client->request('GET', '/register');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Регистрация');

        // Заполняем форму регистрации с корректными данными
        $form = $crawler->selectButton('Зарегистрироваться')->form([
            'email' => 'newuser@mail.ru',
            'password' => 'password123',
            'confirm_password' => 'password123',
        ]);

        $client->submit($form);

        // Проверяем, что произошел редирект (успешная регистрация + аутентификация)
        $this->assertResponseRedirects();

        // Переходим по редиректу
        $client->followRedirect();
        $this->assertResponseIsSuccessful();
    }

    public function testRegistrationWithInvalidEmail(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Подменяем BillingClient МОКом
        static::getContainer()->set(BillingClient::class, new BillingClientMock());

        // Отправляем запрос на страницу регистрации
        $crawler = $client->request('GET', '/register');

        // Заполняем форму с невалидным email
        $form = $crawler->selectButton('Зарегистрироваться')->form([
            'email' => 'invalid-email',
            'password' => 'password123',
            'confirm_password' => 'password123',
        ]);

        $client->submit($form);

        // Должны остаться на странице регистрации
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.text-danger'); // Должна быть ошибка валидации
    }

    public function testRegistrationWithShortPassword(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Подменяем BillingClient МОКом
        static::getContainer()->set(BillingClient::class, new BillingClientMock());

        // Отправляем запрос на страницу регистрации
        $crawler = $client->request('GET', '/register');

        // Заполняем форму с коротким паролем
        $form = $crawler->selectButton('Зарегистрироваться')->form([
            'email' => 'test@mail.ru',
            'password' => '123', // Слишком короткий пароль
            'confirm_password' => '123',
        ]);

        $client->submit($form);

        // Должны остаться на странице регистрации с ошибкой
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.text-danger'); // Должна быть ошибка валидации
    }

    public function testRegistrationWithMismatchedPasswords(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Подменяем BillingClient МОКом
        static::getContainer()->set(BillingClient::class, new BillingClientMock());

        // Отправляем запрос на страницу регистрации
        $crawler = $client->request('GET', '/register');

        // Заполняем форму с несовпадающими паролями
        $form = $crawler->selectButton('Зарегистрироваться')->form([
            'email' => 'test@mail.ru',
            'password' => 'password123',
            'confirm_password' => 'differentpassword',
        ]);

        $client->submit($form);

        // Должны остаться на странице регистрации с ошибкой
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.text-danger'); // Должна быть ошибка валидации
    }

    public function testRegistrationWithEmptyFields(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Подменяем BillingClient МОКом
        static::getContainer()->set(BillingClient::class, new BillingClientMock());

        // Отправляем запрос на страницу регистрации
        $crawler = $client->request('GET', '/register');

        // Заполняем форму с пустыми полями
        $form = $crawler->selectButton('Зарегистрироваться')->form([
            'email' => '',
            'password' => '',
            'confirm_password' => '',
        ]);

        $client->submit($form);

        // Должны остаться на странице регистрации с ошибками
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.text-danger'); // Должны быть ошибки валидации
    }

    public function testAlreadyLoggedInUserRedirectsFromRegistration(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Подменяем BillingClient МОКом
        static::getContainer()->set(BillingClient::class, new BillingClientMock());

        // Сначала авторизуемся
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Войти')->form([
            'email' => 'user@mail.ru',
            'password' => '123456',
        ]);
        $client->submit($form);

        // Теперь пытаемся зайти на страницу регистрации
        $client->request('GET', '/register');

        // Должен быть редирект на профиль
        $this->assertResponseRedirects('/profile');
    }

    public function testRegistrationPageDisplaysCorrectly(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Подменяем BillingClient МОКом
        static::getContainer()->set(BillingClient::class, new BillingClientMock());

        // Отправляем запрос на страницу регистрации
        $crawler = $client->request('GET', '/register');
        $this->assertResponseIsSuccessful();

        // Проверяем наличие основных элементов формы
        $this->assertSelectorTextContains('h1', 'Регистрация');
        $this->assertSelectorExists('input[name="email"]');
        $this->assertSelectorExists('input[name="password"]');
        $this->assertSelectorExists('input[name="confirm_password"]');
        $this->assertSelectorExists('button[type="submit"]');
        $this->assertSelectorTextContains('button[type="submit"]', 'Зарегистрироваться');
    }
} 