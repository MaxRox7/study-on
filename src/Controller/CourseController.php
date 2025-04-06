<?php

namespace App\Controller;

use App\Entity\Course;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CourseController extends AbstractController
{
    #[Route('/courses/{idCourse}', name: 'course_show')]
    public function show(EntityManagerInterface $entityManager, int $idCourse): Response
    {
        $course = $entityManager->getRepository(Course::class)->find($idCourse);

        if (!$course) {
            throw $this->createNotFoundException('Курс не найден');
        }

        return $this->render('course/show.html.twig', [
            'course' => $course,
        ]);
    }

    #[Route('/courses', name: 'course_show_all')]
    public function show_all(EntityManagerInterface $entityManager): Response
    {
        $course = $entityManager->getRepository(Course::class)->findAll();

        if (!$course) {
            throw $this->createNotFoundException('Курсы не найден');
        }

        return $this->render('course/show_all.html.twig', [
            'course' => $course,
        ]);
    }
}
