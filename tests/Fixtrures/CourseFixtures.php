<?php

namespace App\DataFixtures;

use App\Entity\Course;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CourseFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $course = new Course();
        $course->setSymbolCode('php')
               ->setTitleCourse('PHP Course')
               ->setDescription('Learn PHP in depth');

        $manager->persist($course);
        $manager->flush();
    }
}
