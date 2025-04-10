<?php

namespace App\DataFixtures;

use App\Entity\Course;
use App\Entity\Lesson;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Пример курсов
        $coursesData = [
            [
                'symbolCode' => 'PHP101',
                'title_course' => 'Основы PHP',
                'description' => 'Курс по изучению базового синтаксиса и структур языка PHP.',
                'lessons' => [
                    ['title_lesson' => 'Введение в PHP', 'name_lesson' => 'intro', 'status_lesson' => 'active', 'content' => 'Что такое PHP и зачем он нужен?', 'order' => 1],
                    ['title_lesson' => 'Переменные и типы данных', 'name_lesson' => 'variables', 'status_lesson' => 'active', 'content' => 'Объявление переменных и работа с типами данных.', 'order' => 2],
                    ['title_lesson' => 'Условия и циклы', 'name_lesson' => 'conditions', 'status_lesson' => 'active', 'content' => 'Логика исполнения: if, else, switch, for, foreach.', 'order' => 3],
                ],
            ],
            [
                'symbolCode' => 'JS202',
                'title_course' => 'JavaScript для начинающих',
                'description' => 'Изучаем основы языка JavaScript, работу с DOM и событиями.',
                'lessons' => [
                    ['title_lesson' => 'Что такое JavaScript', 'name_lesson' => 'js_intro', 'status_lesson' => 'active', 'content' => 'История и применение JavaScript.', 'order' => 1],
                    ['title_lesson' => 'Работа с DOM', 'name_lesson' => 'dom', 'status_lesson' => 'active', 'content' => 'Манипуляции с элементами страницы.', 'order' => 2],
                ],
            ],
            [
                'symbolCode' => 'SQL300',
                'title_course' => 'Базы данных и SQL',
                'description' => 'Научитесь создавать запросы и работать с базами данных.',
                'lessons' => [
                    ['title_lesson' => 'Введение в базы данных', 'name_lesson' => 'db_intro', 'status_lesson' => 'active', 'content' => 'Что такое БД, таблицы, строки и поля.', 'order' => 1],
                    ['title_lesson' => 'SELECT-запросы', 'name_lesson' => 'select_queries', 'status_lesson' => 'draft', 'content' => 'Как получать данные из базы.', 'order' => 2],
                    ['title_lesson' => 'JOIN-операции', 'name_lesson' => 'joins', 'status_lesson' => 'draft', 'content' => 'Связи между таблицами и их использование.', 'order' => 3],
                ],
            ],
        ];

        foreach ($coursesData as $courseData) {
            $course = new Course();
            $course->setSymbolCode($courseData['symbolCode']);
            $course->setTitleCourse($courseData['title_course']);
            $course->setDescription($courseData['description']);

            foreach ($courseData['lessons'] as $lessonData) {
                $lesson = new Lesson();
                $lesson->setTitleLesson($lessonData['title_lesson']);
                $lesson->setNameLesson($lessonData['name_lesson']);
                $lesson->setStatusLesson($lessonData['status_lesson']);
                $lesson->setContent($lessonData['content']);
                $lesson->setOrderNumber($lessonData['order']);
                $lesson->setCourse($course); // Установка связи
                $course->addLesson($lesson); // Также добавляем в коллекцию курса
            }

            $manager->persist($course);
        }

        $manager->flush();
    }
}
