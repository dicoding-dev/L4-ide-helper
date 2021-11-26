<?php

use Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Factory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ServiceProviderTest extends TestCase
{
    /** @var MockObject|Container */
    protected $app;
    protected IdeHelperServiceProvider $provider;

    public static function makeAppMock(TestCase $testCase)
    {
        $app = $testCase->getMockBuilder(Container::class)
            ->onlyMethods(['make', 'bind'])
            ->getMock();
        $fs = new Filesystem();
        $config = $testCase->getMockBuilder(Repository::class)
            ->onlyMethods(['get', 'set', 'package'])
            ->disableOriginalConstructor()
            ->getMock();
        $events = $testCase->getMockBuilder(Dispatcher::class)
            ->onlyMethods(['listen'])
            ->setConstructorArgs([$app])
            ->getMock();
        $view = $testCase->getMockBuilder(Factory::class)
            ->onlyMethods(['addNamespace'])
            ->disableOriginalConstructor()
            ->getMock();

        $app->expects($testCase::any())->method('make')->willReturnMap([
            ['files', [], $fs],
            ['config', [], $config],
            ['events', [], $events],
            ['view', [], $view],
            ['path', [], __DIR__],
        ]);

        return $app;
    }


    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->app = static::makeAppMock($this);
        $this->provider = new IdeHelperServiceProvider($this->app);
    }

    public function testDeferred()
    {
        static::assertTrue($this->provider->isDeferred());
    }

    public function testProvides()
    {
        static::assertEquals(
            ['command.ide-helper.generate', 'command.ide-helper.models', 'command.ide-helper.meta'],
            $this->provider->provides()
        );
    }

    public function testRegister()
    {
        $this->app->expects(static::exactly(3))->method('bind')->withConsecutive(
            [
                'command.ide-helper.generate',
                static::isType('callable'),
                static::isFalse()
            ],
            [
                'command.ide-helper.models',
                static::isType('callable'),
                static::isFalse()
            ],
            [
                'command.ide-helper.meta',
                static::isType('callable'),
                static::isFalse()
            ]
        );

        /** @var MockObject|Dispatcher $events */
        $events = $this->app['events'];
        $events->expects(static::once())->method('listen')->with(
            'artisan.start',
            static::callback(function ($listener) {
                $params = print_r(
                    [
                        'commands' => [
                            'command.ide-helper.generate',
                            'command.ide-helper.models',
                            'command.ide-helper.meta'
                        ]
                    ],
                    true
                );
                return strpos(
                    preg_replace('/^\s+/mu', '', print_r($listener, true)),
                    preg_replace('/^\s+/mu', '', $params)
                ) !== false;
            }),
            0
        );

        $this->provider->register();
    }

    public function testBoot()
    {
        $path = realpath(__DIR__ . '/../src');

        /** @var MockObject|Repository $config */
        $config = $this->app['config'];
        $config->expects(static::once())->method('package')->with(
            'barryvdh/laravel-ide-helper',
            $path . '/config',
            'laravel-ide-helper'
        );

        /** @var MockObject|Factory $view */
        $view = $this->app['view'];
        $view->expects(static::once())->method('addNamespace')->with(
            'laravel-ide-helper',
            $path . '/views'
        );

        $this->provider->boot();
    }

}

