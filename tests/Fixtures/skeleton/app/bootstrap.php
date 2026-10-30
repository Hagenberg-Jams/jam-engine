<?php

// Lets the tests choose the current time.
if (getenv('JAM_TEST_NOW')) {
    Carbon\Carbon::setTestNow(getenv('JAM_TEST_NOW'));
    Carbon\CarbonImmutable::setTestNow(getenv('JAM_TEST_NOW'));
}

$app = new Hyde\Foundation\Application(dirname(__DIR__));

$app->singleton(Illuminate\Contracts\Console\Kernel::class, Hyde\Foundation\ConsoleKernel::class);
$app->singleton(Illuminate\Contracts\Debug\ExceptionHandler::class, Illuminate\Foundation\Exceptions\Handler::class);

$hyde = new Hyde\Foundation\HydeKernel(dirname(__DIR__));
$app->singleton(Hyde\Foundation\HydeKernel::class, fn(): Hyde\Foundation\HydeKernel => Hyde\Foundation\HydeKernel::getInstance());
Hyde\Foundation\HydeKernel::setInstance($hyde);

return $app;
