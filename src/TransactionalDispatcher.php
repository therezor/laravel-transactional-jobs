<?php

namespace TheRezor\TransactionalJobs;

use Closure;
use Illuminate\Bus\Dispatcher;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use loophp\phptree\Node\ValueNode;
use loophp\phptree\Node\ValueNodeInterface;
use TheRezor\TransactionalJobs\Contracts\TransactionalJob;

class TransactionalDispatcher extends Dispatcher
{
    /**
     * @var ValueNodeInterface
     */
    protected $currentTransaction;

    /**
     * @var array
     */
    protected $pendingJobs;

    /**
     * @var int
     */
    protected $cursor;

    /**
     * @return $this
     */
    public function prepare(): self
    {
        $this->resetJobs();
        $this->setupListeners();

        return $this;
    }

    protected function resetJobs()
    {
        $this->pendingJobs = [];
        $this->cursor = 0;
    }

    protected function setupListeners(): void
    {
        Event::listen(TransactionBeginning::class, function () {
            $this->beginTransaction();
        });

        Event::listen(TransactionCommitted::class, function () {
            $this->commitTransaction();
        });

        Event::listen(TransactionRolledBack::class, function () {
            $this->rollbackTransaction();
        });
    }

    public function beginTransaction(): void
    {
        $this->currentTransaction = tap(new ValueNode(new Collection()), function (ValueNodeInterface $node) {
            if ($this->currentTransaction) {
                $this->currentTransaction->add($node);
            }
        });
    }

    public function commitTransaction(): void
    {
        if ($this->finishCurrentTransaction()->isRoot()) {
            $this->dispatchPendingCommands();
        }
    }

    /**
     * @return ValueNodeInterface
     */
    protected function finishCurrentTransaction(): ValueNodeInterface
    {
        return tap($this->currentTransaction, function (ValueNodeInterface $node) {
            $this->currentTransaction = $node->getParent();
        });
    }

    protected function dispatchPendingCommands(): void
    {
        $jobs = $this->pendingJobs;
        $total = $this->cursor;
        $this->resetJobs();

        for ($i = 0; $i < $total; $i++) {
            parent::dispatchToQueue($jobs[ $i ]);
        }
    }

    public function rollbackTransaction(): void
    {
        $this->cursor -= $this->finishCurrentTransaction()->getValue()->count();
    }

    /**
     * @param Closure $queueResolver
     *
     * @return $this
     */
    public function setQueueResolver(Closure $queueResolver): self
    {
        $this->queueResolver = $queueResolver;
        return $this;
    }

    /**
     * Dispatch a command to its appropriate handler behind a queue.
     *
     * @param mixed $command
     *
     * @return mixed|void
     */
    public function dispatchToQueue($command)
    {
        if ($this->isTransactionalJob($command)) {
            $this->addPendingJob($command);
            return;
        }

        return parent::dispatchToQueue($command);
    }

    /**
     * @param $command
     *
     * @return bool
     */
    protected function isTransactionalJob($command): bool
    {
        if ($this->currentTransaction && $command instanceof TransactionalJob) {
            return true;
        }

        return false;
    }

    protected function addPendingJob($command): void
    {
        $this->currentTransaction->getValue()->push($this->cursor);
        $this->pendingJobs[ $this->cursor++ ] = $command;
    }
}
