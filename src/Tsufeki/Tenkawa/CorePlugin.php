<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa;

use PhpParser\PrettyPrinter\Standard;
use PHPStan\Analyser\NodeScopeResolver;
use PHPStan\Analyser\TypeSpecifier;
use PHPStan\Broker\Broker;
use PHPStan\File\FileHelper;
use PHPStan\Parser\Parser as PhpStanParser;
use PHPStan\PhpDoc\PhpDocNodeResolver;
use PHPStan\PhpDoc\PhpDocStringResolver;
use PHPStan\PhpDoc\TypeNodeResolver;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\Rules;
use PHPStan\Rules\ClassCaseSensitivityCheck;
use PHPStan\Rules\FunctionCallParametersCheck;
use PHPStan\Rules\FunctionDefinitionCheck;
use PHPStan\Rules\FunctionReturnTypeCheck;
use PHPStan\Rules\Properties\PropertyDescriptor;
use PHPStan\Rules\Properties\PropertyReflectionFinder;
use PHPStan\Rules\Registry;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleLevelHelper;
use PHPStan\Rules\UnusedFunctionParametersCheck;
use PHPStan\Type\DynamicFunctionReturnTypeExtension;
use PHPStan\Type\FileTypeMapper;
use PHPStan\Type\Php\AllArgumentBasedFunctionReturnTypeExtension;
use PHPStan\Type\Php\ArgumentBasedArrayFunctionReturnTypeExtension;
use PHPStan\Type\Php\ArgumentBasedFunctionReturnTypeExtension;
use PHPStan\Type\Php\ArrayFilterFunctionReturnTypeReturnTypeExtension;
use PHPStan\Type\Php\CallbackBasedArrayFunctionReturnTypeExtension;
use PHPStan\Type\Php\CallbackBasedFunctionReturnTypeExtension;
use Tsufeki\BlancheJsonRpc\Dispatcher\MethodProvider;
use Tsufeki\BlancheJsonRpc\Dispatcher\MethodRegistry;
use Tsufeki\BlancheJsonRpc\Dispatcher\SimpleMethodRegistry;
use Tsufeki\BlancheJsonRpc\MappedJsonRpc;
use Tsufeki\HmContainer\Container;
use Tsufeki\HmContainer\Definition\Value;
use Tsufeki\KayoJsonMapper\Mapper;
use Tsufeki\KayoJsonMapper\MapperBuilder;
use Tsufeki\KayoJsonMapper\NameMangler\NullNameMangler;
use Tsufeki\Tenkawa\Diagnostics\DiagnosticsAggregator;
use Tsufeki\Tenkawa\Diagnostics\DiagnosticsProvider;
use Tsufeki\Tenkawa\Diagnostics\PhplDiagnosticsProvider;
use Tsufeki\Tenkawa\Document\DocumentStore;
use Tsufeki\Tenkawa\Event\Document\OnChange;
use Tsufeki\Tenkawa\Event\Document\OnClose;
use Tsufeki\Tenkawa\Event\Document\OnOpen;
use Tsufeki\Tenkawa\Event\Document\OnProjectClose;
use Tsufeki\Tenkawa\Event\Document\OnProjectOpen;
use Tsufeki\Tenkawa\Event\EventDispatcher;
use Tsufeki\Tenkawa\Event\OnStart;
use Tsufeki\Tenkawa\Index\GlobalIndexer;
use Tsufeki\Tenkawa\Index\Index;
use Tsufeki\Tenkawa\Index\IndexDataProvider;
use Tsufeki\Tenkawa\Index\Indexer;
use Tsufeki\Tenkawa\Index\IndexStorageFactory;
use Tsufeki\Tenkawa\Index\LocalCacheIndexStorageFactory;
use Tsufeki\Tenkawa\Index\MemoryIndexStorageFactory;
use Tsufeki\Tenkawa\Index\StubsIndexer;
use Tsufeki\Tenkawa\Io\Directories;
use Tsufeki\Tenkawa\Io\FileReader;
use Tsufeki\Tenkawa\Io\FileSearch;
use Tsufeki\Tenkawa\Io\LocalFileReader;
use Tsufeki\Tenkawa\Io\LocalFileSearch;
use Tsufeki\Tenkawa\Logger\ClientLogger;
use Tsufeki\Tenkawa\Mapper\UriMapper;
use Tsufeki\Tenkawa\Parser\Parser;
use Tsufeki\Tenkawa\Parser\ParserDiagnosticsProvider;
use Tsufeki\Tenkawa\Parser\PhpParserAdapter;
use Tsufeki\Tenkawa\PhpStan\Analyser;
use Tsufeki\Tenkawa\PhpStan\DocumentParser;
use Tsufeki\Tenkawa\PhpStan\ErrorTolerantPrettyPrinter;
use Tsufeki\Tenkawa\PhpStan\IndexBroker;
use Tsufeki\Tenkawa\PhpStan\PhpDocResolver;
use Tsufeki\Tenkawa\PhpStan\PhpStanDiagnosticsProvider;
use Tsufeki\Tenkawa\PhpStan\PhpStanTypeInference;
use Tsufeki\Tenkawa\ProcessRunner\ProcessRunner;
use Tsufeki\Tenkawa\ProcessRunner\ReactProcessRunner;
use Tsufeki\Tenkawa\ProcessRunner\ThrottledProcessRunner;
use Tsufeki\Tenkawa\Protocol\LanguageClient;
use Tsufeki\Tenkawa\References\CompletionAggregator;
use Tsufeki\Tenkawa\References\CompletionProvider;
use Tsufeki\Tenkawa\References\DocCommentHelper;
use Tsufeki\Tenkawa\References\DocumentSymbolsAggregator;
use Tsufeki\Tenkawa\References\DocumentSymbolsProvider;
use Tsufeki\Tenkawa\References\ExpressionTypeHoverProvider;
use Tsufeki\Tenkawa\References\GlobalsCompletionProvider;
use Tsufeki\Tenkawa\References\GlobalsHelper;
use Tsufeki\Tenkawa\References\GoToDefinitionAggregator;
use Tsufeki\Tenkawa\References\GoToDefinitionProvider;
use Tsufeki\Tenkawa\References\GoToDocCommentProvider;
use Tsufeki\Tenkawa\References\GoToGlobalsProvider;
use Tsufeki\Tenkawa\References\GoToMembersProvider;
use Tsufeki\Tenkawa\References\HoverAggregator;
use Tsufeki\Tenkawa\References\HoverDocCommentProvider;
use Tsufeki\Tenkawa\References\HoverFormatter;
use Tsufeki\Tenkawa\References\HoverGlobalsProvider;
use Tsufeki\Tenkawa\References\HoverMembersProvider;
use Tsufeki\Tenkawa\References\HoverProvider;
use Tsufeki\Tenkawa\References\MembersCompletionProvider;
use Tsufeki\Tenkawa\References\MembersHelper;
use Tsufeki\Tenkawa\References\ReflectionDocumentSymbolsProvider;
use Tsufeki\Tenkawa\References\VariableCompletionProvider;
use Tsufeki\Tenkawa\Reflection\ClassResolver;
use Tsufeki\Tenkawa\Reflection\IndexReflectionProvider;
use Tsufeki\Tenkawa\Reflection\ReflectionIndexDataProvider;
use Tsufeki\Tenkawa\Reflection\ReflectionProvider;
use Tsufeki\Tenkawa\TypeInference\TypeInference;
use Tsufeki\Tenkawa\Utils\Throttler;

class CorePlugin extends Plugin
{
    public function configureContainer(Container $container, array $options)
    {
        $container->setValue(EventDispatcher::class, new EventDispatcher($container));
        $container->setClass(ClientLogger::class);

        $container->setClass(OnStart::class, CorePluginInit::class, true);
        $container->setCallable(Mapper::class, [$this, 'createMapper']);
        $container->setClass(MethodRegistry::class, SimpleMethodRegistry::class);
        $container->setCallable(MappedJsonRpc::class, [MappedJsonRpc::class, 'create']);

        $container->setClass(Directories::class);
        $container->setClass(FileReader::class, LocalFileReader::class);
        $container->setClass(FileSearch::class, LocalFileSearch::class);

        $container->setClass(ReactProcessRunner::class);
        $container->setClass(ProcessRunner::class, ThrottledProcessRunner::class, false, [
            ReactProcessRunner::class,
            new Value(new Throttler(8)),
        ]);

        $container->setClass(MethodProvider::class, Server::class, true);
        $container->setClass(LanguageClient::class, Client::class);
        $container->setClass(DocumentStore::class);

        $container->setClass(DiagnosticsAggregator::class);
        $container->setAlias(OnOpen::class, DiagnosticsAggregator::class, true);
        $container->setAlias(OnChange::class, DiagnosticsAggregator::class, true);
        $container->setClass(DiagnosticsProvider::class, PhplDiagnosticsProvider::class, true);

        $container->setClass(Parser::class, PhpParserAdapter::class);
        $container->setClass(DiagnosticsProvider::class, ParserDiagnosticsProvider::class, true);

        if ($options['index.memory_only'] ?? false) {
            $container->setClass(IndexStorageFactory::class, MemoryIndexStorageFactory::class);
        } else {
            $container->setClass(IndexStorageFactory::class, LocalCacheIndexStorageFactory::class);
        }

        $container->setClass(Indexer::class);
        $container->setAlias(OnStart::class, Indexer::class, true);
        $container->setAlias(OnOpen::class, Indexer::class, true);
        $container->setAlias(OnChange::class, Indexer::class, true);
        $container->setAlias(OnClose::class, Indexer::class, true);
        $container->setAlias(OnProjectOpen::class, Indexer::class, true);
        $container->setAlias(OnProjectClose::class, Indexer::class, true);
        $container->setClass(Index::class);

        if ($options['index.stubs'] ?? true) {
            $container->setClass(GlobalIndexer::class, StubsIndexer::class, true);
        }

        $container->setClass(IndexDataProvider::class, ReflectionIndexDataProvider::class, true);
        $container->setClass(ReflectionProvider::class, IndexReflectionProvider::class);
        $container->setClass(ClassResolver::class);

        $container->setClass(HoverAggregator::class);
        $container->setClass(HoverFormatter::class);
        $container->setClass(GoToDefinitionAggregator::class);
        $container->setClass(CompletionAggregator::class);

        $container->setClass(GlobalsHelper::class);
        $container->setClass(GoToDefinitionProvider::class, GoToGlobalsProvider::class, true);
        $container->setClass(HoverProvider::class, HoverGlobalsProvider::class, true);
        $container->setClass(CompletionProvider::class, GlobalsCompletionProvider::class, true);

        $container->setClass(DocCommentHelper::class);
        $container->setClass(GoToDefinitionProvider::class, GoToDocCommentProvider::class, true);
        $container->setClass(HoverProvider::class, HoverDocCommentProvider::class, true);

        $container->setClass(TypeInference::class, PhpStanTypeInference::class);
        $container->setClass(NodeScopeResolver::class, null, false, [null, null, null, null, null, new Value(true), new Value(false), new Value([])]);
        $container->setClass(DocumentParser::class);
        $container->setAlias(PhpStanParser::class, DocumentParser::class);
        $container->setClass(IndexBroker::class);
        $container->setAlias(Broker::class, IndexBroker::class);
        $container->setClass(Standard::class, ErrorTolerantPrettyPrinter::class, false, [new Value([])]);
        $container->setClass(TypeSpecifier::class);
        $container->setClass(PhpDocResolver::class);
        $container->setAlias(FileTypeMapper::class, PhpDocResolver::class);
        $container->setClass(PhpDocStringResolver::class);
        $container->setClass(Lexer::class);
        $container->setClass(PhpDocParser::class);
        $container->setClass(PhpDocNodeResolver::class);
        $container->setClass(TypeNodeResolver::class);
        $container->setClass(TypeParser::class);
        $container->setClass(ConstExprParser::class);
        $container->setClass(FileHelper::class, null, false, [new Value(getcwd())]);
        $container->setClass(Analyser::class);

        $container->setClass(DynamicFunctionReturnTypeExtension::class, AllArgumentBasedFunctionReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, ArgumentBasedArrayFunctionReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, ArgumentBasedFunctionReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, ArrayFilterFunctionReturnTypeReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, CallbackBasedArrayFunctionReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, CallbackBasedFunctionReturnTypeExtension::class, true);

        $container->setClass(MembersHelper::class);
        $container->setClass(GoToDefinitionProvider::class, GoToMembersProvider::class, true);
        $container->setClass(HoverProvider::class, HoverMembersProvider::class, true);
        $container->setClass(CompletionProvider::class, MembersCompletionProvider::class, true);

        $container->setClass(HoverProvider::class, ExpressionTypeHoverProvider::class, true);
        $container->setClass(CompletionProvider::class, VariableCompletionProvider::class, true);

        $container->setClass(DocumentSymbolsAggregator::class);
        $container->setClass(DocumentSymbolsProvider::class, ReflectionDocumentSymbolsProvider::class, true);

        $container->setClass(DiagnosticsProvider::class, PhpStanDiagnosticsProvider::class, true);
        $container->setClass(Registry::class);
        $container->setClass(RuleLevelHelper::class, null, false, [null, new Value(true), new Value(false), new Value(true)]);
        $container->setClass(ClassCaseSensitivityCheck::class);
        $container->setClass(FunctionCallParametersCheck::class, null, false, [null, new Value(true), new Value(true)]);
        $container->setClass(FunctionDefinitionCheck::class, null, false, [null, null, new Value(true), new Value(false)]);
        $container->setClass(FunctionReturnTypeCheck::class);
        $container->setClass(UnusedFunctionParametersCheck::class);
        $container->setClass(PropertyReflectionFinder::class);
        $container->setClass(PropertyDescriptor::class);

        $container->setClass(Rule::class, Rules\Arrays\AppendedArrayItemTypeRule::class, true);
        // $container->setClass(Rule::class, Rules\Arrays\DuplicateKeysInLiteralArraysRule::class, true);
        $container->setClass(Rule::class, Rules\Arrays\InvalidKeyInArrayDimFetchRule::class, true);
        $container->setClass(Rule::class, Rules\Arrays\InvalidKeyInArrayItemRule::class, true);
        $container->setClass(Rule::class, Rules\Arrays\IterableInForeachRule::class, true, [new Value(true)]);
        $container->setClass(Rule::class, Rules\Cast\UselessCastRule::class, true);
        $container->setClass(Rule::class, Rules\Classes\ClassConstantRule::class, true);
        $container->setClass(Rule::class, Rules\Classes\ExistingClassInClassExtendsRule::class, true);
        $container->setClass(Rule::class, Rules\Classes\ExistingClassInInstanceOfRule::class, true, [null, null, new Value(true)]);
        $container->setClass(Rule::class, Rules\Classes\ExistingClassInTraitUseRule::class, true);
        $container->setClass(Rule::class, Rules\Classes\ExistingClassesInClassImplementsRule::class, true);
        $container->setClass(Rule::class, Rules\Classes\ExistingClassesInInterfaceExtendsRule::class, true);
        $container->setClass(Rule::class, Rules\Classes\ImpossibleInstanceOfRule::class, true, [new Value(true)]);
        $container->setClass(Rule::class, Rules\Classes\InstantiationRule::class, true);
        // $container->setClass(Rule::class, Rules\Classes\RequireParentConstructCallRule::class, true);
        $container->setClass(Rule::class, Rules\Classes\UnusedConstructorParametersRule::class, true);
        $container->setClass(Rule::class, Rules\Comparison\ImpossibleCheckTypeFunctionCallRule::class, true, [null, new Value(true)]);
        $container->setClass(Rule::class, Rules\Comparison\StrictComparisonOfDifferentTypesRule::class, true);
        // $container->setClass(Rule::class, Rules\Constants\ConstantRule::class, true);
        // $container->setClass(Rule::class, Rules\Exceptions\CaughtExceptionExistenceRule::class, true);
        $container->setClass(Rule::class, Rules\Functions\CallToCountOnlyWithArrayOrCountableRule::class, true);
        $container->setClass(Rule::class, Rules\Functions\CallToFunctionParametersRule::class, true);
        $container->setClass(Rule::class, Rules\Functions\CallToNonExistentFunctionRule::class, true);
        $container->setClass(Rule::class, Rules\Functions\ClosureReturnTypeRule::class, true);
        $container->setClass(Rule::class, Rules\Functions\ExistingClassesInClosureTypehintsRule::class, true);
        $container->setClass(Rule::class, Rules\Functions\ExistingClassesInTypehintsRule::class, true);
        $container->setClass(Rule::class, Rules\Functions\InnerFunctionRule::class, true);
        $container->setClass(Rule::class, Rules\Functions\NonExistentDefinedFunctionRule::class, true);
        $container->setClass(Rule::class, Rules\Functions\PrintfParametersRule::class, true);
        // $container->setClass(Rule::class, Rules\Functions\ReturnTypeRule::class, true);
        $container->setClass(Rule::class, Rules\Functions\UnusedClosureUsesRule::class, true);
        $container->setClass(Rule::class, Rules\Methods\CallMethodsOnPossiblyNullRule::class, true, [null, new Value(false)]);
        $container->setClass(Rule::class, Rules\Methods\CallMethodsRule::class, true);
        $container->setClass(Rule::class, Rules\Methods\CallStaticMethodsRule::class, true);
        $container->setClass(Rule::class, Rules\Methods\ExistingClassesInTypehintsRule::class, true);
        // $container->setClass(Rule::class, Rules\Methods\ReturnTypeRule::class, true);
        $container->setClass(Rule::class, Rules\Namespaces\ExistingNamesInGroupUseRule::class, true);
        $container->setClass(Rule::class, Rules\Namespaces\ExistingNamesInUseRule::class, true);
        $container->setClass(Rule::class, Rules\PhpDoc\IncompatiblePhpDocTypeRule::class, true);
        $container->setClass(Rule::class, Rules\PhpDoc\InvalidPhpDocTagValueRule::class, true);
        $container->setClass(Rule::class, Rules\Properties\AccessPropertiesOnPossiblyNullRule::class, true, [null, new Value(false)]);
        $container->setClass(Rule::class, Rules\Properties\AccessPropertiesRule::class, true);
        $container->setClass(Rule::class, Rules\Properties\AccessStaticPropertiesRule::class, true);
        $container->setClass(Rule::class, Rules\Properties\DefaultValueTypesAssignedToPropertiesRule::class, true);
        $container->setClass(Rule::class, Rules\Properties\ExistingClassesInPropertiesRule::class, true, [null, null, new Value(true)]);
        $container->setClass(Rule::class, Rules\Properties\ReadingWriteOnlyPropertiesRule::class, true, [null, null, null, new Value(false)]);
        $container->setClass(Rule::class, Rules\Properties\TypesAssignedToPropertiesRule::class, true);
        $container->setClass(Rule::class, Rules\Properties\WritingToReadOnlyPropertiesRule::class, true, [null, null, null, new Value(false)]);
        $container->setClass(Rule::class, Rules\Variables\DefinedVariableInAnonymousFunctionUseRule::class, true, [new Value(true)]);
        $container->setClass(Rule::class, Rules\Variables\DefinedVariableRule::class, true, [new Value(true), new Value(true)]);
        $container->setClass(Rule::class, Rules\Variables\ThisVariableRule::class, true);
        $container->setClass(Rule::class, Rules\Variables\VariableCertaintyInIssetRule::class, true);
        $container->setClass(Rule::class, Rules\Variables\VariableCloningRule::class, true, [new Value(true)]);
    }

    /**
     * @internal
     */
    public function createMapper(): Mapper
    {
        $uriMapper = new UriMapper();

        return MapperBuilder::create()
            ->setNameMangler(new NullNameMangler())
            ->setPrivatePropertyAccess(false)
            ->setGuessRequiredProperties(true)
            ->setDumpNullProperties(false)
            ->throwOnMissingProperty(true)
            ->throwOnUnknownProperty(false)
            ->throwOnInfiniteRecursion(true)
            ->acceptStdClassAsArray(true)
            ->setStrictNulls(true)
            ->addLoader($uriMapper)
            ->addDumper($uriMapper)
            ->getMapper();
    }
}
