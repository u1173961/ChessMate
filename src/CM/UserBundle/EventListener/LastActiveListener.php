<?php
namespace CM\UserBundle\EventListener;

use FOS\UserBundle\Model\UserInterface;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\HttpKernel;
use FOS\UserBundle\Model\UserManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

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
    * Update user on each request
    */
    public function onCoreController(FilterControllerEvent $event)
    {
        // only listen for MASTER_REQUESTs
        if ($event->getRequestType() !== HttpKernel::MASTER_REQUEST) {
            return;
        }

        $securityToken = $this->tokenStorage->getToken();

        // Check if request is from user
        if ($securityToken) {
            $user = $securityToken->getUser();
            //update last active time (every three mins) 
            if ($user instanceof UserInterface && !$user->getIsOnline()) {
                $user->setLastActiveTime(new \DateTime());
                $this->userManager->updateUser($user);
            }
        }
    }
}
