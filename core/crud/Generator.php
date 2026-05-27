<?php

namespace core\crud;

class Generator
{
    public function __construct(
        protected SchemaIntrospector $schema = new SchemaIntrospector(),
        protected DescriptorBuilder $descriptorBuilder = new DescriptorBuilder(),
        protected StubRenderer $renderer = new StubRenderer(),
        protected MenuWriter $menuWriter = new MenuWriter(),
        protected ManifestWriter $manifestWriter = new ManifestWriter(),
    ) {
    }

    public function run(GeneratorOptions $options): array
    {
        if ($options->delete) {
            return [
                'mode' => 'delete',
                'deleted' => $this->manifestWriter->delete($options, $options->dryRun),
            ];
        }

        $schema = $this->schema->inspect($options->table, $options->connection);
        $descriptor = $this->descriptorBuilder->build($schema, $options);
        $rendered = $this->renderer->render($descriptor);
        $files = $rendered['files'];
        $names = $rendered['names'];

        $conflicts = $this->findFileConflicts($files, $options->force);
        if ($conflicts) {
            throw new CrudException("Generated files already exist. Use --force to overwrite:\n" . implode("\n", $conflicts));
        }

        $menuPlan = $options->noMenu ? [] : $this->menuWriter->plan($descriptor, $names);
        if ($options->dryRun) {
            return [
                'mode' => 'dry-run',
                'descriptor' => $descriptor,
                'files' => array_keys($files),
                'menu' => $menuPlan,
                'manifest' => $this->manifestWriter->path($options),
            ];
        }

        $writtenFiles = [];
        foreach ($files as $path => $content) {
            $this->writeFile($path, $content);
            $writtenFiles[] = $path;
        }

        $menuNames = $options->noMenu ? [] : $this->menuWriter->write($descriptor, $names);
        $manifest = [
            'app' => $options->app,
            'path' => $options->path,
            'table' => $descriptor['table'],
            'physical_table' => $descriptor['physicalTable'],
            'title' => $descriptor['title'],
            'created_at' => date('c'),
            'files' => $writtenFiles,
            'menu_names' => $menuNames,
        ];
        $this->manifestWriter->write($options, $manifest);

        return [
            'mode' => 'generate',
            'descriptor' => $descriptor,
            'files' => $writtenFiles,
            'menu_names' => $menuNames,
            'manifest' => $this->manifestWriter->path($options),
        ];
    }

    protected function findFileConflicts(array $files, bool $force): array
    {
        if ($force) {
            return [];
        }

        $conflicts = [];
        foreach (array_keys($files) as $path) {
            if (is_file(base_path($path))) {
                $conflicts[] = $path;
            }
        }
        return $conflicts;
    }

    protected function writeFile(string $path, string $content): void
    {
        $absolute = base_path($path);
        if (!is_dir(dirname($absolute))) {
            mkdir(dirname($absolute), 0755, true);
        }
        file_put_contents($absolute, $content);
    }
}
