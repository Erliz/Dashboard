<?php
/**
 * @author Stanislav Vetlovskiy
 * @date   07.04.14
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = new Silex\Application();
$app['config'] = Symfony\Component\Yaml\Yaml::parse(__DIR__ . '/../src/Erliz/Dashboard/Resources/configs/settings.yml');
$app['debug'] = $app['config']['debug'];

if ($app['debug']) {
    error_reporting(7);
    error_reporting (E_ALL);
}

// Providers
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/../src/Erliz/Dashboard/Resources/views/',
));
$app['twig'] = $app->share($app->extend('twig', function($twig) {
    /** @var Twig_Environment $twig */
    $twig->addExtension(new \Twig_Extensions_Extension_Text());
    $twig->getExtension('core')->setNumberFormat(0, '.', ' ');
    return $twig;
}));
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());
$app->register(new Silex\Provider\SessionServiceProvider());

// Services
$app['service.mail'] = $app->share(function() { return new \Erliz\Dashboard\Service\MailService(); });
$app['service.jira'] = $app->share(function() use ($app) {
        $config = $app['config']['jira'];
        return new \Erliz\Dashboard\Service\JiraService(
            $config['login'],
            $config['password'],
            $config['host'],
            $config['ssl']
        );
    });
$app['service.flash_bag'] = $app->share(
    function () use ($app){ return new \Erliz\Dashboard\Service\FlashBagService($app['session']->getFlashBag()); }
);

/**
 * Routing
 */
$app->get('/',                    'Erliz\\Dashboard\\Controller\\MainController::indexAction')->bind('index');

$app->get('/mail/',               'Erliz\\Dashboard\\Controller\\MailController::indexAction')->bind('mail_index');
$app->post('/mail/send/',         'Erliz\\Dashboard\\Controller\\MailController::sendAction')->bind('mail_send');

$app->get('/agile/',               'Erliz\\Dashboard\\Controller\\AgileController::indexAction')->bind('agile_index');
$app->get('/agile/issue/{key}/',   'Erliz\\Dashboard\\Controller\\AgileController::issueAction')->bind('agile_issue');
$app->match('/agile/release/new/', 'Erliz\\Dashboard\\Controller\\AgileController::newReleaseAction')->bind('agile_release_new');
$app->get('/agile/release/{key}/', 'Erliz\\Dashboard\\Controller\\AgileController::releaseAction')->bind('agile_release');

$app->run();
