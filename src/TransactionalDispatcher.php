<?php

namespace TheRezor\TransactionalJobs;

use Illuminate\Bus\Dispatcher;
use TheRezor\TransactionalJobs\Contracts\RunAfterTransaction;

class TransactionalDispatcher extends Dispatcher
{
    protected $transactionLevel = 0;

    protected $pendingCommands = [];

    public function beginTransaction(): void
    {
        $this->transactionLevel++;
        $this->pendingCommands[$this->transactionLevel] = [];
    }

    public function commitTransaction(): void
    {
        $pendingCommands = $this->finishCurrentTransaction();
        if ($this->transactionLevel <= 0) {
            foreach ($pendingCommands as $command) {
                parent::dispatchToQueue($command);
            }

            return;
        }

        $this->pendingCommands[$this->transactionLevel] = array_merge(
            $this->pendingCommands[$this->transactionLevel],
            $pendingCommands
        );
    }

    public function rollbackTransaction(): void
    {
        $this->finishCurrentTransaction();
    }

    protected function finishCurrentTransaction(): array
    {
        $pendingCommands = $this->pendingCommands[$this->transactionLevel] ?? [];

        unset($this->pendingCommands[$this->transactionLevel]);

        $this->transactionLevel--;

        return $pendingCommands;
    }

    public function dispatchToQueue($command)
    {
        if ($this->isTransactionalJob($command)) {
            $this->addPendingCommand($command);

            return null;
        }

        return parent::dispatchToQueue($command);
    }

    protected function isTransactionalJob($command): bool
    {
        return $this->transactionLevel && ($command instanceof RunAfterTransaction || !empty($command->afterTransactions));
    }

    protected function addPendingCommand($command): void
    {
        $this->pendingCommands[$this->transactionLevel][] = $command;
    }
}
