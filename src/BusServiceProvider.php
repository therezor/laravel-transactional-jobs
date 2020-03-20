<?php

namespace TheRezor\TransactionalJobs;

use Illuminate\Contracts\Bus\Dispatcher as DispatcherContract;
use Illuminate\Contracts\Bus\QueueingDispatcher as QueueingDispatcherContract;
use Illuminate\Contracts\Container\Container as ContainerContracts;
use Illuminate\Contracts\Queue\Factory as QueueFactoryContract;
use Illuminate\Support\ServiceProvider;

class BusServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(TransactionalDispatcher::class, function (ContainerContracts $app) {
            return (new TransactionalDispatcher($app))->setQueueResolver(function ($connection = null) use ($app) {
                return $app->make(QueueFactoryContract::class)->connection($connection);
            })->prepare();
        });

        $this->app->alias(
            TransactionalDispatcher::class, DispatcherContract::class
        );

        $this->app->alias(
            TransactionalDispatcher::class, QueueingDispatcherContract::class
        );

        $this->app->afterResolving('db', function () {
            $this->app->make(TransactionalDispatcher::class);
        });
    }
}
