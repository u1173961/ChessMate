<?php

namespace CM\UserBundle\EventListener;

use CM\UserBundle\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

class LastActiveListener
{
    private $tokenStorage;
    private $entityManager;

    public function __construct(TokenStorageInterface $tokenStorage, EntityManagerInterface $entityManager)
    {
        $this->tokenStorage = $tokenStorage;
        $this->entityManager = $entityManager;
    }

    /**
     * Update the user on each main request.
     */
    public function onKernelController(ControllerEvent $event)
    {
        if (method_exists($event, 'isMainRequest')) {
            if (!$event->isMainRequest()) {
                return;
            }
        } elseif (!$event->isMasterRequest()) {
            return;
        }

        $securityToken = $this->tokenStorage->getToken();

        if ($securityToken) {
            $user = $securityToken->getUser();

            if ($user instanceof User && !$user->getIsOnline()) {
                $user->setLastActiveTime(new \DateTime());
                $this->entityManager->flush();
            }
        }
    }
}
