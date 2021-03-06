<?php

namespace PDFfiller\TelescopeClient\Watchers;

use Exception;
use Illuminate\Support\Arr;
use PDFfiller\TelescopeClient\Telescope;
use PDFfiller\TelescopeClient\IncomingEntry;
use Illuminate\Log\Events\MessageLogged;

class LogWatcher extends Watcher
{
    /**
     * Register the watcher.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return void
     */
    public function register($app)
    {
        $app['events']->listen(MessageLogged::class, [$this, 'recordLog']);
    }

    /**
     * Record a message was logged.
     *
     * @param  \Illuminate\Log\Events\MessageLogged  $event
     * @return void
     */
    public function recordLog(MessageLogged $event)
    {
        if (! Telescope::isRecording() || $this->shouldIgnore($event)) {
            return;
        }

        if (! in_array($event->level, [
            'critical',
            'error',
        ])) {
            return;
        }

        Telescope::recordLog(
            IncomingEntry::make([
                'level' => $event->level,
                'message' => $event->message,
                'context' => Arr::except($event->context, ['telescope']),
            ])->tags($this->tags($event))
        );
    }

    /**
     * Extract tags from the given event.
     *
     * @param  \Illuminate\Log\Events\MessageLogged  $event
     * @return array
     */
    private function tags($event)
    {
        return $event->context['telescope'] ?? [];
    }

    /**
     * Determine if the event should be ignored.
     *
     * @param  mixed  $event
     * @return bool
     */
    private function shouldIgnore($event)
    {
        return isset($event->context['exception']) &&
            $event->context['exception'] instanceof Exception;
    }
}
