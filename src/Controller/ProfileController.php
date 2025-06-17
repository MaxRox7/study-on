<?php
// src/Controller/ProfileController.php
namespace App\Controller;

use App\Repository\CourseRepository;
use App\Service\BillingClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    public function profile(BillingClient $billingClient, CourseRepository $courseRepository): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        try {
            $data = $billingClient->getCurrentUser($user->getApiToken());
            $transactions = $billingClient->getTransactions($user->getApiToken());
        } catch (\Throwable $e) {
            return $this->render('profile/billing_unavailable.html.twig');
        }

        // Создаём карту кодов курсов к их ID
        $courseCodes = [];
        foreach ($transactions as $transaction) {
            if (isset($transaction['course_code'])) {
                $course = $courseRepository->findOneBySymbolCode(trim($transaction['course_code']));
                $courseCodes[$transaction['course_code']] = $course ? $course->getIdCourse() : null;
            }
        }

        return $this->render('profile/index.html.twig', [
            'email' => $data['email'],
            'role' => in_array('ROLE_SUPER_ADMIN', $data['roles']) ? 'Администратор' : 'Пользователь',
            'balance' => $data['balance'],
            'transactions' => $transactions,
            'courseCodes' => $courseCodes,
        ]);
    }
}
