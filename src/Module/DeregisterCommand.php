<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-tooling for the canonical source repository
 * @copyright Copyright (c) 2017 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-tooling/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\Expressive\Tooling\Module;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Zend\ComponentInstaller\Injector\ConfigAggregatorInjector;
use ZF\ComposerAutoloading\Command\Disable;

class DeregisterCommand extends Command
{
    public const HELP = <<< 'EOT'
Deregister an existing middleware module from the application, by:

- Removing the associated PSR-4 autoloader entry from composer.json, and
  regenerating autoloading rules.
- Removing the associated ConfigProvider class for the module from the
  application configuration.
EOT;

    public const HELP_ARG_MODULE = 'The module to register with the application';

    public const HELP_OPT_NAMESPACE = 'De-register the module with has this root namespace';

    /**
     * Configure command.
     */
    protected function configure() : void
    {
        $this->setDescription('Deregister a middleware module from the application');
        $this->setHelp(self::HELP);
        CommandCommonOptions::addDefaultOptionsAndArguments($this);
        $this->addOption(
            'namespace',
            null,
            InputOption::VALUE_REQUIRED,
            self::HELP_OPT_NAMESPACE
        );
    }

    /**
     * Deregister module.
     *
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $module = $input->getArgument('module');
        $composer = $input->getOption('composer') ?: 'composer';
        $rootNamespace = $input->getOption('namespace') ?: null;
        $modulesPath = CommandCommonOptions::getModulesPath($input);

        $injector = new ConfigAggregatorInjector(getcwd());
        $moduleNamespace = $this->createModuleNamespace($module, $rootNamespace);
        $configProvider = sprintf('%s\ConfigProvider', $moduleNamespace);
        if ($injector->isRegistered($configProvider)) {
            $injector->remove($configProvider);
        }

        $disable = new Disable(getcwd(), $modulesPath, $composer);
        $disable->process($moduleNamespace, 'psr-4');

        $output->writeln(sprintf('Removed autoloading rules and configuration entries for module %s', $module));
        return 0;
    }

    private function createModuleNamespace(string $moduleName, ?string $rootNamespace): string
    {
        if ($rootNamespace) {
            $components = explode('\\', $rootNamespace);
            $rootNamespace = implode('\\', array_map('ucfirst', $components));
        }
        return ltrim($rootNamespace.'\\'.$moduleName, '\\');
    }
}
