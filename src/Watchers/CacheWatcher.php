<?php

namespace PDFfiller\TelescopeClient\Watchers;

use Illuminate\Support\Str;
use PDFfiller\TelescopeClient\Telescope;
use PDFfiller\TelescopeClient\IncomingEntry;
use Illuminate\Cache\Events\CacheMissed;

class CacheWatcher extends Watcher
{
    /**
     * Register the watcher.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return void
     */
    public function register($app)
    {
        $app['events']->listen(CacheMissed::class, [$this, 'recordCacheMissed']);
    }

    /**
     * Record a missing cache key.
     *
     * @param  \Illuminate\Cache\Events\CacheMissed  $event
     * @return void
     */
    public function recordCacheMissed(CacheMissed $event)
    {
        if (! Telescope::isRecording() || $this->shouldIgnore($event)) {
            return;
        }

        Telescope::recordCache(IncomingEntry::make([
            'type' => 'missed',
            'key' => $event->key,
        ]));
    }

    /**
     * Determine if the event should be ignored.
     *
     * @param  mixed  $event
     * @return bool
     */
    private function shouldIgnore($event)
    {
        return Str::is([
            'illuminate:queue:restart',
            'framework/schedule*',
            'telescope:*',
        ], $event->key);
    }
}
