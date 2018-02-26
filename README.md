# Laravel transactional jobs
Dispatch jobs inside transactions. Cancel job on transaction rollback. Add to queue on transaction committed.

## Installation

1) Run ```composer require therezor/laravel-transactional-jobs``` in your laravel project root folder

2) Replace `Illuminate\Bus\BusServiceProvider::class` a service provider in the `app.php` configuration file with ` TheRezor\TransactionalJobs\BusServiceProvider::class`

```php
<?php

'providers' => [
    ...
    TheRezor\TransactionalJobs\BusServiceProvider::class
]
?>
```

3) Add `public $afterTransactions = true;` property to jobs that runs in the middle of transactions

```php
<?php

class MySuperJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $afterTransactions = true;
    
    ...
}
?>
```
