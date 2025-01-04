<?php

namespace XiDanko\TsEnumsGenerator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\Finder\SplFileInfo;

class Generate extends Command
{
    protected $signature;
    protected $description = 'Generates typescript enums from php enums';
    protected string $sourceDir;
    protected string $destinationDir;


    public function __construct()
    {
        $defaultSourceDir = config('ts-enums-generator.default_source_dir');
        $defaultDestinationDir = config('ts-enums-generator.default_destination_dir');
        $this->signature = "ts-enums:generate {--source=$defaultSourceDir} {--destination=$defaultDestinationDir}";
        parent::__construct();
    }

    public function handle(): void
    {
        $this->setOptions();
        try {
            $this->info('Generating typescript enums...');
            $this->generate();
            $this->info('Typescript enums generated successfully.');
        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
        }
    }

    private function setOptions(): void
    {
        $this->sourceDir = $this->option('source');
        $this->destinationDir = $this->option('destination');
    }

    private function generate(): void
    {
        $phpEnumFiles = File::allFiles($this->sourceDir);
        foreach ($phpEnumFiles as $phpEnumFile) {
            $tsEnumFilePath = $this->getTsEnumFilePathFrom($phpEnumFile);
            $tsEnumFileContent = $this->generateTsEnumContentFrom($phpEnumFile);
            $this->createTsEnumFile($tsEnumFilePath, $tsEnumFileContent);
        }
    }

    private function getTsEnumFilePathFrom(SplFileInfo $phpEnumFile): string
    {
        $directoriesConventionMethod = config('ts-enums-generator.convention.directories');
        $filesConventionMethod = config('ts-enums-generator.convention.files');
        $dirPath = Str::of($phpEnumFile->getPath())
            ->replace('\\', '/')
            ->after($this->sourceDir)
            ->explode('/')
            ->map(fn ($directory) => Str::$directoriesConventionMethod($directory))
            ->join('/');
        $fileName = Str::$filesConventionMethod($phpEnumFile->getFilenameWithoutExtension());
        return base_path($this->destinationDir . "/$dirPath/$fileName.ts");
    }

    private function generateTsEnumContentFrom(SplFileInfo $phpEnumFile): string
    {
        $phpEnumClassName = $this->getClassNameFrom($phpEnumFile);
        $reflection = new ReflectionClass($phpEnumClassName);
        $enumName = $reflection->getShortName();
        $enumCases = $reflection->getConstants();

        $tsEnum = "export enum {$enumName} {\n";
        foreach ($enumCases as $case => $object) {
            if ($this->isBackedEnum($object)) {
                $tsEnum .= "    {$object->name} = '{$object->value}',\n";
            } else {
                $tsEnum .= "    {$object->name},\n";
            }
        }
        $tsEnum .= "}\n";
        return $tsEnum;
    }

    protected function getClassNameFrom(SplFileInfo $phpEnumFile): ?string
    {
        $contents = File::get($phpEnumFile);
        if (preg_match('/namespace\s+(.+?);/', $contents, $namespaceMatch)
            && preg_match('/enum\s+(\w+)/', $contents, $enumMatch)) {
            return $namespaceMatch[1] . '\\' . $enumMatch[1];
        }
        return null;
    }

    private function isBackedEnum(object $caseObject): bool
    {
        return property_exists($caseObject, 'value');
    }

    protected function createTsEnumFile(string $path, string $tsEnumContent): void
    {
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $tsEnumContent);
    }
}
