<?php

namespace App\Controller;

use App\Entity\Lesson;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class LessonController extends AbstractController
{
    #[Route('/lesson/{idLesson}', name: 'lesson_show')]
    #[IsGranted('ROLE_USER')]
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

    #[Route('/lessons/delete/{idLesson}', name: 'lesson_delete', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function delete(int $idLesson, EntityManagerInterface $entityManager): Response
    {
        $lesson = $entityManager->getRepository(Lesson::class)->find($idLesson);

        if (!$lesson) {
            throw $this->createNotFoundException('Урок не найден');
        }

        $courseId = $lesson->getCourse()->getIdCourse();

        $entityManager->remove($lesson);
        $entityManager->flush();

        return $this->redirectToRoute('course_show', ['idCourse' => $courseId]);
    }

    #[Route('/lesson/{idLesson}/edit', name: 'lesson_edit')]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function edit(int $idLesson, EntityManagerInterface $entityManager, Request $request, ValidatorInterface $validator): Response
    {
        $lesson = $entityManager->getRepository(Lesson::class)->find($idLesson);

        if (!$lesson) {
            throw $this->createNotFoundException('Урок не найден');
        }

        $form = $this->createFormBuilder($lesson)
            ->add('titleLesson')
            ->add('content')
            ->add('orderNumber')
            ->add('save', SubmitType::class, ['label' => 'Сохранить изменения'])
            ->getForm();

        $errors = $validator->validate($lesson);

        if (count($errors) > 0) {
            /*
             * Использует метод __toString в переменной $errors, которая является объектом
             * ConstraintViolationList. Это дает хорошую строку для отладки.
             */
            $errorsString = (string) $errors;

            return new Response($errorsString);
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('course_show', ['idCourse' => $lesson->getCourse()->getIdCourse()]);
        }

        return $this->render('lesson/edit.html.twig', [
            'form' => $form->createView(),
            'lesson' => $lesson,
        ]);
    }
}
