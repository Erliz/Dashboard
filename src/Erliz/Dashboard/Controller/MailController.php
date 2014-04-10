<?php
/**
 * @author Stanislav Vetlovskiy
 * @date   07.04.14
 */

namespace Erliz\Dashboard\Controller;


use Erliz\Dashboard\Service\MailService;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class MailController
{
    public function indexAction(Request $request, Application $app)
    {
        return $app['twig']->render(
            'Mail/form.twig',
            array(
                'content' => $app['service.flash_bag']->getContent(),
                'flash_bag' => $app['service.flash_bag']->getMessages()
            )
        );
    }

    public function sendAction(Request $request, Application $app)
    {
        /** @var MailService $mailService */
        $mailService = $app['service.mail'];
        $recipients = $mailService->getRecipients($request->get('recipients'));

        $theme = $request->get('theme');
        $body = $request->get('body');
        $sendError = false;
        if (!$recipients) {
            $app['service.flash_bag']->error('Не заполнено кому отправлять');
            $sendError = true;
        }
        if (!$theme) {
            $app['service.flash_bag']->error('Не заполнена Тема письма');
            $sendError = true;
        }
        if (!$body) {
            $app['service.flash_bag']->error('Не заполнен Текст письма');
            $sendError = true;
        }

        if(!$sendError && $mailService->send($recipients, $request->get('theme'), $request->get('body'))) {
            $app['service.flash_bag']->success(
                sprintf('Письмо с темой "%s" было отправлено на %d адреса', $theme, count($recipients))
            );
        } else {
            $app['service.flash_bag']->error('Не удалось отправить письмо');
        }
        $app['service.flash_bag']->content(
            array('theme' => $theme, 'body' => $body, 'recipients' => join(';', $recipients))
        );
        return $app->redirect($app['url_generator']->generate('mail_index'));
    }
}
