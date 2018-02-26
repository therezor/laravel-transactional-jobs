<?php

namespace TheRezor\TransactionalJobs;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Bus\Dispatcher as DispatcherContract;
use Illuminate\Contracts\Queue\Factory as QueueFactoryContract;
use Illuminate\Contracts\Bus\QueueingDispatcher as QueueingDispatcherContract;

class BusServiceProvider extends ServiceProvider
{
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

        $this->app->afterResolving('db', function ($db, Application $app) {
            $app->make(TransactionalDispatcher::class);
        });
    }
}
