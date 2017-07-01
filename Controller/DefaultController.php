<?php

namespace MaximMV\Bundle\UniversalFilterBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction()
    {
        return $this->render('UniversalFilterBundle:Default:index.html.twig');
    }
}
