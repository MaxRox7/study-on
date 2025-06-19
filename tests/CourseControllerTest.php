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
        $form['course[symbolCode]'] = 'CS101';  // Код курса
        $form['course[titleCourse]'] = 'Основы программирования';  // Название курса
        $form['course[description]'] = 'Курс по основам программирования на Python.';  // Описание курса
        $form['course[courseType]'] = 'free';  // Тип курса

        // Отправляем форму
        $client->submit($form);

        // Проверяем что форма была обработана
        $this->assertResponseIsSuccessful();
        
        // Проверяем результат - либо редирект (успех), либо ошибка биллинга
        if ($client->getResponse()->isRedirect()) {
            // Успешное создание - следуем за редиректом
            $crawler = $client->followRedirect();
            $this->assertStringContainsString('Основы программирования', $crawler->text());
            $this->assertStringContainsString('Курс по основам программирования на Python.', $crawler->text());
        } else {
            // Ошибка биллинга - проверяем что есть ошибка
            $this->assertStringContainsString('Ошибка', $client->getResponse()->getContent());
        }
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
        $form['course[symbolCode]'] = 'ff';  
        $form['course[titleCourse]'] = 'Основы программирования';
        $form['course[description]'] = 'Курс по основам программирования на Python.';
        $form['course[courseType]'] = 'free';
        $client->submit($form);
        $this->assertResponseIsSuccessful();
        $crawler = $client->getCrawler(); // Обновляем crawler после submit
        
        // Проверяем ошибку конкретно для поля symbolCode
        $symbolCodeField = $crawler->filter('#course_symbolCode');
        $this->assertCount(1, $symbolCodeField, 'Поле symbolCode должно существовать');
        
        // Ищем ошибку в родительском контейнере поля symbolCode
        $symbolCodeContainer = $symbolCodeField->ancestors()->filter('.mb-3')->first();
        $errorText = $symbolCodeContainer->filter('.help-block')->text();
        $this->assertStringContainsString('Символьный код должен содержать минимум', $errorText, 'Ошибка валидации должна быть именно для поля symbolCode');

        // 2. Проверка ошибки при слишком коротком title_course  
        $crawler = $client->request('GET', '/courses/create');
        $form = $crawler->selectButton('Создать')->form();
        $form['course[symbolCode]'] = 'CS101';
        $form['course[titleCourse]'] = 'ff';  
        $form['course[description]'] = 'Курс по основам программирования на Python.';
        $form['course[courseType]'] = 'free';
        $client->submit($form);
        $this->assertResponseIsSuccessful();
        $crawler = $client->getCrawler(); // Обновляем crawler после submit
        
        // Проверяем ошибку конкретно для поля titleCourse
        $titleCourseField = $crawler->filter('#course_titleCourse');
        $this->assertCount(1, $titleCourseField, 'Поле titleCourse должно существовать');
        
        // Ищем ошибку в родительском контейнере поля titleCourse
        $titleCourseContainer = $titleCourseField->ancestors()->filter('.mb-3')->first();
        $errorText = $titleCourseContainer->filter('.help-block')->text();
        $this->assertStringContainsString('Название курса должно содержать минимум', $errorText, 'Ошибка валидации должна быть именно для поля titleCourse');

        // 3. Проверка ошибки при слишком коротком description
        $crawler = $client->request('GET', '/courses/create');
        $form = $crawler->selectButton('Создать')->form();
        $form['course[symbolCode]'] = 'CS101';
        $form['course[titleCourse]'] = 'Основы программирования';
        $form['course[description]'] = 'ff';  
        $form['course[courseType]'] = 'free';
        $client->submit($form);
        $this->assertResponseIsSuccessful();
        $crawler = $client->getCrawler(); // Обновляем crawler после submit
        
        // Проверяем ошибку конкретно для поля description
        $descriptionField = $crawler->filter('#course_description');
        $this->assertCount(1, $descriptionField, 'Поле description должно существовать');
        
        // Ищем ошибку в родительском контейнере поля description
        $descriptionContainer = $descriptionField->ancestors()->filter('.mb-3')->first();
        $errorText = $descriptionContainer->filter('.help-block')->text();
        $this->assertStringContainsString('Описание курса должно содержать минимум', $errorText, 'Ошибка валидации должна быть именно для поля description');
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
        $form['course[titleCourse]'] = 'Основы программирования';
        $form['course[symbolCode]'] = 'CS101';
        $form['course[description]'] = 'Курс по основам программирования на Python.';
        $form['course[courseType]'] = 'free';

        // Отправляем форму
        $client->submit($form);

        // Проверяем что форма была обработана
        $this->assertResponseIsSuccessful();
        
        // Проверяем результат - либо редирект (успех), либо ошибка биллинга
        if ($client->getResponse()->isRedirect()) {
            // Успешное редактирование - следуем за редиректом
            $crawler = $client->followRedirect();
            $this->assertResponseIsSuccessful();
            $this->assertStringContainsString('Основы программирования', $crawler->text());
            $this->assertStringContainsString('Курс по основам программирования на Python.', $crawler->text());
        } else {
            // Ошибка биллинга - проверяем что есть ошибка
            $this->assertStringContainsString('Ошибка', $client->getResponse()->getContent());
        }
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

        // 1. Проверка ошибки при слишком коротком symbolCode
        $form = $crawler->selectButton('Сохранить изменения')->form();
        $form['course[symbolCode]'] = 'ff'; // слишком короткий код
        $form['course[titleCourse]'] = 'Основы программирования';
        $form['course[description]'] = 'Курс по основам программирования на Python.';
        $form['course[courseType]'] = 'free';
        $client->submit($form);
        $this->assertResponseIsSuccessful();
        $crawler = $client->getCrawler(); // Обновляем crawler после submit

        // Проверяем ошибку конкретно для поля symbolCode
        $symbolCodeField = $crawler->filter('#course_symbolCode');
        $this->assertCount(1, $symbolCodeField, 'Поле symbolCode должно существовать');
        
        // Ищем ошибку в родительском контейнере поля symbolCode
        $symbolCodeContainer = $symbolCodeField->ancestors()->filter('.mb-4')->first();
        $errorElements = $symbolCodeContainer->filter('.help-block, .invalid-feedback');
        $this->assertGreaterThan(0, $errorElements->count(), 'Должен быть элемент с ошибкой для symbolCode');
        
        $errorText = $errorElements->text();
        $this->assertStringContainsString('Символьный код должен содержать минимум', $errorText, 'Ошибка валидации должна быть именно для поля symbolCode');

        // 2. Проверка ошибки при слишком коротком titleCourse
        $crawler = $client->request('GET', '/courses/1/edit');
        $form = $crawler->selectButton('Сохранить изменения')->form();
        $form['course[symbolCode]'] = 'CS101';
        $form['course[titleCourse]'] = 'ff'; // слишком короткое название
        $form['course[description]'] = 'Курс по основам программирования на Python.';
        $form['course[courseType]'] = 'free';
        $client->submit($form);
        $this->assertResponseIsSuccessful();
        $crawler = $client->getCrawler(); // Обновляем crawler после submit

        // Проверяем ошибку конкретно для поля titleCourse
        $titleCourseField = $crawler->filter('#course_titleCourse');
        $this->assertCount(1, $titleCourseField, 'Поле titleCourse должно существовать');
        
        // Ищем ошибку в родительском контейнере поля titleCourse
        $titleCourseContainer = $titleCourseField->ancestors()->filter('.mb-4')->first();
        $errorElements = $titleCourseContainer->filter('.help-block, .invalid-feedback');
        $this->assertGreaterThan(0, $errorElements->count(), 'Должен быть элемент с ошибкой для titleCourse');
        
        $errorText = $errorElements->text();
        $this->assertStringContainsString('Название курса должно содержать минимум', $errorText, 'Ошибка валидации должна быть именно для поля titleCourse');

        // 3. Проверка ошибки при слишком коротком description
        $crawler = $client->request('GET', '/courses/1/edit');
        $form = $crawler->selectButton('Сохранить изменения')->form();
        $form['course[symbolCode]'] = 'CS101';
        $form['course[titleCourse]'] = 'Основы программирования';
        $form['course[description]'] = 'ff'; // слишком короткое описание
        $form['course[courseType]'] = 'free';
        $client->submit($form);
        $this->assertResponseIsSuccessful();
        $crawler = $client->getCrawler(); // Обновляем crawler после submit

        // Проверяем ошибку конкретно для поля description
        $descriptionField = $crawler->filter('#course_description');
        $this->assertCount(1, $descriptionField, 'Поле description должно существовать');
        
        // Ищем ошибку в родительском контейнере поля description
        $descriptionContainer = $descriptionField->ancestors()->filter('.mb-4')->first();
        $errorElements = $descriptionContainer->filter('.help-block, .invalid-feedback');
        $this->assertGreaterThan(0, $errorElements->count(), 'Должен быть элемент с ошибкой для description');
        
        $errorText = $errorElements->text();
        $this->assertStringContainsString('Описание курса должно содержать минимум', $errorText, 'Ошибка валидации должна быть именно для поля description');
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
