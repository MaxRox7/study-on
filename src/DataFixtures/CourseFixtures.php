<?php

namespace App\DataFixtures;

use App\Entity\Course;
use App\Entity\Lesson;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CourseFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        for ($i = 1; $i <= 3; ++$i) {
            $course = new Course();
            $course->setSymbolCode("course-$i");
            $course->setTitle("Курс $i");
            $course->setDescription("Описание курса $i");

            $manager->persist($course);

            for ($j = 1; $j <= 3; ++$j) {
                $lesson = new Lesson();
                $lesson->setTitle("Урок $j курса $i");
                $lesson->setContent("Содержимое урока $j курса $i");
                $lesson->setOrderNumber($j);
                $lesson->setCourse($course);

                $manager->persist($lesson);
            }
        }

        $manager->flush();
    }
}
