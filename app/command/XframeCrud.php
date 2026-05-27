<?php

namespace app\command;

use core\crud\CrudException;
use core\crud\Generator;
use core\crud\GeneratorOptions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand('xframe:crud', 'Generate XFrame backend CRUD pages from an existing table')]
class XframeCrud extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('app', null, InputOption::VALUE_REQUIRED, 'Backend app name, e.g. admin, user, shop.', 'admin')
            ->addOption('table', 't', InputOption::VALUE_REQUIRED, 'Existing table name.')
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'Menu title. Required when table has no comment.')
            ->addOption('path', 'p', InputOption::VALUE_REQUIRED, 'Feature path, e.g. crm/customer.')
            ->addOption('parent', null, InputOption::VALUE_REQUIRED, 'Parent admin_rule.name. Empty means top-level or generated directory nodes.')
            ->addOption('connection', 'c', InputOption::VALUE_REQUIRED, 'think-orm connection name.')
            ->addOption('quick-search', null, InputOption::VALUE_REQUIRED, 'Comma separated quick search fields.')
            ->addOption('sort-field', null, InputOption::VALUE_REQUIRED, 'Drag sort field. Defaults to sort/weigh when present.')
            ->addOption('spec', null, InputOption::VALUE_REQUIRED, 'Optional JSON spec file for field overrides.')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite generated files when they already exist.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview files, fields and menu without writing.')
            ->addOption('delete', 'd', InputOption::VALUE_NONE, 'Delete generated files and menu rules recorded in the manifest.')
            ->addOption('no-menu', null, InputOption::VALUE_NONE, 'Generate files only, without writing admin_rule menu/button nodes.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $options = new GeneratorOptions([
                'app' => (string) ($input->getOption('app') ?: 'admin'),
                'table' => (string) ($input->getOption('table') ?: ''),
                'title' => (string) ($input->getOption('title') ?: ''),
                'path' => (string) ($input->getOption('path') ?: ($input->getOption('table') ?: '')),
                'parent' => (string) ($input->getOption('parent') ?: ''),
                'connection' => (string) ($input->getOption('connection') ?: ''),
                'quickSearch' => $this->parseList((string) ($input->getOption('quick-search') ?: '')),
                'sortField' => (string) ($input->getOption('sort-field') ?: ''),
                'spec' => $this->readSpec((string) ($input->getOption('spec') ?: '')),
                'force' => (bool) $input->getOption('force'),
                'dryRun' => (bool) $input->getOption('dry-run'),
                'delete' => (bool) $input->getOption('delete'),
                'noMenu' => (bool) $input->getOption('no-menu'),
            ]);

            $result = (new Generator())->run($options);
            $this->writeResult($output, $result);
            return self::SUCCESS;
        } catch (CrudException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return self::FAILURE;
        } catch (Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            if ($output->isVerbose()) {
                $output->writeln($e->getTraceAsString());
            }
            return self::FAILURE;
        }
    }

    protected function parseList(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    protected function readSpec(string $path): array
    {
        if ($path === '') {
            return [];
        }
        $absolute = str_starts_with($path, '/') ? $path : base_path($path);
        if (!is_file($absolute)) {
            throw new CrudException("Spec file [$path] was not found.");
        }

        $data = json_decode((string) file_get_contents($absolute), true);
        if (!is_array($data)) {
            throw new CrudException("Spec file [$path] is not valid JSON.");
        }
        return $data;
    }

    protected function writeResult(OutputInterface $output, array $result): void
    {
        if ($result['mode'] === 'delete') {
            $deleted = $result['deleted'];
            $output->writeln('<info>CRUD delete completed.</info>');
            foreach ($deleted['files'] as $file) {
                $output->writeln('  file: ' . $file);
            }
            foreach ($deleted['menu_names'] as $name) {
                $output->writeln('  menu: ' . $name);
            }
            $output->writeln('  manifest: ' . $deleted['manifest']);
            return;
        }

        if ($result['mode'] === 'dry-run') {
            $descriptor = $result['descriptor'];
            $output->writeln('<info>CRUD dry-run</info>');
            $output->writeln('  app: ' . $descriptor['app']);
            $output->writeln('  table: ' . $descriptor['physicalTable']);
            $output->writeln('  path: ' . $descriptor['path']);
            $output->writeln('  title: ' . $descriptor['title']);
            $output->writeln('  primary key: ' . $descriptor['pk']);
            $output->writeln('  quick search: ' . implode(',', $descriptor['quickSearch']));
            $output->writeln('  sort field: ' . ($descriptor['sortField'] ?: '-'));
            $output->writeln('');
            $output->writeln('<comment>Files</comment>');
            foreach ($result['files'] as $file) {
                $output->writeln('  ' . $file);
            }
            if ($result['menu']) {
                $output->writeln('');
                $output->writeln('<comment>Menu</comment>');
                foreach ($result['menu'] as $item) {
                    $output->writeln('  ' . $item['type'] . ': ' . $item['name'] . ' (' . $item['title'] . ')');
                }
            }
            $output->writeln('');
            $output->writeln('manifest: ' . $result['manifest']);
            return;
        }

        $descriptor = $result['descriptor'];
        $output->writeln('<info>CRUD generated successfully.</info>');
        $output->writeln('  app: ' . $descriptor['app']);
        $output->writeln('  table: ' . $descriptor['physicalTable']);
        $output->writeln('  path: ' . $descriptor['path']);
        $output->writeln('');
        foreach ($result['files'] as $file) {
            $output->writeln('  file: ' . $file);
        }
        foreach ($result['menu_names'] as $name) {
            $output->writeln('  menu: ' . $name);
        }
        $output->writeln('  manifest: ' . $result['manifest']);
    }
}
