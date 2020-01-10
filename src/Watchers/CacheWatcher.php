<?php

namespace PDFfiller\TelescopeClient\Watchers;

use Illuminate\Support\Str;
use PDFfiller\TelescopeClient\Telescope;
use PDFfiller\TelescopeClient\IncomingEntry;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyForgotten;

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
     * Record a cache key was found.
     *
     * @param  \Illuminate\Cache\Events\CacheHit  $event
     * @return void
     */
    public function recordCacheHit(CacheHit $event)
    {
        if (! Telescope::isRecording() || $this->shouldIgnore($event)) {
            return;
        }

        Telescope::recordCache(IncomingEntry::make([
            'type' => 'hit',
            'key' => $event->key,
            'value' => $event->value,
        ]));
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
     * Record a cache key was updated.
     *
     * @param  \Illuminate\Cache\Events\KeyWritten  $event
     * @return void
     */
    public function recordKeyWritten(KeyWritten $event)
    {
        if (! Telescope::isRecording() || $this->shouldIgnore($event)) {
            return;
        }

        Telescope::recordCache(IncomingEntry::make([
            'type' => 'set',
            'key' => $event->key,
            'value' => $event->value,
            'expiration' => $event->minutes,
        ]));
    }

    /**
     * Record a cache key was forgotten / removed.
     *
     * @param  \Illuminate\Cache\Events\KeyForgotten  $event
     * @return void
     */
    public function recordKeyForgotten(KeyForgotten $event)
    {
        if (! Telescope::isRecording() || $this->shouldIgnore($event)) {
            return;
        }

        Telescope::recordCache(IncomingEntry::make([
            'type' => 'forget',
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
