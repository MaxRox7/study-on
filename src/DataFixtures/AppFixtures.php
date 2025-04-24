<?php

// src/DataFixtures/AppFixtures.php

namespace App\DataFixtures;

use App\Entity\Course;
use App\Entity\Lesson;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $this->createBasicCourses($manager);
        $this->createProgrammingCourses($manager);

        $manager->flush();
    }

    private function createBasicCourses(ObjectManager $manager): void
    {
        $course = new Course();
        $course->setSymbolCode('web-basics')
            ->setTitleCourse('Основы веб-разработки')
            ->setDescription('Введение в HTML, CSS и JavaScript');

        $lessons = [
            ['HTML Basics', 'Основы HTML: теги, структура документа...', 1],
            ['CSS Fundamentals', 'Работа со стилями и селекторами...', 2],
            ['JavaScript Intro', 'Базовый синтаксис JavaScript...', 3],
        ];

        foreach ($lessons as $lessonData) {
            $lesson = new Lesson();
            $lesson->setTitleLesson($lessonData[0])
                ->setContent($lessonData[1])
                ->setOrderNumber($lessonData[2])
                ->setCourse($course);

            $manager->persist($lesson);
            $course->addLesson($lesson);
        }

        $manager->persist($course);
    }

    private function createProgrammingCourses(ObjectManager $manager): void
    {
        $courses = [
            [
                'code' => 'php-oop',
                'title' => 'PHP: ООП',
                'description' => 'Объектно-ориентированное программирование на PHP',
                'lessons' => [
                    ['Классы и объекты', 'Создание классов и работа с объектами...', 1],
                    ['Наследование', 'Принципы наследования в PHP...', 2],
                    ['Интерфейсы', 'Работа с интерфейсами и абстрактными классами...', 3],
                ],
            ],
            [
                'code' => 'symfony-api',
                'title' => 'REST API на Symfony',
                'description' => 'Создание RESTful API с использованием Symfony',
                'lessons' => [
                    ['Введение в REST', 'Основные принципы REST архитектуры...', 1],
                    ['Создание контроллеров', 'Работа с API-ориентированными контроллерами...', 2],
                    ['JWT аутентификация', 'Реализация аутентификации с использованием JWT...', 3],
                ],
            ],
        ];

        foreach ($courses as $courseData) {
            $course = new Course();
            $course->setSymbolCode($courseData['code'])
                ->setTitleCourse($courseData['title'])
                ->setDescription($courseData['description']);

            foreach ($courseData['lessons'] as $lessonData) {
                $lesson = new Lesson();
                $lesson->setTitleLesson($lessonData[0])
                    ->setContent($lessonData[1])
                    ->setOrderNumber($lessonData[2])
                    ->setCourse($course);

                $manager->persist($lesson);
                $course->addLesson($lesson);
            }

            $manager->persist($course);
        }
    }
}
