<?php

namespace CM\UserBundle\EventListener;

use FOS\UserBundle\Model\UserInterface;
use FOS\UserBundle\Model\UserManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

class LastActiveListener
{
    private $tokenStorage;
    private $userManager;

    public function __construct(TokenStorageInterface $tokenStorage, UserManagerInterface $userManager)
    {
        $this->tokenStorage = $tokenStorage;
        $this->userManager = $userManager;
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

            if ($user instanceof UserInterface && !$user->getIsOnline()) {
                $user->setLastActiveTime(new \DateTime());
                $this->userManager->updateUser($user);
            }
        }
    }
}
