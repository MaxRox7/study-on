<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Lesson;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CourseController extends AbstractController
{
    #[Route('/courses/{idCourse}', name: 'course_show')]
    public function show(int $idCourse, EntityManagerInterface $entityManager, Request $request): Response
    {
        // Получаем курс по его ID
        $course = $entityManager->getRepository(Course::class)->find($idCourse);

        if (!$course) {
            throw $this->createNotFoundException('Курс не найден');
        }

        // Создаем новый урок
        $lesson = new Lesson();
        $lesson->setCourse($course);

        // Создаем форму для добавления урока
        $form = $this->createFormBuilder($lesson)
            ->add('titleLesson')
            ->add('content')
            ->add('orderNumber')
            ->add('save', SubmitType::class, ['label' => 'Добавить урок'])
            ->getForm();

        // Обрабатываем запрос, если форма отправлена
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($lesson);
            $entityManager->flush();

            return $this->redirectToRoute('course_show', ['idCourse' => $idCourse]);
        }

        $lessons = $course->getLessons(); // This retrieves all lessons related to the course

        // Отображаем страницу курса с формой добавления урока
        return $this->render('course/show.html.twig', [
            'course' => $course,
            'form' => $form->createView(),
            'lessons' => $lessons, // Pass the lessons to the template
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

    #[Route('/course/create', name: 'course_create')]
    public function create_course(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            // Получаем данные из формыz
            $symbolCode = $request->request->get('symbolCode');
            $titleCourse = $request->request->get('title_course');
            $description = $request->request->get('description');

            // Проверка на пустые обязательные поля
            if (empty($symbolCode) || empty($titleCourse)) {
                return new Response('Не все обязательные поля были заполнены', Response::HTTP_BAD_REQUEST);
            }

            // Создаём новый курс
            $course = new Course();
            $course->setSymbolCode($symbolCode);
            $course->setTitleCourse($titleCourse);
            $course->setDescription($description);

            // Сохраняем курс
            $entityManager->persist($course);
            $entityManager->flush();

            // Редирект на страницу курса
            return $this->redirectToRoute('course_show', ['idCourse' => $course->getIdCourse()]);
        }

        // Если это GET-запрос, то показываем форму
        return $this->render('course/create.html.twig');
    }

    // App\Controller\CourseController.php

    #[Route('/courses/{idCourse}/delete', name: 'course_delete')]
    public function delete_course(int $idCourse, EntityManagerInterface $entityManager): Response
    {
        // Ищем курс по его ID
        $course = $entityManager->getRepository(Course::class)->find($idCourse);

        // Если курс не найден, выбрасываем исключение
        if (!$course) {
            throw $this->createNotFoundException('Курс не найден');
        }

        // Удаляем курс (и все связанные уроки из-за каскадного удаления)
        $entityManager->remove($course);
        $entityManager->flush();

        // Перенаправляем на страницу со списком всех курсов
        return $this->redirectToRoute('course_show_all');
    }

    #[Route('/courses/{idCourse}/edit', name: 'course_edit')]
    public function edit(int $idCourse, EntityManagerInterface $entityManager, Request $request): Response
    {
        $course = $entityManager->getRepository(Course::class)->find($idCourse);

        if (!$course) {
            throw $this->createNotFoundException('Курс не найден');
        }

        $form = $this->createFormBuilder($course)
            ->add('titleCourse')
            ->add('symbolCode')
            ->add('description')
            ->add('save', SubmitType::class, ['label' => 'Сохранить изменения'])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('course_show', ['idCourse' => $idCourse]);
        }

        return $this->render('course/edit.html.twig', [
            'form' => $form->createView(),
            'course' => $course,
        ]);
    }
}
