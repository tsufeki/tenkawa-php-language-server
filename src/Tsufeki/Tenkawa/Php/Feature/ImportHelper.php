<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature;

use Tsufeki\Tenkawa\Php\Feature\CodeAction\ImportCommandProvider;
use Tsufeki\Tenkawa\Php\Reflection\Element\Element;
use Tsufeki\Tenkawa\Php\Reflection\NameContext;
use Tsufeki\Tenkawa\Php\Reflection\ReflectionProvider;
use Tsufeki\Tenkawa\Server\Document\Document;
use Tsufeki\Tenkawa\Server\Feature\Common\Command;
use Tsufeki\Tenkawa\Server\Feature\Common\Position;

class ImportHelper
{
    /**
     * @var ReflectionProvider
     */
    private $reflectionProvider;

    public function __construct(ReflectionProvider $reflectionProvider)
    {
        $this->reflectionProvider = $reflectionProvider;
    }

    /**
     * @resolve Command[]
     */
    public function getCodeActions(
        string $name,
        string $kind,
        NameContext $nameContext,
        Position $position,
        Document $document,
        int $version = null
    ): \Generator {
        $parts = explode('\\', $name);

        if (($name[0] ?? '') === '\\' || $this->isAlreadyImported($parts, $kind, $nameContext)) {
            return [];
        }

        if (yield $this->isAlreadyResolved($name, $kind, $nameContext, $document)) {
            return [];
        }

        $elements = yield $this->getReflections($name, $kind, $document);
        $commands = [];
        /** @var Element $element */
        foreach ($elements as $element) {
            $importParts = explode('\\', ltrim($element->name, '\\'));
            if (count($parts) > 1) {
                // discard nested parts, import only top-most namespace
                $importParts = array_slice($importParts, 0, -count($parts) + 1);
            }
            $importName = implode('\\', $importParts);
            $command = new Command();
            $command->title = "Import $importName";
            $command->command = ImportCommandProvider::COMMAND;
            $command->arguments = [
                $document->getUri()->getNormalized(),
                $position,
                count($parts) > 1 ? '' : $kind,
                '\\' . $importName,
                $version,
            ];
            $commands[] = $command;
        }

        return $commands;
    }

    /**
     * @param string[] $parts
     */
    private function isAlreadyImported(array $parts, string $kind, NameContext $nameContext): bool
    {
        $importAlias = $parts[0];
        $kind = count($parts) > 1 ? '' : $kind;

        if ($kind === 'const') {
            if (isset($nameContext->constUses[$importAlias])) {
                return true;
            }
        } elseif ($kind === 'function') {
            if (isset($nameContext->functionUses[$importAlias])) {
                return true;
            }
        } else {
            if (isset($nameContext->uses[$importAlias])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @resolve bool
     */
    private function isAlreadyResolved(string $name, string $kind, NameContext $nameContext, Document $document): \Generator
    {
        if ($kind === 'const') {
            foreach ($nameContext->resolveConst($name) as $resolved) {
                if (!empty(yield $this->reflectionProvider->getConst($document, $resolved))) {
                    return true;
                }
            }
        } elseif ($kind === 'function') {
            foreach ($nameContext->resolveFunction($name) as $resolved) {
                if (!empty(yield $this->reflectionProvider->getFunction($document, $resolved))) {
                    return true;
                }
            }
        } else {
            $resolved = $nameContext->resolveClass($name);
            if (!empty(yield $this->reflectionProvider->getClass($document, $resolved))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @resolve Element[]
     */
    private function getReflections(string $name, string $kind, Document $document): \Generator
    {
        if ($kind === 'const') {
            return yield $this->reflectionProvider->getConstsByShortName($document, $name);
        }
        if ($kind === 'function') {
            return yield $this->reflectionProvider->getFunctionsByShortName($document, $name);
        }

        return yield $this->reflectionProvider->getClassesByShortName($document, $name);
    }
}
