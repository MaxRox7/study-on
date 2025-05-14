<?php
// src/Controller/RegistrationController.php
namespace App\Controller;

use App\DTO\RegistrationDTO;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Service\BillingClient;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(Request $request, BillingClient $billingClient, ValidatorInterface $validator): Response
    {
        $dto = new RegistrationDTO();  // создаем новый объект DTO

        if ($request->isMethod('POST')) {
            $dto->setEmail($request->request->get('email'));
            $dto->setPassword($request->request->get('password'));
            $dto->setConfirmPassword($request->request->get('confirm_password'));

            // Валидация
            $errors = $validator->validate($dto);

            if (count($errors) > 0) {
                return $this->render('registration/register.html.twig', [
                    'dto' => $dto,
                    'errors' => $errors,
                ]);
            }

            // Процесс регистрации через BillingClient или другая логика
            try {
                $billingClient->register([
                    'email' => $dto->getEmail(),
                    'password' => $dto->getPassword(),
                ]);

                $this->addFlash('success', 'Регистрация успешна. Войдите в систему.');
                return $this->redirectToRoute('app_login');
            } catch (\Exception $e) {
                $this->addFlash('danger', 'Ошибка регистрации: ' . $e->getMessage());
            }
        }

        return $this->render('registration/register.html.twig', [
            'dto' => $dto,
            'errors' => [],
        ]);
    }
}
