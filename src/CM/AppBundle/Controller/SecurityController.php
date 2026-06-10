<?php

namespace CM\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    private $authenticationUtils;

    public function __construct(AuthenticationUtils $authenticationUtils)
    {
        $this->authenticationUtils = $authenticationUtils;
    }

    public function loginAction()
    {
        return $this->render('Security/login.html.twig', array(
            'last_username' => $this->authenticationUtils->getLastUsername(),
            'error' => $this->authenticationUtils->getLastAuthenticationError(),
        ));
    }
}
