<?php

namespace PDFfiller\TelescopeClient;

use Closure;
use Exception;
use Throwable;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Contracts\Debug\ExceptionHandler;
use PDFfiller\TelescopeClient\Contracts\EntriesRepository;
use PDFfiller\TelescopeClient\Contracts\TerminableRepository;
use Symfony\Component\Debug\Exception\FatalThrowableError;

class Telescope
{
    use ListensForStorageOpportunities,
        RegistersWatchers;

    /**
     * The callbacks that filter the entries that should be recorded.
     *
     * @var array
     */
    public static $filterUsing = [];

    /**
     * The callbacks that filter the batches that should be recorded.
     *
     * @var array
     */
    public static $filterBatchUsing = [];

    /**
     * The callback executed after queuing a new entry.
     *
     * @var \Closure
     */
    public static $afterRecordingHook;

    /**
     * The callback that adds tags to the record.
     *
     * @var \Closure
     */
    public static $tagUsing;

    /**
     * The list of queued entries to be stored.
     *
     * @var array
     */
    public static $entriesQueue = [];

    /**
     * The list of hidden request headers.
     *
     * @var array
     */
    public static $hiddenRequestHeaders = [
        'authorization',
    ];

    /**
     * The list of hidden request parameters.
     *
     * @var array
     */
    public static $hiddenRequestParameters = [
        'password',
        'password_confirmation',
    ];

    /**
     * The list of hidden response parameters.
     *
     * @var array
     */
    public static $hiddenResponseParameters = [];

    /**
     * Indicates if Telescope should record entries.
     *
     * @var bool
     */
    public static $shouldRecord = false;

    /**
     * Indicates if Telescope migrations will be run.
     *
     * @var bool
     */
    public static $runsMigrations = true;

    /**
     * Register the Telescope watchers and start recording if necessary.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    public static function start($app)
    {
        if (! config('telescope-error-service-client.enabled')) {
            return;
        }

        static::registerWatchers($app);

        if (static::runningApprovedArtisanCommand($app) ||
            static::handlingApprovedRequest($app)
        ) {
            static::startRecording();
        }
    }

    /**
     * Determine if the application is running an approved command.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return bool
     */
    protected static function runningApprovedArtisanCommand($app)
    {
        return $app->runningInConsole() && ! in_array(
            $_SERVER['argv'][1] ?? null,
            array_merge([
                'migrate:rollback',
                'migrate:fresh',
                'migrate:reset',
                'migrate:install',
                'package:discover',
                'queue:listen',
                'queue:work',
                'horizon',
                'horizon:work',
                'horizon:supervisor',
            ], config('telescope-error-service-client.ignoreCommands', []), config('telescope-error-service-client.ignore_commands', []))
        );
    }

    /**
     * Determine if the application is handling an approved request.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return bool
     */
    protected static function handlingApprovedRequest($app)
    {
        return ! $app->runningInConsole() && ! $app['request']->is(
            array_merge([
                'telescope-api*',
                'vendor/telescope*',
                'horizon*',
                'vendor/horizon*',
            ], config('telescope-error-service-client.ignore_paths', []))
        );
    }

    /**
     * Start recording entries.
     *
     * @return void
     */
    public static function startRecording()
    {
        static::$shouldRecord = ! cache('telescope:pause-recording');
    }

    /**
     * Stop recording entries.
     *
     * @return void
     */
    public static function stopRecording()
    {
        static::$shouldRecord = false;
    }

    /**
     * Execute the given callback without recording Telescope entries.
     *
     * @param  callable  $callback
     * @return void
     */
    public static function withoutRecording($callback)
    {
        $shouldRecord = static::$shouldRecord;

        static::$shouldRecord = false;

        call_user_func($callback);

        static::$shouldRecord = $shouldRecord;
    }

    /**
     * Determine if Telescope is recording.
     *
     * @return bool
     */
    public static function isRecording()
    {
        return static::$shouldRecord;
    }

    /**
     * Record the given entry.
     *
     * @param  string  $type
     * @param  \PDFfiller\TelescopeClient\IncomingEntry  $entry
     * @return void
     */
    protected static function record(string $type, IncomingEntry $entry)
    {
        if (! static::isRecording()) {
            return;
        }

        $entry->type($type)->tags(
            static::$tagUsing ? call_user_func(static::$tagUsing, $entry) : []
        );

        try {
            if (Auth::hasUser()) {
                $entry->user(Auth::user());
            }
        } catch (Throwable $e) {
            // Do nothing.
        }

        static::withoutRecording(function () use ($entry) {
            if (collect(static::$filterUsing)->every->__invoke($entry)) {
                static::$entriesQueue[] = $entry;
            }

            if (static::$afterRecordingHook) {
                call_user_func(static::$afterRecordingHook, new static);
            }
        });
    }

    /**
     * Record the given entry.
     *
     * @param  \PDFfiller\TelescopeClient\IncomingEntry  $entry
     * @return void
     */
    public static function recordCache(IncomingEntry $entry)
    {
        static::record(EntryType::CACHE, $entry);
    }

    /**
     * Record the given entry.
     *
     * @param  \PDFfiller\TelescopeClient\IncomingEntry  $entry
     * @return void
     */
    public static function recordCommand(IncomingEntry $entry)
    {
        static::record(EntryType::COMMAND, $entry);
    }

    /**
     * Record the given entry.
     *
     * @param  \PDFfiller\TelescopeClient\IncomingEntry  $entry
     * @return void
     */
    public static function recordException(IncomingEntry $entry)
    {
        static::record(EntryType::EXCEPTION, $entry);
    }

    /**
     * Record the given entry.
     *
     * @param  \PDFfiller\TelescopeClient\IncomingEntry  $entry
     * @return void
     */
    public static function recordGate(IncomingEntry $entry)
    {
        static::record(EntryType::GATE, $entry);
    }

    /**
     * Record the given entry.
     *
     * @param  \PDFfiller\TelescopeClient\IncomingEntry  $entry
     * @return void
     */
    public static function recordJob($entry)
    {
        static::record(EntryType::JOB, $entry);
    }

    /**
     * Record the given entry.
     *
     * @param  \PDFfiller\TelescopeClient\IncomingEntry  $entry
     * @return void
     */
    public static function recordLog(IncomingEntry $entry)
    {
        static::record(EntryType::LOG, $entry);
    }

    /**
     * Record the given entry.
     *
     * @param  \PDFfiller\TelescopeClient\IncomingEntry  $entry
     * @return void
     */
    public static function recordRequest(IncomingEntry $entry)
    {
        static::record(EntryType::REQUEST, $entry);
    }

    /**
     * Flush all entries in the queue.
     *
     * @return static
     */
    public static function flushEntries()
    {
        static::$entriesQueue = [];

        return new static;
    }

    /**
     * Record the given exception.
     *
     * @param  \Throwable|\Exception  $e
     * @param  array  $tags
     * @return void
     */
    public static function catch($e, $tags = [])
    {
        if ($e instanceof Throwable && ! $e instanceof Exception) {
            $e = new FatalThrowableError($e);
        }

        event(new MessageLogged('error', $e->getMessage(), [
            'exception' => $e,
            'telescope' => $tags,
        ]));
    }

    /**
     * Set the callback that filters the entries that should be recorded.
     *
     * @param  \Closure  $callback
     * @return static
     */
    public static function filter(Closure $callback)
    {
        static::$filterUsing[] = $callback;

        return new static;
    }

    /**
     * Set the callback that filters the batches that should be recorded.
     *
     * @param  \Closure  $callback
     * @return static
     */
    public static function filterBatch(Closure $callback)
    {
        static::$filterBatchUsing[] = $callback;

        return new static;
    }

    /**
     * Set the callback that will be executed after an entry is recorded in the queue.
     *
     * @param  \Closure  $callback
     * @return static
     */
    public static function afterRecording(Closure $callback)
    {
        static::$afterRecordingHook = $callback;

        return new static;
    }

    /**
     * Set the callback that adds tags to the record.
     *
     * @param  \Closure  $callback
     * @return static
     */
    public static function tag(Closure $callback)
    {
        static::$tagUsing = $callback;

        return new static;
    }

    /**
     * Store the queued entries and flush the queue.
     *
     * @param  \PDFfiller\TelescopeClient\Contracts\EntriesRepository  $storage
     * @return void
     */
    public static function store(EntriesRepository $storage)
    {
        if (empty(static::$entriesQueue)) {
            return;
        }

        if (! collect(static::$filterBatchUsing)->every->__invoke(collect(static::$entriesQueue))) {
            static::flushEntries();
        }

        try {
            $batchId = Str::orderedUuid()->toString();

            $storage->store(static::collectEntries($batchId));

            if ($storage instanceof TerminableRepository) {
                $storage->terminate();
            }
        } catch (Exception $e) {
            app(ExceptionHandler::class)->report($e);
        }

        static::$entriesQueue = [];
    }

    /**
     * Collect the entries for storage.
     *
     * @param  string  $batchId
     * @return \Illuminate\Support\Collection
     */
    protected static function collectEntries($batchId)
    {
        return collect(static::$entriesQueue)
            ->each(function ($entry) use ($batchId) {
                $entry->batchId($batchId);

                if ($entry->isDump()) {
                    $entry->assignEntryPointFromBatch(static::$entriesQueue);
                }
            });
    }
}
