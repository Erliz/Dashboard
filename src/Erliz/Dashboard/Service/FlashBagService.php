<?php

namespace Erliz\Dashboard\Service;

use Silex\Application;
use Symfony\Component\Debug\Exception\DummyException;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;

/**
 * FlashBagService.
 *
 * @author Stanislav Vetlovskiy <s.vetlovskiy@corp.mail.ru>
 */ 
class FlashBagService
{
    /** @var FlashBag */
    private $flashBag;

    public function __construct(FlashBag $flashBag)
    {
        $this->flashBag = $flashBag;
    }

    public function error($message)
    {
        $this->danger($message);
    }

    public function danger($message)
    {
        $this->flashBag->add('danger', $message);
    }

    public function success($message)
    {
        $this->flashBag->add('success', $message);
    }

    public function content(array $content, $force = false)
    {
        if($this->flashBag->has('content') && !$force){
            throw new DummyException('Flash Bag content already set');
        }

        $this->flashBag->add('content', $content);
    }

    public function getMessages()
    {
        return array(
            'success' => $this->flashBag->get('success'),
            'danger' => $this->flashBag->get('danger')
        );
    }
    public function getContent()
    {
        return array_pop($this->flashBag->get('content'));
    }
}
