<?php
/**
 * Laravel IDE Helper Generator
 *
 * @author    Barry vd. Heuvel <barryvdh@gmail.com>
 * @copyright 2014 Barry vd. Heuvel / Fruitcake Studio (http://www.fruitcakestudio.nl)
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @link      https://github.com/barryvdh/laravel-ide-helper
 */

namespace Barryvdh\LaravelIdeHelper;

use /** @noinspection PhpUndefinedNamespaceInspection,PhpUndefinedClassInspection */Illuminate\Foundation\AliasLoader;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\View\Factory;
use Symfony\Component\Console\Output\OutputInterface;

class Generator
{
    protected ConfigRepository $config;
    protected Factory $view;
    protected OutputInterface $output;

    protected array $extra = [];
    protected array $magic = [];
    protected array $interfaces = [];
    protected string $helpers;

    /**
     * @param \Illuminate\Config\Repository $config
     * @param Factory $view
     * @param OutputInterface|null $output
     * @param string $helpers
     */
    public function __construct(ConfigRepository $config, $view, OutputInterface $output = null, $helpers = '')
    {
        $this->config = $config;
        $this->view = $view;

        // Find the drivers to add to the extra/interfaces
        $this->detectDrivers();

        $this->extra = array_merge($this->extra, $this->config->get('laravel-ide-helper::extra'));
        $this->magic = array_merge($this->magic, $this->config->get('laravel-ide-helper::magic'));
        $this->interfaces = array_merge($this->interfaces, $this->config->get('laravel-ide-helper::interfaces'));

        // Make all interface classes absolute
        foreach ($this->interfaces as &$interface) {
            $interface = '\\' . ltrim($interface, '\\');
        }
        $this->helpers = $helpers;
    }

    /**
     * Generate the helper file contents;
     *
     * @param  string $format The format to generate the helper in (php/json)
     * @return string;
     */
    public function generate($format = 'php')
    {
        // Check if the generator for this format exists
        $method = 'generate' . ucfirst($format) . 'Helper';
        if (method_exists($this, $method)) {
            return $this->$method();
        }

        return $this->generatePhpHelper();
    }

    public function generatePhpHelper()
    {
        $app = app();
        return $this->view->make('laravel-ide-helper::ide-helper')
            ->with('namespaces', $this->getNamespaces())
            ->with('helpers', $this->helpers)
            ->with('version', $app::VERSION)
            ->render();
    }

    public function generateJsonHelper()
    {
        $classes = [];
        foreach ($this->getNamespaces() as $aliases) {
            foreach ($aliases as $alias) {
                $functions = [];
                foreach ($alias->getMethods() as $method) {
                    $functions[$method->getName()] = '(' . $method->getParamsWithDefault() . ')';
                }
                $classes[$alias->getAlias()] = [
                    'functions' => $functions,
                ];
            }
        }

        $flags = JSON_FORCE_OBJECT;
        if (defined('JSON_PRETTY_PRINT')) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return json_encode([
            'php' => [
                'classes' => $classes,
            ],
        ], $flags);
    }

    protected function detectDrivers()
    {
        try {
            if (class_exists('Auth') && is_a('Auth', '\Illuminate\Support\Facades\Auth', true)) {
                /** @noinspection PhpUndefinedClassInspection */
                $class = get_class(\Auth::driver());
                $this->extra['Auth'] = [$class];
                $this->interfaces['\Illuminate\Auth\UserProviderInterface'] = $class;
            }
        } catch (\Exception $e) {}

        try {
            if (class_exists('DB') && is_a('DB', '\Illuminate\Support\Facades\DB', true)) {
                /** @noinspection PhpUndefinedClassInspection */
                $class = get_class(\DB::connection());
                $this->extra['DB'] = [$class];
                $this->interfaces['\Illuminate\Database\ConnectionInterface'] = $class;
            }
        } catch (\Exception $e) {}

        try {
            if (class_exists('Cache') && is_a('Cache', '\Illuminate\Support\Facades\Cache', true)) {
                /** @noinspection PhpUndefinedClassInspection */
                $driver = get_class(\Cache::driver());
                /** @noinspection PhpUndefinedClassInspection */
                $store = get_class(\Cache::getStore());
                $this->extra['Cache'] = [$driver, $store];
                $this->interfaces['\Illuminate\Cache\StoreInterface'] = $store;
            }
        } catch (\Exception $e) {}

        try {
            if (class_exists('Queue') && is_a('Queue', '\Illuminate\Support\Facades\Queue', true)) {
                /** @noinspection PhpUndefinedClassInspection */
                $class = get_class(\Queue::connection());
                $this->extra['Queue'] = [$class];
                $this->interfaces['\Illuminate\Queue\QueueInterface'] = $class;
            }
        } catch (\Exception $e) {}

        try {
            if (class_exists('SSH') && is_a('SSH', '\Illuminate\Support\Facades\SSH', true)) {
                /** @noinspection PhpUndefinedClassInspection */
                $class = get_class(\SSH::connection());
                $this->extra['SSH'] = [$class];
                $this->interfaces['\Illuminate\Remote\ConnectionInterface'] = $class;
            }
        } catch (\Exception $e) {}

    }

    /**
     * Find all namespaces/aliases that are valid for us to render
     *
     * @return array|Alias[][]
     */
    protected function getNamespaces()
    {
        $namespaces = [];

        // Get all aliases
        /** @noinspection PhpUndefinedClassInspection */
        foreach (AliasLoader::getInstance()->getAliases() as $name => $facade) {
            $magicMethods = array_key_exists($name, $this->magic) ? $this->magic[$name] : [];
            $alias = new Alias($name, $facade, $magicMethods, $this->interfaces);
            if ($alias->isValid()) {

                //Add extra methods, from other classes (magic static calls)
                if (array_key_exists($name, $this->extra)) {
                    $alias->addClass($this->extra[$name]);
                }

                $namespace = $alias->getNamespace();
                if (!isset($namespaces[$namespace])) {
                    $namespaces[$namespace] = [];
                }
                $namespaces[$namespace][] = $alias;
            }

        }

        return $namespaces;
    }

    /**
     * Get the driver/connection/store from the managers
     *
     * @param $alias
     * @return array|bool|string
     */
    public function getDriver($alias)
    {
        try {
            if ($alias == 'Auth') {
                /** @noinspection PhpUndefinedClassInspection */
                $driver = \Auth::driver();
            } elseif ($alias == 'DB') {
                /** @noinspection PhpUndefinedClassInspection */
                $driver = \DB::connection();
            } elseif ($alias == 'Cache') {
                /** @noinspection PhpUndefinedClassInspection */
                $driver = get_class(\Cache::driver());
                /** @noinspection PhpUndefinedClassInspection */
                $store = get_class(\Cache::getStore());
                return [$driver, $store];
            } elseif ($alias == 'Queue') {
                /** @noinspection PhpUndefinedClassInspection */
                $driver = \Queue::connection();
            } else {
                return false;
            }

            return get_class($driver);
        } catch (\Exception $e) {
            $this->error("Could not determine driver/connection for $alias.");
            return false;
        }
    }

    /**
     * Write a string as error output.
     *
     * @param  string $string
     * @return void
     */
    protected function error($string)
    {
        if ($this->output) {
            $this->output->writeln("<error>$string</error>");
        } else {
            echo $string . "\r\n";
        }
    }
}
