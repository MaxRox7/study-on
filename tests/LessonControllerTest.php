<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\Entity\Course;
use App\Entity\Lesson;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Tests\Mock\BillingClientMock;
use App\Service\BillingClient;

class LessonControllerTest extends WebTestCase
{


    protected function getFixtures(): array
    {
        return [AppFixtures::class];
    }

    public function testAllLesons(): void
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

        // Получаем курс, у которого есть уроки
        $courseWithLessons = $entityManager->getRepository(Course::class)
            ->createQueryBuilder('c')
            ->join('c.lessons', 'l')
            ->getQuery()
            ->setMaxResults(1)
            ->getOneOrNullResult();

        $this->assertNotNull($courseWithLessons, 'Не найден курс с уроками для теста');

        $lessons = $courseWithLessons->getLessons();

        $client->request('GET', '/courses/'.$courseWithLessons->getIdCourse());

        // Проверка наличия уроков на странице (допустим, каждый отображается как .lesson-item)
        $crawler = $client->getCrawler();
        $this->assertCount(count($lessons), $crawler->filter('.list-group-item'));
        $this->assertResponseIsSuccessful();

        $invalidId = 999999; // заведомо несуществующий ID
        $client->request('GET', '/courses/'.$invalidId);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testCreateLesson(): void
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

        // Создаём курс для урока
        $course = new Course();
        $course->setTitleCourse('Test Course');
        $course->setSymbolCode('TC001');
        $course->setDescription('Test course description.');
        $entityManager->persist($course);
        $entityManager->flush();

        // Отправляем запрос на страницу курса
        $crawler = $client->request('GET', '/courses/'.$course->getIdCourse());

        // Проверка, что страница загрузилась
        $this->assertResponseIsSuccessful();

        // Заполняем форму добавления урока
        $form = $crawler->selectButton('Добавить урок')->form();

        // Исправленный путь к полям формы
        $form['lesson[titleLesson]'] = 'New Lesson';
        $form['lesson[content]'] = 'Lesson content here.';
        $form['lesson[orderNumber]'] = 1;

        // Отправляем форму
        $client->submit($form);

        // Проверяем, что произошёл редирект на страницу курса
        $this->assertResponseRedirects('/courses/'.$course->getIdCourse());

        // Следуем по редиректу
        $crawler = $client->followRedirect();

        // Проверяем, что урок был добавлен
        $this->assertSelectorTextContains('.list-group-item', 'New Lesson');
    }

    public function testLessonValidation(): void
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

        // Создаём курс для урока
        $course = new Course();
        $course->setTitleCourse('Test Course');
        $course->setSymbolCode('TC001');
        $course->setDescription('Test course description.');
        $entityManager->persist($course);
        $entityManager->flush();

        // Отправляем запрос на страницу курса
        $crawler = $client->request('GET', '/courses/'.$course->getIdCourse());
        $this->assertResponseIsSuccessful();

        // 1. Проверка ошибки при слишком коротком titleLesson
        $form = $crawler->selectButton('Добавить урок')->form();
        $form['lesson[titleLesson]'] = 'ff';  // Всего 2 символа - меньше 3
        $form['lesson[content]'] = 'Lesson content here.';
        $form['lesson[orderNumber]'] = 1;
        $client->submit($form);
        $this->assertResponseIsSuccessful();
        $crawler = $client->getCrawler(); // Обновляем crawler после submit

        // Проверяем ошибку конкретно для поля titleLesson
        $titleLessonField = $crawler->filter('#lesson_titleLesson');
        $this->assertCount(1, $titleLessonField, 'Поле titleLesson должно существовать');
        
        // Ищем ошибку в родительском контейнере поля titleLesson
        $titleLessonContainer = $titleLessonField->ancestors()->filter('.mb-3')->first();
        $errorElements = $titleLessonContainer->filter('.invalid-feedback');
        $this->assertGreaterThan(0, $errorElements->count(), 'Должен быть элемент с ошибкой для titleLesson');
        
        $errorText = $errorElements->text();
        $this->assertStringContainsString('Название урока должно содержать минимум', $errorText, 'Ошибка валидации должна быть именно для поля titleLesson');

        // 2. Проверка ошибки при слишком коротком content
        $crawler = $client->request('GET', '/courses/'.$course->getIdCourse());
        $form = $crawler->selectButton('Добавить урок')->form();
        $form['lesson[titleLesson]'] = 'Title_lesson';
        $form['lesson[content]'] = '1';  // Всего 1 символ - меньше 3
        $form['lesson[orderNumber]'] = 1;
        $client->submit($form);
        $this->assertResponseIsSuccessful();
        $crawler = $client->getCrawler(); // Обновляем crawler после submit

        // Проверяем ошибку конкретно для поля content
        $contentField = $crawler->filter('#lesson_content');
        $this->assertCount(1, $contentField, 'Поле content должно существовать');
        
        // Ищем ошибку в родительском контейнере поля content
        $contentContainer = $contentField->ancestors()->filter('.mb-3')->first();
        $errorElements = $contentContainer->filter('.invalid-feedback');
        $this->assertGreaterThan(0, $errorElements->count(), 'Должен быть элемент с ошибкой для content');
        
        $errorText = $errorElements->text();
        $this->assertStringContainsString('Содержимое урока должно содержать минимум', $errorText, 'Ошибка валидации должна быть именно для поля content');

        // 3. Проверка ошибки при некорректном orderNumber
        $crawler = $client->request('GET', '/courses/'.$course->getIdCourse());
        $form = $crawler->selectButton('Добавить урок')->form();
        $form['lesson[titleLesson]'] = 'Title_lesson';
        $form['lesson[content]'] = 'content';
        $form['lesson[orderNumber]'] = 'a';  // Не число
        $client->submit($form);
        $this->assertResponseIsSuccessful();
        $crawler = $client->getCrawler(); // Обновляем crawler после submit

        // Проверяем ошибку конкретно для поля orderNumber
        $orderNumberField = $crawler->filter('#lesson_orderNumber');
        $this->assertCount(1, $orderNumberField, 'Поле orderNumber должно существовать');
        
        // Ищем ошибку в родительском контейнере поля orderNumber
        $orderNumberContainer = $orderNumberField->ancestors()->filter('.mb-3')->first();
        $errorElements = $orderNumberContainer->filter('.invalid-feedback');
        $this->assertGreaterThan(0, $errorElements->count(), 'Должен быть элемент с ошибкой для orderNumber');
        
        $errorText = $errorElements->text();
        $this->assertStringContainsString('Please enter an integer', $errorText, 'Ошибка валидации должна быть именно для поля orderNumber');
    }

    public function testEditLesson(): void
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

        // Отправляем запрос на страницу курса
        $crawler = $client->request('GET', '/courses/'. 1);
        $this->assertResponseIsSuccessful();

        // Выводим HTML-код страницы для диагностики

        // Переход на страницу редактирования урока
        $editLink = $crawler->selectLink('Редактировать')->link();
        $crawler = $client->click($editLink);
        $this->assertResponseIsSuccessful();

        // Заполняем форму редактирования урока новыми данными
        $form = $crawler->selectButton('Сохранить')->form();

        // Изменяем данные урока
        $form['lesson[titleLesson]'] = 'Updated Lesson Title';
        $form['lesson[content]'] = 'Updated lesson content';
        $form['lesson[orderNumber]'] = 2;

        // Отправляем форму
        $client->submit($form);

        // Проверяем редирект после успешного редактирования
        $this->assertResponseRedirects();
        $crawler = $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertRouteSame('course_show', ['idCourse' => 1]);
    }

    public function testEditLessonValidation(): void
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

        // Отправляем запрос на страницу курса
        $crawler = $client->request('GET', '/courses/1');
        $this->assertResponseIsSuccessful();

        // Переход на страницу редактирования урока
        $editLink = $crawler->selectLink('Редактировать')->link();
        $crawler = $client->click($editLink);
        $this->assertResponseIsSuccessful();

        // 1. Проверка ошибки при слишком коротком titleLesson
        $form = $crawler->selectButton('Сохранить')->form();
        $form['lesson[titleLesson]'] = 'ff';  // Всего 2 символа - меньше 3
        $form['lesson[content]'] = 'Lesson content here.';
        $form['lesson[orderNumber]'] = 1;
        $client->submit($form);
        $this->assertResponseIsSuccessful();
        $crawler = $client->getCrawler();

        // Проверяем ошибку конкретно для поля titleLesson (в форме редактирования используется .form-floating)
        $titleLessonField = $crawler->filter('#lesson_titleLesson');
        $this->assertCount(1, $titleLessonField, 'Поле titleLesson должно существовать');
        
        // Ищем ошибку в родительском контейнере поля titleLesson
        $titleLessonContainer = $titleLessonField->ancestors()->filter('.form-floating')->first();
        $errorElements = $titleLessonContainer->filter('.invalid-feedback');
        $this->assertGreaterThan(0, $errorElements->count(), 'Должен быть элемент с ошибкой для titleLesson');
        
        $errorText = $errorElements->text();
        $this->assertStringContainsString('Название урока должно содержать минимум', $errorText, 'Ошибка валидации должна быть именно для поля titleLesson');

        // 2. Проверка ошибки при слишком коротком content
        $crawler = $client->request('GET', '/courses/1');
        $editLink = $crawler->selectLink('Редактировать')->link();
        $crawler = $client->click($editLink);
        
        $form = $crawler->selectButton('Сохранить')->form();
        $form['lesson[titleLesson]'] = 'Title_lesson';
        $form['lesson[content]'] = '1';  // Всего 1 символ - меньше 3
        $form['lesson[orderNumber]'] = 1;
        $client->submit($form);
        $this->assertResponseIsSuccessful();
        $crawler = $client->getCrawler();

        // Проверяем ошибку конкретно для поля content
        $contentField = $crawler->filter('#lesson_content');
        $this->assertCount(1, $contentField, 'Поле content должно существовать');
        
        // Ищем ошибку в родительском контейнере поля content
        $contentContainer = $contentField->ancestors()->filter('.form-floating')->first();
        $errorElements = $contentContainer->filter('.invalid-feedback');
        $this->assertGreaterThan(0, $errorElements->count(), 'Должен быть элемент с ошибкой для content');
        
        $errorText = $errorElements->text();
        $this->assertStringContainsString('Содержимое урока должно содержать минимум', $errorText, 'Ошибка валидации должна быть именно для поля content');

        // 3. Проверка ошибки при некорректном orderNumber
        $crawler = $client->request('GET', '/courses/1');
        $editLink = $crawler->selectLink('Редактировать')->link();
        $crawler = $client->click($editLink);
        
        $form = $crawler->selectButton('Сохранить')->form();
        $form['lesson[titleLesson]'] = 'Title_lesson';
        $form['lesson[content]'] = 'content';
        $form['lesson[orderNumber]'] = 'a';  // Не число
        $client->submit($form);
        $this->assertResponseIsSuccessful();
        $crawler = $client->getCrawler();

        // Проверяем ошибку конкретно для поля orderNumber
        $orderNumberField = $crawler->filter('#lesson_orderNumber');
        $this->assertCount(1, $orderNumberField, 'Поле orderNumber должно существовать');
        
        // Ищем ошибку в родительском контейнере поля orderNumber
        $orderNumberContainer = $orderNumberField->ancestors()->filter('.form-floating')->first();
        $errorElements = $orderNumberContainer->filter('.invalid-feedback');
        $this->assertGreaterThan(0, $errorElements->count(), 'Должен быть элемент с ошибкой для orderNumber');
        
        $errorText = $errorElements->text();
        $this->assertStringContainsString('Please enter an integer', $errorText, 'Ошибка валидации должна быть именно для поля orderNumber');
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

        // Переходим на страницу с курсами
        $crawler = $client->request('GET', '/courses');
        $this->assertResponseIsSuccessful();

        // Сохраняем количество курсов до удаления
        $coursesCountBefore = count($entityManager->getRepository(Course::class)->findAll());

        $deleteLink = $crawler->selectLink('Удалить')->link();
        $crawler = $client->click($deleteLink);  // Переход на страницу с подтверждением удаления

        // Проверяем редирект после удаления
        $crawler = $client->followRedirect();
        $this->assertResponseIsSuccessful();

        // Проверяем, что курс был удален
        $coursesCountAfter = count($entityManager->getRepository(Course::class)->findAll());
        $this->assertSame($coursesCountAfter, $coursesCountBefore - 1);
    }
}
