<?php

namespace App\Controller;

use App\DTO\RegistrationDTO;
use App\Exception\BillingUnavailableException;
use App\Security\User;
use App\Security\UserAuthenticator;
use App\Service\BillingClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface; // можно, но в контроллере AbstractController есть isGranted()

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
        // ✅ Проверка: если пользователь уже авторизован — редиректим
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('app_profile');
        }
        $dto = new RegistrationDTO();

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

            try {
                // Регистрация в Billing-сервисе
                $billingClient->register([
                    'email' => $dto->getEmail(),
                    'password' => $dto->getPassword(),
                ]);

                // Аутентификация после успешной регистрации
                $authResponse = $billingClient->auth([
                    'email' => $dto->getEmail(),
                    'password' => $dto->getPassword(),
                ]);

                if (empty($authResponse['token'])) {
                    throw new \Exception('Ошибка авторизации после регистрации.');
                }

                // Получаем данные пользователя
                $userData = $billingClient->getCurrentUser($authResponse['token']);

                $user = new User();
                $user->setEmail($userData['email'])
                     ->setRoles($userData['roles'])
                     ->setApiToken($authResponse['token']);

                // Аутентифицируем пользователя
                return $userAuthenticator->authenticateUser(
                    $user,
                    $authenticator,
                    $request
                );
            } catch (BillingUnavailableException $e) {
                $this->addFlash('danger', 'Сервис временно недоступен. Попробуйте зарегистрироваться позднее.');
            } catch (\Exception $e) {
                $this->addFlash('danger', 'Ошибка регистрации: ' . $e->getMessage());
            }
        }

        // GET-запрос или повторный рендер при ошибке
        return $this->render('registration/register.html.twig', [
            'dto' => $dto,
            'errors' => [],
        ]);
    }
}
