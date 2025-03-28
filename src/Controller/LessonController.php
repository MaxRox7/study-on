<?php

// src/Controller/LessonController.php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Lesson;
use App\Form\LessonType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LessonController extends AbstractController
{
    /**
     * @Route("/lessons/{id}", name="lesson_show", requirements={"id"="\d+"})
     */
    public function show(Lesson $lesson): Response
    {
        return $this->render('lesson/show.html.twig', [
            'lesson' => $lesson,
            'course' => $lesson->getCourse(),
        ]);
    }

    /**
     * @Route("/lessons/create/{courseId}", name="lesson_create")
     */
    public function create(Request $request, $courseId): Response
    {
        $course = $this->getDoctrine()->getRepository(Course::class)->find($courseId);
        $lesson = new Lesson();
        $lesson->setCourse($course);

        $form = $this->createForm(LessonType::class, $lesson);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($lesson);
            $entityManager->flush();

            return $this->redirectToRoute('course_show', ['id' => $course->getId()]);
        }

        return $this->render('lesson/create.html.twig', [
            'form' => $form->createView(),
            'course' => $course,
        ]);
    }
}
