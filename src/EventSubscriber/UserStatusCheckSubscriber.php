<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Bundle\SecurityBundle\Security;

class UserStatusCheckSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Security $security,
        private UrlGeneratorInterface $urlGenerator,
        private EntityManagerInterface $entityManager
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 7],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        if (in_array($route, ['login', 'register', 'verify_email', 'logout'])) {
            return;
        }

        $user = $this->security->getUser();

        if ($user instanceof User) {
            $this->entityManager->refresh($user);

            if ($user->isBlocked()) {
                if ($request->hasSession()) {
                    $request->getSession()->set('_flash_blocked', true);
                }

                $this->security->logout(false);
                $event->setResponse(new RedirectResponse($this->urlGenerator->generate('login') . '?blocked=1'));
            }
        }
    }
}
