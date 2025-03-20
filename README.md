# laravel-schedule-monitor

Laravel Pulse card that list all scheduled tasks based on hosmelq 


## Installation

You can install the package via composer:


composer.json configuration

 "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/matoke-flow/laravel-schedule-monitor.git"
        }
    ],

## Usage

To add the card to the Pulse dashboard, you must first [publish](https://laravel.com/docs/10.x/pulse#dashboard-customization) the dashboard view.

```sh
php artisan vendor:publish --tag=pulse-dashboard
```

You can now add the card.

```html
<x-pulse>
    ...

    <livewire:pulse.schedule cols="8" />

    ...
</x-pulse>

```


## Credits
- [Team Sakthi]
- [Hari Krishnan]


The majority of the code has been taken from [here](https://github.com/laravel/framework/blob/10.x/src/Illuminate/Console/Scheduling/ScheduleListCommand.php).

