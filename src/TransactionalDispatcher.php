<?php

namespace TheRezor\TransactionalJobs;

use Closure;
use Illuminate\Bus\Dispatcher;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;

class TransactionalDispatcher extends Dispatcher
{
    /**
     * @var array
     */
    protected $pendingCommands = [];

    /**
     * @var DispatcherContract
     */
    protected $eventDispatcher;

    public function __construct(Container $container, Closure $queueResolver = null)
    {
        parent::__construct($container, $queueResolver);

        $this->eventDispatcher = $container->make(DispatcherContract::class);
        $this->setUpTransactionListeners();
    }

    public function dispatchToQueue($command)
    {
        // Dispatch immediately if no transactions was opened during job
        if (empty($command->afterTransactions) || empty($this->pendingCommands)) {
            return parent::dispatchToQueue($command);
        }

        // Add command to pending list
        foreach ($this->pendingCommands as $connection => $items) {
            $this->pendingCommands[$connection][] = $command;
        }

        return null;
    }

    protected function prepareTransaction(ConnectionInterface $connection)
    {
        if (!isset($this->pendingCommands[$connection->getName()])) {
            $this->pendingCommands[$connection->getName()] = [];
        }
    }

    public function commitTransaction(ConnectionInterface $connection)
    {
        if (empty($this->pendingCommands[$connection->getName()]) || $connection->transactionLevel() > 0) {
            return;
        }

        $this->dispatchPendingCommands($connection);
    }

    public function rollbackTransaction(ConnectionInterface $connection)
    {
        unset($this->pendingCommands[$connection->getName()]);
    }

    protected function dispatchPendingCommands(ConnectionInterface $connection)
    {
        foreach ($this->pendingCommands[$connection->getName()] as $command) {
            parent::dispatchToQueue($command);
        }

        unset($this->pendingCommands[$connection->getName()]);
    }

    protected function setUpTransactionListeners()
    {
        $this->eventDispatcher->listen(TransactionBeginning::class, function ($event) {
            $this->prepareTransaction($event->connection);
        });
        $this->eventDispatcher->listen(TransactionCommitted::class, function ($event) {
            $this->commitTransaction($event->connection);
        });
        $this->eventDispatcher->listen(TransactionRolledBack::class, function ($event) {
            $this->rollbackTransaction($event->connection);
        });
    }
}
