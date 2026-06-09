<?php

namespace CM\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class JSTestsController extends AbstractController
{    
    public function testJSAction()
    {    	
        return $this->render('CMAppBundle:JSTests:tests.html.twig', array());
    }
}
