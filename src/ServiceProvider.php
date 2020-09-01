<?php

declare(strict_types=1);

namespace TTBooking\ClassFactory;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/class-factory.php', 'class-factory');

        $this->publishes([
            __DIR__.'/../config/class-factory.php' => config_path('class-factory.php'),
        ], 'config');

        GenericClass::setTempDirectory(storage_path('framework/cache'));
        GenericClass::useEval(config('class-factory.use_eval', false));
        foreach (config('class-factory.templates', []) as $name => $dependencies) {
            GenericClass::setTemplate($name, (array) $dependencies);
        }
    }
}
