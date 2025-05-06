<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\Entity\Course;
use App\Entity\Lesson;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LessonControllerTest extends WebTestCase
{
    protected function getFixtures(): array
    {
        return [AppFixtures::class];
    }

    public function testAllLesons(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

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
        $container = static::getContainer();
        $entityManager = $container->get('doctrine')->getManager();

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
        $form['form[titleLesson]'] = 'New Lesson';
        $form['form[content]'] = 'Lesson content here.';
        $form['form[orderNumber]'] = 1;

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
        $container = static::getContainer();
        $entityManager = $container->get('doctrine')->getManager();

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
        $form['form[titleLesson]'] = 'ff';
        $form['form[content]'] = 'Lesson content here.';
        $form['form[orderNumber]'] = 1;

        // Отправляем форму
        $client->submit($form);
        // Проверка наличия ошибки для поля symbolCode
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.invalid-feedback'); // проверяем наличие блока ошибки

        $form['form[titleLesson]'] = 'Title_lesson';
        $form['form[content]'] = '1';
        $form['form[orderNumber]'] = 1;
        $client->submit($form);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.invalid-feedback'); // проверяем наличие блока ошибки

        $form['form[titleLesson]'] = 'Title_lesson';
        $form['form[content]'] = 'content';
        $form['form[orderNumber]'] = 'a';

        $client->submit($form);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.invalid-feedback'); // проверяем наличие блока ошибки
    }

    public function testEditLesson(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $entityManager = $container->get('doctrine')->getManager();

        // Отправляем запрос на страницу курса
        $crawler = $client->request('GET', '/courses/'. 2);
        $this->assertResponseIsSuccessful();

        // Выводим HTML-код страницы для диагностики

        // Переход на страницу редактирования урока
        $editLink = $crawler->selectLink('Редактировать')->link();
        $crawler = $client->click($editLink);
        $this->assertResponseIsSuccessful();

        // Заполняем форму редактирования урока новыми данными
        $form = $crawler->selectButton('Сохранить')->form();

        // Изменяем данные урока
        $form['form[titleLesson]'] = 'Updated Lesson Title';
        $form['form[content]'] = 'Updated lesson content';
        $form['form[orderNumber]'] = 2;

        // Отправляем форму
        $client->submit($form);

        // Проверяем редирект после успешного редактирования
        $this->assertResponseRedirects();
        $crawler = $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertRouteSame('course_show', ['idCourse' => 2]);
    }

    public function testEditLessonValidation(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $entityManager = $container->get('doctrine')->getManager();

        // Отправляем запрос на страницу курса
        $crawler = $client->request('GET', '/courses/'. 2);
        $this->assertResponseIsSuccessful();

        // Выводим HTML-код страницы для диагностики

        // Переход на страницу редактирования урока
        $editLink = $crawler->selectLink('Редактировать')->link();
        $crawler = $client->click($editLink);
        $this->assertResponseIsSuccessful();

        // Заполняем форму редактирования урока новыми данными
        $form = $crawler->selectButton('Сохранить')->form();

        // Исправленный путь к полям формы
        $form['form[titleLesson]'] = 'ff';
        $form['form[content]'] = 'Lesson content here.';
        $form['form[orderNumber]'] = 1;

        // Отправляем форму

        $client->submit($form);
        // Проверка наличия ошибки для поля symbolCode
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.invalid-feedback'); // проверяем наличие блока ошибки

        $form['form[titleLesson]'] = 'Title_lesson';
        $form['form[content]'] = '1';
        $form['form[orderNumber]'] = 1;

        $client->submit($form);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.invalid-feedback'); // проверяем наличие блока ошибки

        $form['form[titleLesson]'] = 'Title_lesson';
        $form['form[content]'] = 'content';
        $form['form[orderNumber]'] = 'a';

        $client->submit($form);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.invalid-feedback'); // проверяем наличие блока ошибки
    }

    public function testDeleteCourse(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

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
