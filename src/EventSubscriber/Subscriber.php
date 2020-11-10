<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class Subscriber implements EventSubscriberInterface
{
    public function onUser($event)
    {
        // ...
    }

    public static function getSubscribedEvents()
    {
        return [
            'User' => 'onUser',
        ];
    }
}
