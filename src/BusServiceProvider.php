<?php

namespace TheRezor\TransactionalJobs;

use Illuminate\Bus\Dispatcher;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Bus\BusServiceProvider as LaravelProvider;
use Illuminate\Contracts\Bus\Dispatcher as DispatcherContract;
use Illuminate\Contracts\Queue\Factory as QueueFactoryContract;
use Illuminate\Contracts\Bus\QueueingDispatcher as QueueingDispatcherContract;

class BusServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // Register laravel service provider before package provider
        $this->app->register(LaravelProvider::class);

        $this->app->singleton(TransactionalDispatcher::class, function ($app) {
            return new TransactionalDispatcher($app, function ($connection = null) use ($app) {
                return $app[QueueFactoryContract::class]->connection($connection);
            });
        });

        $this->app->alias(
            TransactionalDispatcher::class, Dispatcher::class
        );

        $this->app->alias(
            TransactionalDispatcher::class, DispatcherContract::class
        );

        $this->app->alias(
            TransactionalDispatcher::class, QueueingDispatcherContract::class
        );
    }

    public function boot()
    {
        Event::listen(TransactionBeginning::class, function () {
            $this->app->make(TransactionalDispatcher::class)->beginTransaction();
        });

        Event::listen(TransactionCommitted::class, function () {
            $this->app->make(TransactionalDispatcher::class)->commitTransaction();
        });

        Event::listen(TransactionRolledBack::class, function () {
            $this->app->make(TransactionalDispatcher::class)->rollbackTransaction();
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            Dispatcher::class,
            TransactionalDispatcher::class,
            DispatcherContract::class,
            QueueingDispatcherContract::class,
        ];
    }
}

