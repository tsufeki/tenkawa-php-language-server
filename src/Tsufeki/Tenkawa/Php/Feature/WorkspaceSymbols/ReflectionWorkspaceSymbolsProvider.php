<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Feature\WorkspaceSymbols;

use Tsufeki\Tenkawa\Php\Index\StubsIndexer;
use Tsufeki\Tenkawa\Php\Reflection\Element\Element;
use Tsufeki\Tenkawa\Php\Reflection\Element\Function_;
use Tsufeki\Tenkawa\Php\Reflection\ReflectionProvider;
use Tsufeki\Tenkawa\Server\Document\DocumentStore;
use Tsufeki\Tenkawa\Server\Document\Project;
use Tsufeki\Tenkawa\Server\Feature\Common\SymbolInformation;
use Tsufeki\Tenkawa\Server\Feature\Common\SymbolKind;
use Tsufeki\Tenkawa\Server\Feature\WorkspaceSymbols\WorkspaceSymbolsProvider;
use Tsufeki\Tenkawa\Server\Utils\FuzzyMatcher;
use Tsufeki\Tenkawa\Server\Utils\StringUtils;

class ReflectionWorkspaceSymbolsProvider implements WorkspaceSymbolsProvider
{
    /**
     * @var ReflectionProvider
     */
    private $reflection;

    /**
     * @var FuzzyMatcher
     */
    private $fuzzyMatcher;

    /**
     * @var DocumentStore
     */
    private $documentStore;

    private const GET_METHODS = [
        SymbolKind::CLASS_ => 'getClass',
        SymbolKind::FUNCTION_ => 'getFunction',
        SymbolKind::CONSTANT => 'getConst',
    ];

    private const GET_ALL_METHODS = [
        SymbolKind::CLASS_ => 'getAllClassNames',
        SymbolKind::FUNCTION_ => 'getAllFunctionNames',
        SymbolKind::CONSTANT => 'getAllConstNames',
    ];

    public function __construct(ReflectionProvider $reflection, FuzzyMatcher $fuzzyMatcher, DocumentStore $documentStore)
    {
        $this->reflection = $reflection;
        $this->fuzzyMatcher = $fuzzyMatcher;
        $this->documentStore = $documentStore;
    }

    /**
     * @resolve SymbolInformation[]
     */
    public function getSymbols(string $query): \Generator
    {
        $projects = yield $this->documentStore->getProjects();
        $scores = [];
        $symbols = [];

        foreach (array_keys(self::GET_METHODS) as $kind) {
            foreach ($projects as $project) {
                $symbols = array_merge($symbols, yield $this->getSymbolsForProject($query, $project, $kind, $scores));
            }
        }

        $this->sort($symbols, $scores);

        return $symbols;
    }

    private function getSymbolsForProject(string $query, Project $project, int $kind, array &$scores): \Generator
    {
        $getAllMethod = self::GET_ALL_METHODS[$kind];

        /** @var string[] $names */
        $names = yield $this->reflection->$getAllMethod($project);
        $names = $this->filter($query, $names, $scores);

        return yield $this->makeSymbols($project, $names, $kind);
    }

    /**
     * @param string[]          $names
     * @param array<string,int> $scores
     *
     * @return string[]
     */
    private function filter(string $query, array $names, array &$scores): array
    {
        $filtered = [];

        foreach ($names as $name) {
            $shortName = StringUtils::getShortName($name);
            $score = $this->fuzzyMatcher->match($query, $shortName);
            if ($score === \PHP_INT_MIN) {
                continue;
            }
            $scores[$shortName] = $score;
            $filtered[] = $name;
        }

        return $filtered;
    }

    private function makeSymbols(Project $project, array $names, int $kind): \Generator
    {
        $getMethod = self::GET_METHODS[$kind];
        $symbols = [];

        foreach ($names as $name) {
            /** @var Element|null $element */
            $element = (yield $this->reflection->$getMethod($project, $name))[0] ?? null;
            if ($element === null || $element->origin === StubsIndexer::ORIGIN || $element->location === null) {
                continue;
            }

            $symbol = new SymbolInformation();
            $symbol->name = StringUtils::getShortName($name);
            $symbol->containerName = ltrim(StringUtils::getNamespace($name), '\\');
            $symbol->kind = $kind;
            $symbol->location = $element->location;

            $symbols[] = $symbol;
        }

        return $symbols;
    }

    /**
     * @param SymbolInformation[] $symbols
     */
    private function sort(array &$symbols, array $scores): void
    {
        usort($symbols, function (SymbolInformation $a, SymbolInformation $b) use ($scores) {
            return $scores[$b->name] <=> $scores[$a->name];
        });
    }
}
