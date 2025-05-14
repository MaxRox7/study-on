<?php

namespace App\Security;

use App\Service\BillingClient;
use Psr\Log\LoggerInterface; // Добавляем зависимость для логирования
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use App\Exception\BillingUnavailableException;

class UserAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    private LoggerInterface $logger; // Добавляем свойство для логера

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private BillingClient $billingClient,
        LoggerInterface $logger // Внедряем зависимость
    ) {
        $this->logger = $logger; // Инициализируем логер
    }

    public function authenticate(Request $request): Passport
    {
        // Логируем весь запрос, чтобы увидеть, что в нем содержится
        $this->logger->info('Request data: ' . json_encode($request->request->all()));
    
        $email = $request->request->get('email');
        $password = $request->request->get('password');
        
        // Логируем email и пароль для отладки
        $this->logger->info('Email: ' . $email . ', Password: ' . $password);
    
        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);
    
        try {
            // Логируем попытку аутентификации
            $this->logger->info('Authenticating user: ' . $email);
    
            // Получаем токен по логину и паролю
            $authResponse = $this->billingClient->auth([
                'email' => $email,
                'password' => $password,
            ]);
    
            // Логируем ответ сервера
            $this->logger->info('Auth response: ' . json_encode($authResponse));
    
            if (empty($authResponse['token'])) {
                throw new CustomUserMessageAuthenticationException('Неверный email или пароль.');
            }
    
            $token = $authResponse['token'];
    
            // Лямбда-функция для получения пользователя
            $loadUser = function (string $userIdentifier) use ($token): User {
                $userData = $this->billingClient->getCurrentUser($token);
                $this->logger->info('User data: ' . json_encode($userData));
    
                $user = new User();
                $user->setEmail($userData['email'])
                    ->setRoles($userData['roles'])
                    ->setApiToken($token);
    
                return $user;
            };
    
            return new SelfValidatingPassport(
                new UserBadge($token, $loadUser),
                [
                    new CsrfTokenBadge('authenticate', $request->request->get('_csrf_token')),
                    new RememberMeBadge(),
                ]
            );
        } catch (BillingUnavailableException $exception) {
            throw new CustomUserMessageAuthenticationException('Сервис временно недоступен. Попробуйте позже.');
        } catch (\Exception $e) {
            // Логируем ошибку
            $this->logger->error('Authentication failed: ' . $e->getMessage());
            throw new CustomUserMessageAuthenticationException('Неверный email или пароль.');
        }
    }
    

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
    // Получаем токен из User
        $user = $token->getUser();
        $apiToken = method_exists($user, 'getApiToken') ? $user->getApiToken() : null;

    // Сохраняем в flash
        $request->getSession()->getFlashBag()->add('api_token', $apiToken);

    // Редиректим на вашу страницу, например, на главный роут
        return new RedirectResponse($this->urlGenerator->generate('course_show_all'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
