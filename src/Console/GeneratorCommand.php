<?php
/**
 * Laravel IDE Helper Generator
 *
 * @author    Barry vd. Heuvel <barryvdh@gmail.com>
 * @copyright 2014 Barry vd. Heuvel / Fruitcake Studio (http://www.fruitcakestudio.nl)
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @link      https://github.com/barryvdh/laravel-ide-helper
 */

namespace Barryvdh\LaravelIdeHelper\Console;

use Barryvdh\LaravelIdeHelper\Generator;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

/**
 * A command to generate autocomplete information for your IDE
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */
class GeneratorCommand extends Command
{

    /**
     * {@inheritdoc}
     */
    protected $name = 'ide-helper:generate';

    /**
     * {@inheritdoc}
     */
    protected $description = 'Generate a new IDE Helper file.';

    /** @var \Illuminate\Config\Repository */
    protected ConfigRepository $config;

    /** @var \Illuminate\Filesystem\Filesystem */
    protected Filesystem $files;

    /** @var \Illuminate\View\Factory */
    protected \Illuminate\View\Factory $view;

    protected $onlyExtend;

    /**
     * {@inheritdoc}
     *
     * @param \Illuminate\Config\Repository $config
     * @param \Illuminate\Filesystem\Filesystem $files
     * @param \Illuminate\View\Factory $view
     */
    public function __construct(ConfigRepository $config, Filesystem $files, $view) {
        $this->config = $config;
        $this->files = $files;
        $this->view = $view;
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
        if (file_exists($compiled = base_path() . '/bootstrap/compiled.php')) {
            $this->error(
                'Error generating IDE Helper: first delete bootstrap/compiled.php (php artisan clear-compiled)'
            );
        } else {
            $filename = $this->argument('filename');
            $format = $this->option('format');

            // Strip the php extension
            if (substr($filename, -4, 4) == '.php') {
                $filename = substr($filename, 0, -4);
            }

            $filename .= '.' . $format;

            if ($this->option('memory')) {
                $this->useMemoryDriver();
            }

            $helpers = '';
            if ($this->option('helpers') || ($this->config->get('laravel-ide-helper::include_helpers'))) {
                foreach ($this->config->get('laravel-ide-helper::helper_files', []) as $helper) {
                    if (file_exists($helper)) {
                        $helpers .= str_replace(['<?php', '?>'], '', $this->files->get($helper));
                    }
                }
            } else {
                $helpers = '';
            }

            $generator = new Generator($this->config, $this->view, $this->getOutput(), $helpers);
            $content = $generator->generate($format);
            $written = $this->files->put($filename, $content);

            if ($written !== false) {
                $this->info("A new helper file was written to $filename");
            } else {
                $this->error("The helper file could not be created at $filename");
            }
        }
    }

    protected function useMemoryDriver()
    {
        //Use a sqlite database in memory, to avoid connection errors on Database facades
        $this->config->set(
            'database.connections.sqlite',
            [
                'driver' => 'sqlite',
                'database' => ':memory:',
            ]
        );
        $this->config->set('database.default', 'sqlite');
    }

    /**
     * {@inheritdoc}
     */
    protected function getArguments()
    {
        $filename = $this->config->get('laravel-ide-helper::filename');

        return [
            [
                'filename', InputArgument::OPTIONAL, 'The path to the helper file', $filename
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getOptions()
    {
        $format = $this->config->get('laravel-ide-helper::format');

        return [
            ['format', 'F', InputOption::VALUE_OPTIONAL, 'The format for the IDE Helper', $format],
            ['helpers', 'H', InputOption::VALUE_NONE, 'Include the helper files'],
            ['memory', 'M', InputOption::VALUE_NONE, 'Use sqlite memory driver'],
            ['sublime', 'S', InputOption::VALUE_NONE, 'DEPRECATED: Use different style for SublimeText CodeIntel'],
        ];
    }

}
