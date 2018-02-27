<?php

namespace TheRezor\TransactionalJobs;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Bus\Dispatcher as DispatcherContract;
use Illuminate\Contracts\Queue\Factory as QueueFactoryContract;
use Illuminate\Contracts\Bus\QueueingDispatcher as QueueingDispatcherContract;

class BusServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(TransactionalDispatcher::class, function ($app) {
            return new TransactionalDispatcher($app, function ($connection = null) use ($app) {
                return $app[QueueFactoryContract::class]->connection($connection);
            });
        });

        $this->app->alias(
            TransactionalDispatcher::class, DispatcherContract::class
        );

        $this->app->alias(
            TransactionalDispatcher::class, QueueingDispatcherContract::class
        );
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            TransactionalDispatcher::class,
            DispatcherContract::class,
            QueueingDispatcherContract::class,
        ];
    }
}

