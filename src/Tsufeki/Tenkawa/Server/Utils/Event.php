<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Utils;

use Evenement\EventEmitterInterface;
use Recoil\Listener;
use Recoil\Recoil;

final class Event
{
    /**
     * @param EventEmitterInterface $emitter
     * @param string[]|string       $events
     * @param string[]|string       $errorEvents
     *
     * @resolve array Event args.
     */
    public static function first(EventEmitterInterface $emitter, $events = [], $errorEvents = []): \Generator
    {
        $events = !is_array($events) ? [$events] : $events;
        $errorEvents = !is_array($errorEvents) ? [$errorEvents] : $errorEvents;

        return yield Recoil::suspend(function (Listener $strand) use ($emitter, $events, $errorEvents) {
            $listener = function (...$args) use ($strand, &$removeListeners) {
                $removeListeners();
                $strand->send($args);
            };

            $errorListener = function ($error) use ($strand, &$removeListeners) {
                $removeListeners();
                $strand->throw($error);
            };

            $removeListeners = function () use ($emitter, $events, $errorEvents, $listener, $errorListener) {
                foreach ($events as $event) {
                    $emitter->removeListener($event, $listener);
                }
                foreach ($errorEvents as $event) {
                    $emitter->removeListener($event, $errorListener);
                }
            };

            foreach ($events as $event) {
                $emitter->on($event, $listener);
            }
            foreach ($errorEvents as $event) {
                $emitter->on($event, $errorListener);
            }
        });
    }

    // @codeCoverageIgnoreStart
    private function __construct()
    {
    }

    // @codeCoverageIgnoreEnd
}
