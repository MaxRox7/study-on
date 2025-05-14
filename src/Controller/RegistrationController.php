<?php

// src/Controller/RegistrationController.php

namespace App\Controller;

use App\DTO\RegistrationDTO;
use App\Security\UserAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Service\BillingClient;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use App\Security\User;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        BillingClient $billingClient,
        ValidatorInterface $validator,
        UserAuthenticatorInterface $userAuthenticator,
        UserAuthenticator $authenticator
    ): Response {
        $dto = new RegistrationDTO();

        if ($request->isMethod('POST')) {
            $dto->setEmail($request->request->get('email'));
            $dto->setPassword($request->request->get('password'));
            $dto->setConfirmPassword($request->request->get('confirm_password'));

            $errors = $validator->validate($dto);

            if (count($errors) > 0) {
                return $this->render('registration/register.html.twig', [
                    'dto' => $dto,
                    'errors' => $errors,
                ]);
            }

            try {
                $billingClient->register([
                    'email' => $dto->getEmail(),
                    'password' => $dto->getPassword(),
                ]);

                // Аутентификация после регистрации
                $authResponse = $billingClient->auth([
                    'email' => $dto->getEmail(),
                    'password' => $dto->getPassword(),
                ]);

                if (empty($authResponse['token'])) {
                    throw new \Exception('Ошибка авторизации после регистрации');
                }

                $userData = $billingClient->getCurrentUser($authResponse['token']);

                $user = new User();
                $user->setEmail($userData['email'])
                     ->setRoles($userData['roles'])
                     ->setApiToken($authResponse['token']);

                return $userAuthenticator->authenticateUser(
                    $user,
                    $authenticator,
                    $request
                );
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
