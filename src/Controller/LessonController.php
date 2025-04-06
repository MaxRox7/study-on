<?php

namespace App\Controller;

use App\Entity\Lesson;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LessonController extends AbstractController
{
    #[Route('/lesson/{idLesson}', name: 'lesson_show')]
    public function show(EntityManagerInterface $entityManager, int $idLesson): Response
    {
        $lesson = $entityManager->getRepository(Lesson::class)->find($idLesson);

        if (!$lesson) {
            throw $this->createNotFoundException('Курс не найден');
        }

        return $this->render('lesson/show.html.twig', [
            'lesson' => $lesson,
            'course' => $lesson->getCourse(),
        ]);
    }
}
