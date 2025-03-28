<?php

// src/Controller/CourseController.php

namespace App\Controller;

use App\Entity\Course;
use App\Form\CourseType;
use App\Repository\CourseRepository;
use Doctrine\ORM\EntityManagerInterface;  // Импортируем интерфейс EntityManager
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CourseController extends AbstractController
{
    /**
     * @Route("/courses", name="course_index")
     */
    public function index(CourseRepository $courseRepository): Response
    {
        $courses = $courseRepository->findAll();

        return $this->render('course/index.html.twig', [
            'courses' => $courses,
        ]);
    }

    /**
     * @Route("/courses/{id}", name="course_show", requirements={"id"="\d+"})
     */
    public function show(Course $course): Response
    {
        return $this->render('course/show.html.twig', [
            'course' => $course,
        ]);
    }

    /**
     * @Route("/courses/create", name="course_create")
     */
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        $course = new Course();
        $form = $this->createForm(CourseType::class, $course);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Используем инъекцию зависимостей для EntityManager
            $entityManager->persist($course);
            $entityManager->flush();

            return $this->redirectToRoute('course_index');
        }

        return $this->render('course/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
