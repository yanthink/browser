<?php

namespace Yanthink\Browser;

use Closure;
use Facebook\WebDriver\Chrome\ChromeOptions;
use ReflectionFunction;
use Illuminate\Support\Collection;
use Facebook\WebDriver\Remote\RemoteWebDriver;

class Simulation
{
    use SupportsChrome;

    /**
     * 自启动chromeDriver服务
     * @var bool
     */
    protected static $autoStartChromeDriver = true;

    /**
     * All of the active browser instances.
     *
     * @var array | Collection
     */
    protected static $browsers = [];

    /**
     * 后置回调
     * @var array
     */
    protected static $afterClassCallbacks = [];

    public function __construct()
    {
        if (static::$autoStartChromeDriver) {
            static::startChromeDriver();
        }

        register_shutdown_function(function () {
            static::tearDown();
        });
    }

    public static function tearDown()
    {
        static::closeAll();

        foreach (static::$afterClassCallbacks as $callback) {
            $callback();
        }
    }

    /**
     * 禁用自启动chromeDriver服务
     */
    public static function disableAutoStartChromeDriver()
    {
        static::$autoStartChromeDriver = false;
    }

    /**
     * Register an "after class" tear down callback.
     *
     * @param  \Closure $callback
     * @return void
     */
    public static function afterClass(Closure $callback)
    {
        static::$afterClassCallbacks[] = $callback;
    }

    /**
     * Create a new browser instance.
     *
     * @param  \Closure $callback
     * @return Browser|void
     */
    public function browse(Closure $callback)
    {
        $browsers = $this->createBrowsersFor($callback);

        $callback(...$browsers->all());
    }

    /**
     * Create the browser instances needed for the given callback.
     *
     * @param  \Closure $callback
     * @return array | Collection
     */
    protected function createBrowsersFor(Closure $callback)
    {
        if (count(static::$browsers) === 0) {
            static::$browsers = collect([$this->newBrowser($this->createWebDriver())]);
        }

        $additional = $this->browsersNeededFor($callback) - 1;

        for ($i = 0; $i < $additional; $i++) {
            static::$browsers->push($this->newBrowser($this->createWebDriver()));
        }

        return static::$browsers;
    }

    /**
     * Create a new Browser instance.
     *
     * @param  \Facebook\WebDriver\Remote\RemoteWebDriver $driver
     * @return Browser
     */
    protected function newBrowser(RemoteWebDriver $driver)
    {
        return new Browser($driver);
    }

    /**
     * Get the number of browsers needed for a given callback.
     *
     * @param  \Closure $callback
     * @return int
     */
    protected function browsersNeededFor(Closure $callback)
    {
        return (new ReflectionFunction($callback))->getNumberOfParameters();
    }

    /**
     * Close all of the browsers except the primary (first) one.
     *
     * @param  \Illuminate\Support\Collection $browsers
     * @return \Illuminate\Support\Collection
     */
    protected function closeAllButPrimary($browsers)
    {
        $browsers->slice(1)->each->quit();

        return $browsers->take(1);
    }

    /**
     * Close all of the active browsers.
     *
     * @return void
     */
    public static function closeAll()
    {
        Collection::make(static::$browsers)->each->quit();

        static::$browsers = collect();
    }

    /**
     * Create the remote web driver instance.
     *
     * @return \Facebook\WebDriver\Remote\RemoteWebDriver
     */
    protected function createWebDriver()
    {
        return retry(5, function () {
            return $this->driver();
        }, 50);
    }

    /**
     * Create the RemoteWebDriver instance.
     *
     * @return \Facebook\WebDriver\Remote\RemoteWebDriver
     */
    protected function driver()
    {
        $chromeOptions = new ChromeOptions();

        if (app()->environment('production')) {
            $chromeOptions->addArguments([
                // '--no-sandbox', // root 需启用该参数
                '--headless',
                '--disable-gpu',
            ]); // 生产环境去UI化
        }

        $desiredCapabilities = $chromeOptions->toCapabilities();

        return RemoteWebDriver::create(
            static::$url, $desiredCapabilities
        );
    }

    /*
    public function __destruct()
    {
        static::tearDown();
    }
    */
}
