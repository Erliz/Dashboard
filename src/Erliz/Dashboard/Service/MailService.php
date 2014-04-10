<?php
/**
 * @author Stanislav Vetlovskiy
 * @date   09.04.14
 */

namespace Erliz\Dashboard\Service;


class MailService
{
    public function send(array $recipients, $theme, $body)
    {
        $header = "Content-type: text/html; charset=\"utf-8\"\r\n";
        $header .= "From: Mailer test server\r\n";
        return mail(join(',', $recipients), $theme, $body, $header);
    }

    /**
     * @param $string
     *
     * @return array
     */
    public function getRecipients($string)
    {
        $string = str_replace(' ', '', $string);
        $recipient = explode(';', $string);
        $recipient = array_filter(
            $recipient,
            function ($element){ return !!$element && strpos($element, '@') !== false; }
        );
        return $recipient;
    }
}
