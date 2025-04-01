<?php

declare(strict_types=1);

namespace Flow\Laravel\Pulse\Schedule\Livewire;

use function Safe\preg_replace;

use Closure;
use Cron\CronExpression;
use DateTimeZone;
use Illuminate\Console\Application;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule as IlluminateSchedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;
use ReflectionClass;
use ReflectionFunction;
use DB;

#[Lazy]
class Schedule extends Card
{
    #[Url(as: 'selectedcountry')]
    public string $selectedcountry = 'ALL'; // Default to 'ALL' to show all

    public function render(ConsoleKernel $kernel, IlluminateSchedule $schedule): View
    {
        $kernel->bootstrap();

        $now = Carbon::now()->toDateString();

        $events = collect($schedule->events())->map(function (Event $event): array {

            $command = $this->getCommand($event);

            $taskresult = $this->getschedulerstatus($command);

            $timezone = new DateTimeZone(session('timezone')?? 'UTC');

            return [
                'command' => $command,
                'expression' => $this->getExpression($event),
                'next_due' => $this->getNextDueDateForEvent($event, $timezone),
                'status' => $taskresult ? $taskresult->status : null,
                'failed_at' => $taskresult ?  $taskresult->last_failed_at :null,
                'reason' => $taskresult ? $taskresult->failedlog : null,
                'date'       => $taskresult ? $taskresult->date : null,
        ];
    })->filter(function ($event) use ($now) {
        
        $istoday = isset($event['date']) && $event['date'] === $now;

        $matchesCountry = ($this->selectedcountry === 'ALL') ? true : str_contains($event['command'], $this->selectedcountry);
        return $istoday && $matchesCountry;
    });

        return view('pulse-schedule::livewire.schedule', [
            'events' => $events,
        ]);
    }

    private function getClosureLocation(CallbackEvent $event): string
    {
        $callback = (new ReflectionClass($event))->getProperty('callback')->getValue($event);

        if ($callback instanceof Closure) {
            $function = new ReflectionFunction($callback);

            return sprintf(
                '%s:%s',
                str_replace(app()->basePath().DIRECTORY_SEPARATOR, '', $function->getFileName() ?: ''),
                $function->getStartLine()
            );
        }

        if (is_string($callback)) {
            return $callback;
        }

        if (is_array($callback)) {
            $className = is_string($callback[0]) ? $callback[0] : $callback[0]::class;

            return sprintf('%s::%s', $className, $callback[1]);
        }

        return sprintf('%s::__invoke', $callback::class); // @phpstan-ignore-line
    }

    private function getCommand(Event $event): string
    {
        $command = str_replace([Application::phpBinary(), Application::artisanBinary()], [
            'php',
            preg_replace("#['\"]#", '', Application::artisanBinary()),
        ], $event->command ?? '');

        if ($event instanceof CallbackEvent) {
            $command = $event->getSummaryForDisplay();

            if (in_array($command, ['Closure', 'Callback'], true)) {
                $command = 'Closure at: '.$this->getClosureLocation($event);
            }
        }

        return mb_strlen($command) > 1 ? "{$command} " : '';
    }

    private function getExpression(Event $event): string
    {
        if (! $event->isRepeatable()) {
            return $event->getExpression();
        }

        return "{$event->getExpression()} ({$event->repeatSeconds}s)";
    }

    private function getNextDueDateForEvent(Event $event, DateTimeZone $timezone): Carbon
    {
        $nextDueDate = Carbon::instance(
            (new CronExpression($event->expression))
                ->getNextRunDate(Carbon::now()->setTimezone($event->timezone))
                ->setTimezone($timezone)
        );

        if (! $event->isRepeatable()) {
            return $nextDueDate;
        }

        $previousDueDate = Carbon::instance(
            (new CronExpression($event->expression))
                ->getPreviousRunDate(Carbon::now()->setTimezone($event->timezone), allowCurrentDate: true)
                ->setTimezone($timezone)
        );

        $now = Carbon::now()->setTimezone($event->timezone)->startOfMinute();

        if (! $now->copy()->startOfMinute()->eq($previousDueDate)) {
            return $nextDueDate;
        }

        return $now
            ->endOfSecond()
            ->ceilSeconds($event->repeatSeconds); // @phpstan-ignore-line
    }

    private function getschedulerstatus($command)
    {

        $task = DB::table('monitored_scheduled_tasks')
                ->where('name', $command)
                ->select('id', 'last_started_at', 'last_finished_at', 'last_failed_at')
                ->first();

        $taskstatus = new \stdClass();
        $taskstatus->status = 'Unknown';
        $taskstatus->failedlog = null;
        $taskstatus->last_failed_at = null;

        if(!empty($task)) {
            $startedDate = $task->last_started_at ? Carbon::parse($task->last_started_at)->toDateString() : null;
            $finishedDate = $task->last_finished_at ? Carbon::parse($task->last_finished_at)->toDateString() : null;
            $failedDate = $task->last_failed_at ? Carbon::parse($task->last_failed_at)->toDateString() : null;
    
            
            if ($failedDate && ($failedDate === $startedDate || $failedDate === $finishedDate)) {
                $failedlog = DB::table('monitored_scheduled_task_log_items')
                    ->where('monitored_scheduled_task_id', $task->id)
                    ->where('type', 'failed')
                    ->select('meta')
                    ->orderBy('id', 'desc')
                    ->first();
            
                $taskstatus->status = 'Failed';
                $taskstatus->date = $startedDate;
                $taskstatus->last_failed_at = $task->last_failed_at;
                if ($failedlog && $failedlog->meta) {
                    $failureMessage = json_decode($failedlog->meta, true);
                    $taskstatus->failedlog = $failureMessage['failure_message'] ?? 'No message';
                } else {
                    $taskstatus->failedlog = 'Unknown'; 
                    $taskstatus->date = $startedDate;

                }
            }
            else {
                $taskstatus->status = 'Success';
                $taskstatus->date = $startedDate;

            }
            return $taskstatus;
        }

    }
}
