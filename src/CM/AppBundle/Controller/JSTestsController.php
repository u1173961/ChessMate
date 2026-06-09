<?php

namespace CM\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class JSTestsController extends Controller
{    
    public function testJSAction()
    {    	
        return $this->render('CMAppBundle:JSTests:tests.html.twig', array());
    }
}
