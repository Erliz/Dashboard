<?php
/**
 * @author Stanislav Vetlovskiy
 * @date   07.04.14
 */

error_reporting(7);
error_reporting (E_ALL);

require_once __DIR__.'/../vendor/autoload.php';

$app = new Silex\Application();
$app['debug'] = true;

// Providers
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/../src/Erliz/Dashboard/Resources/views/',
));
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());
$app->register(new Silex\Provider\SessionServiceProvider());

// Services
$app['service.mail'] = $app->share(function(){ return new \Erliz\Dashboard\Service\MailService(); });
$app['service.flash_bag'] = $app->share(
    function () use ($app){ return new \Erliz\Dashboard\Service\FlashBagService($app['session']->getFlashBag()); }
);

/**
 * Routing
 */
$app->get('/',               'Erliz\\Dashboard\\Controller\\MainController::indexAction')->bind('index');
$app->get('/mail/',               'Erliz\\Dashboard\\Controller\\MailController::indexAction')->bind('mail_index');
$app->post('/mail/send/',               'Erliz\\Dashboard\\Controller\\MailController::sendAction')->bind('mail_send');

$app->run();
