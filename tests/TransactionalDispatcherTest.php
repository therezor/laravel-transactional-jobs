<?php

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\TestCase;
use TheRezor\TransactionalJobs\BusServiceProvider;
use TheRezor\TransactionalJobs\Contracts\RunAfterTransaction;
use TheRezor\TransactionalJobs\TransactionalDispatcher;

class TransactionalDispatcherTest extends TestCase
{
    /**
     * @var TransactionalDispatcher
     */
    protected $dispatcher;

    public function test_dont_handle_job_out_of_transaction()
    {
        $this->dispatcher->dispatch(new class {
            public function handle()
            {
                $_SERVER['_test_job_'] = 'foo';
            }
        });

        $this->assertEquals('foo', $_SERVER['_test_job_']);
    }

    public function test_only_dispatch_job_after_root_transaction_commit()
    {
        DB::transaction(function () {
            DB::transaction(function () {
                TestJob::dispatch();
            });

            $this->assertArrayNotHasKey('_test_job_', $_SERVER);
        });

        $this->assertEquals('foo', $_SERVER['_test_job_']);
    }

    public function test_should_clean_pending_jobs_after_root_transaction_commit()
    {
        DB::transaction(function () {
            TestJob::dispatch();
        });

        DB::transaction(function () {
            unset($_SERVER['_test_job_']);
        });

        $this->assertArrayNotHasKey('_test_job_', $_SERVER);
    }

    public function test_dont_dispatch_jobs_after_outer_transaction_rollback()
    {
        try {
            DB::transaction(function () {
                DB::transaction(function () {
                    TestJob::dispatch();
                });

                throw new Exception();
            });
        } catch (Exception $e) {

        }

        $this->assertArrayNotHasKey('_test_job_', $_SERVER);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispatcher = $this->app->make(TransactionalDispatcher::class);
        unset($_SERVER['_test_job_']);
    }

    protected function getPackageProviders($app)
    {
        return [BusServiceProvider::class];
    }
}

class TestJob implements ShouldQueue, RunAfterTransaction
{
    use Dispatchable;

    public function handle()
    {
        DB::transaction(function () {
            $_SERVER['_test_job_'] = 'foo';
        });
    }
}
