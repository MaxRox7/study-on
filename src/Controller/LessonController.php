<?php

namespace App\Controller;

use App\Service\LessonService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class LessonController extends AbstractController
{
    private LessonService $lessonService;
    private EntityManagerInterface $entityManager;

    public function __construct(LessonService $lessonService, EntityManagerInterface $entityManager)
    {
        $this->lessonService = $lessonService;
        $this->entityManager = $entityManager;
    }

    #[Route('/lesson/{idLesson}', name: 'lesson_show')]
    #[IsGranted('ROLE_USER')]
    public function show(int $idLesson): Response
    {
        $lesson = $this->lessonService->getLesson($idLesson);
        if (!$lesson) {
            throw $this->createNotFoundException('Курс не найден');
        }
        $this->lessonService->canUserAccessLesson($lesson, $this->getUser());
        return $this->render('lesson/show.html.twig', [
            'lesson' => $lesson,
            'course' => $lesson->getCourse(),
        ]);
    }

    #[Route('/lessons/delete/{idLesson}', name: 'lesson_delete', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function delete(int $idLesson): Response
    {
        $courseId = $this->lessonService->deleteLesson($idLesson);
        if ($courseId === null) {
            throw $this->createNotFoundException('Урок не найден');
        }
        return $this->redirectToRoute('course_show', ['idCourse' => $courseId]);
    }

    #[Route('/lesson/{idLesson}/edit', name: 'lesson_edit')]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function edit(int $idLesson, Request $request): Response
    {
        $lesson = $this->lessonService->getLesson($idLesson);
        if (!$lesson) {
            throw $this->createNotFoundException('Урок не найден');
        }
        $form = $this->createForm(\App\Form\LessonType::class, $lesson);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Урок успешно обновлен!');
            return $this->redirectToRoute('course_show', ['idCourse' => $lesson->getCourse()->getIdCourse()]);
        }
        return $this->render('lesson/edit.html.twig', [
            'form' => $form->createView(),
            'lesson' => $lesson,
        ]);
    }
}
