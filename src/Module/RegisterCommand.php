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
use Zend\ComponentInstaller\Injector\InjectorInterface;
use ZF\ComposerAutoloading\Command\Enable;

class RegisterCommand extends Command
{
    public const HELP = <<< 'EOT'
Register an existing middleware module with the application, by:

- Ensuring a PSR-4 autoloader entry is present in composer.json, and the
  autoloading rules have been generated.
- Ensuring the ConfigProvider class for the module is registered with the
  application configuration.
EOT;

    public const HELP_ARG_MODULE = 'The module to register with the application';

    public const HELP_OPT_NAMESPACE = 'Register the module with has this root namespace';

    /**
     * Configure command.
     */
    protected function configure() : void
    {
        $this->setDescription('Register a middleware module with the application');
        $this->setHelp(self::HELP);
        CommandCommonOptions::addDefaultOptionsAndArguments($this);
        $this->addOption(
            'namespace',
            null,
            InputOption::VALUE_REQUIRED,
            self::HELP_OPT_NAMESPACE
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $module = $input->getArgument('module');
        $rootNamespace = $input->getOption('namespace') ?: null;
        $composer = $input->getOption('composer') ?: 'composer';
        $modulesPath = CommandCommonOptions::getModulesPath($input);

        $injector = new ConfigAggregatorInjector(getcwd());
        $moduleNamespace = $this->createModuleNamespace($module, $rootNamespace);
        $configProvider = sprintf('%s\ConfigProvider', $moduleNamespace);
        if (! $injector->isRegistered($configProvider)) {
            $injector->inject(
                $configProvider,
                InjectorInterface::TYPE_CONFIG_PROVIDER
            );
        }

        $enable = new Enable(getcwd(), $modulesPath, $composer);
        $enable->setMoveModuleClass(false);
        $enable->process($moduleNamespace, 'psr-4');

        $output->writeln(sprintf('Registered autoloading rules and added configuration entry for module %s', $module));
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
