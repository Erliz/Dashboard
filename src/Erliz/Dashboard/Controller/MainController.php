<?php
/**
 * @author Stanislav Vetlovskiy
 * @date   07.04.14
 */

namespace Erliz\Dashboard\Controller;


use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class MainController
{
    public function indexAction(Request $request, Application $app)
    {
        return $app['twig']->render(
            'Main/index.twig',
            array()
        );
    }
}
