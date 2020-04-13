# Laravel transactional jobs
Dispatch jobs inside transactions. Cancel job on transaction rollback. Add to queue on transaction committed.

## Installation

1) Run ```composer require therezor/laravel-transactional-jobs``` in your laravel project root folder

2) Implement `TheRezor\TransactionalJobs\Contracts\TransactionalJob` to jobs that runs in the middle of transactions

```php
<?php

use TheRezor\TransactionalJobs\Contracts\RunAfterTransaction;

class MySuperJob implements ShouldQueue, RunAfterTransaction
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    ...
}
```
