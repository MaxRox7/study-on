<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\Entity\Course;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Tests\Mock\BillingClientMock;
use App\Service\BillingClient;

class CourseControllerTest extends WebTestCase
{
    protected function getFixtures(): array
    {
        return [AppFixtures::class];
    }
    

    public function testAllCoursesWithMockBillingClient(): void
    {
        $client = static::createClient();
        $client->disableReboot();
    
        $billingClientMock = new BillingClientMock();
    
        $container = static::getContainer();
    
        // Важно: правильный сервис нужно переопределить
        // Например, если сервис называется App\Service\BillingClient, а не BillingClientMock
        $container->set(BillingClient::class, $billingClientMock);
    
        // Теперь все, кто запрашивает BillingClient через DI, получат мок
    
        $crawler = $client->request('GET', '/courses');

        // echo $client->getResponse()->getContent(); // Добавьте это для отладки
        
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.card-title');
    
        $client->request('GET', '/courses/999999');
        $this->assertResponseStatusCodeSame(404);
    }

    

    public function testCourseCreationForm(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Подменяем BillingClient МОКом
        static::getContainer()->set(BillingClient::class, new BillingClientMock());


         // Логин
         $crawler = $client->request('GET', '/login');
         $submitBtn = $crawler->selectButton('Войти');
         $data = $submitBtn->form([
             'email' => 'admin@mail.ru', # Логинимся как админ
             'password' => 'password',
         ]);
         $client->submit($data);
        //  echo $client->getResponse()->getContent(); // Добавьте это для отладки

        // Переходим на страницу курсов
        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        // Нажимаем кнопку "Создать курс"
        $addCourse = $crawler->selectLink('+ Создать курс')->link();
        $crawler = $client->click($addCourse);  // Переход на страницу создания курса
        $this->assertResponseIsSuccessful();  // Проверяем, что страница загрузилась

        // 1. Проверка валидации: отправка пустой формы (ошибки обязательных полей)
        $form = $crawler->selectButton('Создать')->form();
        $client->submit($form);  // Отправляем пустую форму

        // Проверяем, что валидация сработала и мы остались на той же странице
        $this->assertResponseIsSuccessful();
        $crawler = $client->getCrawler();

        // Находим форму и заполняем ее
        $form = $crawler->selectButton('Создать')->form();
        $form['symbolCode'] = 'CS101';  // Код курса
        $form['title_course'] = 'Основы программирования';  // Название курса
        $form['description'] = 'Курс по основам программирования на Python.';  // Описание курса

        // Отправляем форму
        $client->submit($form);

        // Проверяем, что произошел редирект на страницу курса, например, с ID 1

        // Следуем за редиректом
        $crawler = $client->followRedirect();

        // Проверяем, что на странице курса отображается название
        $this->assertStringContainsString('Основы программирования', $crawler->text());

        // Дополнительно: проверяем, что описание курса также присутствует на странице
        $this->assertStringContainsString('Курс по основам программирования на Python.', $crawler->text());
    }


    public function testAuthWithValidUserCredentials(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Подменяем BillingClient МОКом
        static::getContainer()->set(BillingClient::class, new BillingClientMock());
    
        // Получаем страницу логина
        $crawler = $client->request('GET', '/login');
    
        // Заполняем форму и отправляем
        $form = $crawler->selectButton('Войти')->form();
        $form['email'] = 'user@mail.ru';
        $form['password'] = '123456';
    
        $client->submit($form);
    
        // Проверяем редирект после логина
        $this->assertTrue($client->getResponse()->isRedirect());
    
        $client->followRedirect();
    
        // Проверяем, что в ответе есть текст с балансом (или другой индикатор успешного входа)
        $this->assertStringContainsString('Ваш баланс', $client->getResponse()->getContent());
    }
    
    
    
    public function testCourseValidationWithErrors(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Подменяем BillingClient МОКом
        static::getContainer()->set(BillingClient::class, new BillingClientMock());

        // Авторизация как администратор
        $crawler = $client->request('GET', '/login');
        $submitBtn = $crawler->selectButton('Войти');
        $data = $submitBtn->form([
            'email' => 'admin@mail.ru', // Логинимся как админ
            'password' => 'password',
        ]);
        $client->submit($data);

        // Переход на страницу курсов
        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        // Переход на форму создания курса
        $addCourse = $crawler->selectLink('+ Создать курс')->link();
        $crawler = $client->click($addCourse);
        $this->assertResponseIsSuccessful();

        // 1. Проверка ошибки при слишком коротком symbolCode
        $form = $crawler->selectButton('Создать')->form();
        $form['symbolCode'] = 'ff';
        $form['title_course'] = 'Основы программирования';
        $form['description'] = 'Курс по основам программирования на Python.';
        $client->submit($form);
        $this->assertResponseIsSuccessful();
        $crawler = $client->getCrawler(); // Обновляем crawler после submit
        
        $this->assertSelectorExists('.alert-error_symbol_code');
        $this->assertSelectorTextContains('.alert-error_symbol_code', 'Символьный код должен содержать минимум 3 символа.');

        // 2. Проверка ошибки при слишком коротком title_course  
        // Возвращаемся на форму создания курса
        $crawler = $client->request('GET', '/course/create');
        $form = $crawler->selectButton('Создать')->form();
        $form['symbolCode'] = 'CS101';
        $form['title_course'] = 'ff';
        $form['description'] = 'Курс по основам программирования на Python.';
        $client->submit($form);
        $this->assertResponseIsSuccessful();
        $crawler = $client->getCrawler(); // Обновляем crawler после submit
        $this->assertSelectorExists('.alert-error_title_course');
        $this->assertSelectorTextContains('.alert-error_title_course', 'Название курса должно содержать минимум 3 символа.');

        // 3. Проверка ошибки при слишком коротком description
        // Возвращаемся на форму создания курса
        $crawler = $client->request('GET', '/course/create');
        $form = $crawler->selectButton('Создать')->form();
        $form['symbolCode'] = 'CS101';
        $form['title_course'] = 'Основы программирования';
        $form['description'] = 'ff';
        $client->submit($form);
        $this->assertResponseIsSuccessful();
        $crawler = $client->getCrawler(); // Обновляем crawler после submit
        $this->assertSelectorExists('.alert-error_description');
        $this->assertSelectorTextContains('.alert-error_description', 'Описание курса должно содержать минимум 3 символа.');
    }

    public function testCourseEditForm(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Подменяем BillingClient МОКом
        static::getContainer()->set(BillingClient::class, new BillingClientMock());

        // Авторизация как администратор
        $crawler = $client->request('GET', '/login');
        $submitBtn = $crawler->selectButton('Войти');
        $data = $submitBtn->form([
            'email' => 'admin@mail.ru', // Логинимся как админ
            'password' => 'password',
        ]);
        $client->submit($data);

        // Переход на страницу курсов
        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        // Нажимаем на первую ссылку "Редактировать"
        $editLink = $crawler->selectLink('Редактировать')->link();
        $crawler = $client->click($editLink);
        $this->assertResponseIsSuccessful();

        // Заполняем форму новыми данными
        $form = $crawler->selectButton('Сохранить изменения')->form();
        $form['form[titleCourse]'] = 'Основы программирования';  // Corrected to 'form[titleCourse]'
        $form['form[symbolCode]'] = 'CS101';  // Corrected to 'form[symbolCode]'
        $form['form[description]'] = 'Курс по основам программирования на Python.';  // Corrected to 'form[description]'

        // Отправляем форму
        $client->submit($form);

        // Следуем за редиректом
        $crawler = $client->followRedirect();
        $this->assertResponseIsSuccessful();

        // Проверяем наличие новых данных
        $this->assertStringContainsString('Основы программирования', $crawler->text());
        $this->assertStringContainsString('Курс по основам программирования на Python.', $crawler->text());
    }

    public function testCourseEditValidationWithErrors(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        // Подменяем BillingClient МОКом
        static::getContainer()->set(BillingClient::class, new BillingClientMock());

        // Авторизация как администратор
        $crawler = $client->request('GET', '/login');
        $submitBtn = $crawler->selectButton('Войти');
        $data = $submitBtn->form([
            'email' => 'admin@mail.ru', // Логинимся как админ
            'password' => 'password',
        ]);
        $client->submit($data);

        // Переход на страницу курсов
        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();
        // Переход на страницу редактирования курса
        $crawler = $client->request('GET', '/courses/1/edit');
        $this->assertResponseIsSuccessful();

        // Проверка ошибки при слишком коротком symbolCode
        $form = $crawler->selectButton('Сохранить изменения')->form();
        $form['form[symbolCode]'] = 'ff'; // слишком короткий код
        $form['form[titleCourse]'] = 'Основы программирования';
        $form['form[description]'] = 'Курс по основам программирования на Python.';
        $crawler = $client->submit($form);

        // Проверка наличия ошибки для поля symbolCode
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.invalid-feedback'); // проверяем наличие блока ошибки

        // Проверка ошибки при слишком коротком titleCourse
        $form = $crawler->selectButton('Сохранить изменения')->form();
        $form['form[symbolCode]'] = 'CS101';
        $form['form[titleCourse]'] = 'ff'; // слишком короткое название
        $form['form[description]'] = 'Курс по основам программирования на Python.';
        $crawler = $client->submit($form);

        // Проверка наличия ошибки для поля titleCourse
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.invalid-feedback'); // проверяем наличие блока ошибки

        // Проверка ошибки при слишком коротком description
        $form = $crawler->selectButton('Сохранить изменения')->form();
        $form['form[symbolCode]'] = 'CS101';
        $form['form[titleCourse]'] = 'Основы программирования';
        $form['form[description]'] = 'ff'; // слишком короткое описание
        $crawler = $client->submit($form);

        // Проверка наличия ошибки для поля description
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.invalid-feedback'); // проверяем наличие блока ошибки
    }

    public function testDeleteCourse(): void
     {
         $client = static::createClient();
         $client->disableReboot();

         // Подменяем BillingClient МОКом
         static::getContainer()->set(BillingClient::class, new BillingClientMock());

         // Получаем EntityManager из контейнера
         $container = static::getContainer();
         $entityManager = $container->get(EntityManagerInterface::class);

         // Авторизация как администратор
         $crawler = $client->request('GET', '/login');
         $submitBtn = $crawler->selectButton('Войти');
         $data = $submitBtn->form([
             'email' => 'admin@mail.ru', // Логинимся как админ
             'password' => 'password',
         ]);
         $client->submit($data);

         // Переход на страницу курсов
         $crawler = $client->request('GET', '/courses');
         $this->assertResponseIsSuccessful();

         // Сохраняем количество курсов до удаления
         $coursesCountBefore = count($entityManager->getRepository(Course::class)->findAll());

        // Находим и кликаем кнопку "Удалить" у первого курса
        $DeleteCourse = $crawler->selectLink('Удалить')->link();
        $crawler = $client->click($DeleteCourse);  // Переход на страницу создания курса

        // Проверяем редирект после удаления
        $crawler = $client->followRedirect();
        $this->assertResponseIsSuccessful();
        // Проверяем, что курс был удален
        $coursesCountAfter = count($entityManager->getRepository(Course::class)->findAll());
        $this->assertSame($coursesCountAfter, $coursesCountBefore - 1);
    }
}
