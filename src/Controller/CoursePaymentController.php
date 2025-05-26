<?php

namespace App\Controller;

use App\Service\BillingClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

class CoursePaymentController extends AbstractController
{
    #[Route('/courses/{code}/pay', name: 'course_pay', methods: ['POST'])]
    public function pay(string $code, BillingClient $billingClient, Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('danger', 'Необходимо войти в систему для оплаты курса.');
            return $this->redirectToRoute('course_show_all');
        }

        try {
            $result = $billingClient->request(
                method: 'POST',
                url: '/api/v1/courses/' . $code . '/pay',
                token: $user->getApiToken()
            );
            $this->addFlash('success', 'Курс успешно оплачен!');
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Ошибка оплаты: ' . $e->getMessage());
        }

        // Пытаемся найти idCourse для редиректа на страницу курса
        $idCourse = $request->query->get('idCourse');
        if (!$idCourse) {
            // Если не передан, ищем по коду
            $course = $entityManager->getRepository(\App\Entity\Course::class)->findOneBy(['symbolCode' => $code]);
            $idCourse = $course ? $course->getIdCourse() : null;
        }
        if ($idCourse) {
            return $this->redirectToRoute('course_show', ['idCourse' => $idCourse]);
        }
        return $this->redirectToRoute('course_show_all');
    }
}
