<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php;

use League\HTMLToMarkdown\HtmlConverter;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\DocBlockFactoryInterface;
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
use PHPStan\Reflection\Php\UniversalObjectCratesClassReflectionExtension;
use PHPStan\Reflection\PropertiesClassReflectionExtension;
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
use Tsufeki\HmContainer\Container;
use Tsufeki\HmContainer\Definition\Value;
use Tsufeki\Tenkawa\Php\Feature\CodeAction\ImportCodeActionProvider;
use Tsufeki\Tenkawa\Php\Feature\Command\WorkspaceEditCommandProvider;
use Tsufeki\Tenkawa\Php\Feature\Completion\GlobalSymbolCompleter;
use Tsufeki\Tenkawa\Php\Feature\Completion\ImportSymbolCompleter;
use Tsufeki\Tenkawa\Php\Feature\Completion\MemberSymbolCompleter;
use Tsufeki\Tenkawa\Php\Feature\Completion\SymbolCompleter;
use Tsufeki\Tenkawa\Php\Feature\Completion\SymbolCompletionProvider;
use Tsufeki\Tenkawa\Php\Feature\Completion\VariableCompletionProvider;
use Tsufeki\Tenkawa\Php\Feature\DocCommentSymbolExtractor;
use Tsufeki\Tenkawa\Php\Feature\DocumentSymbols\ReflectionDocumentSymbolsProvider;
use Tsufeki\Tenkawa\Php\Feature\GlobalSymbolExtractor;
use Tsufeki\Tenkawa\Php\Feature\GoToDefinition\SymbolGoToDefinitionProvider;
use Tsufeki\Tenkawa\Php\Feature\Hover\ExpressionTypeHoverProvider;
use Tsufeki\Tenkawa\Php\Feature\Hover\HoverFormatter;
use Tsufeki\Tenkawa\Php\Feature\Hover\SymbolHoverProvider;
use Tsufeki\Tenkawa\Php\Feature\Importer;
use Tsufeki\Tenkawa\Php\Feature\MemberSymbolExtractor;
use Tsufeki\Tenkawa\Php\Feature\NodeFinder;
use Tsufeki\Tenkawa\Php\Feature\NodePathSymbolExtractor;
use Tsufeki\Tenkawa\Php\Feature\PhpDocFormatter;
use Tsufeki\Tenkawa\Php\Feature\References\GlobalReferencesIndexDataProvider;
use Tsufeki\Tenkawa\Php\Feature\SymbolExtractor;
use Tsufeki\Tenkawa\Php\Feature\SymbolReflection;
use Tsufeki\Tenkawa\Php\Index\ComposerFileFilterFactory;
use Tsufeki\Tenkawa\Php\Index\StubsIndexer;
use Tsufeki\Tenkawa\Php\Parser\Parser;
use Tsufeki\Tenkawa\Php\Parser\ParserDiagnosticsProvider;
use Tsufeki\Tenkawa\Php\Parser\PhpParserAdapter;
use Tsufeki\Tenkawa\Php\PhpStan\Analyser;
use Tsufeki\Tenkawa\Php\PhpStan\DocumentParser;
use Tsufeki\Tenkawa\Php\PhpStan\ErrorTolerantPrettyPrinter;
use Tsufeki\Tenkawa\Php\PhpStan\IndexBroker;
use Tsufeki\Tenkawa\Php\PhpStan\PhpDocResolver;
use Tsufeki\Tenkawa\Php\PhpStan\PhpStanDiagnosticsProvider;
use Tsufeki\Tenkawa\Php\PhpStan\PhpStanTypeInference;
use Tsufeki\Tenkawa\Php\Reflection\ClassResolver;
use Tsufeki\Tenkawa\Php\Reflection\ClassResolverExtension;
use Tsufeki\Tenkawa\Php\Reflection\ConstExprEvaluator;
use Tsufeki\Tenkawa\Php\Reflection\IndexReflectionProvider;
use Tsufeki\Tenkawa\Php\Reflection\InheritPhpDocClassResolverExtension;
use Tsufeki\Tenkawa\Php\Reflection\MembersFromAnnotationClassResolverExtension;
use Tsufeki\Tenkawa\Php\Reflection\ReflectionIndexDataProvider;
use Tsufeki\Tenkawa\Php\Reflection\ReflectionProvider;
use Tsufeki\Tenkawa\Php\Reflection\ReflectionTransformer;
use Tsufeki\Tenkawa\Php\Reflection\StubsReflectionTransformer;
use Tsufeki\Tenkawa\Php\TypeInference\TypeInference;
use Tsufeki\Tenkawa\Server\Event\OnStart;
use Tsufeki\Tenkawa\Server\Feature\CodeAction\CodeActionProvider;
use Tsufeki\Tenkawa\Server\Feature\Command\CommandProvider;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionProvider;
use Tsufeki\Tenkawa\Server\Feature\Diagnostics\DiagnosticsProvider;
use Tsufeki\Tenkawa\Server\Feature\Diagnostics\WorkspaceDiagnosticsProvider;
use Tsufeki\Tenkawa\Server\Feature\DocumentSymbols\DocumentSymbolsProvider;
use Tsufeki\Tenkawa\Server\Feature\GoToDefinition\GoToDefinitionProvider;
use Tsufeki\Tenkawa\Server\Feature\Hover\HoverProvider;
use Tsufeki\Tenkawa\Server\Index\FileFilterFactory;
use Tsufeki\Tenkawa\Server\Index\GlobalIndexer;
use Tsufeki\Tenkawa\Server\Index\IndexDataProvider;
use Tsufeki\Tenkawa\Server\Io\FileLister\FileFilter;
use Tsufeki\Tenkawa\Server\Io\FileLister\GlobFileFilter;
use Tsufeki\Tenkawa\Server\Io\FileLister\GlobRejectDirectoryFilter;
use Tsufeki\Tenkawa\Server\Plugin;

class PhpPlugin extends Plugin
{
    public function configureContainer(Container $container, array $options)
    {
        $container->setClass(OnStart::class, PhpPluginInit::class, true);

        $container->setClass(Parser::class, PhpParserAdapter::class);
        $container->setClass(DiagnosticsProvider::class, ParserDiagnosticsProvider::class, true);

        if ($options['index.stubs'] ?? true) {
            $container->setClass(GlobalIndexer::class, StubsIndexer::class, true);
        }

        $container->setValue(FileFilter::class, new GlobFileFilter('**/*.php', 'php'), true);
        $container->setValue(FileFilter::class, new GlobRejectDirectoryFilter('{var,app/cache,cache,.git}'), true);
        $container->setClass(FileFilterFactory::class, ComposerFileFilterFactory::class, true);
        $container->setClass(IndexDataProvider::class, ReflectionIndexDataProvider::class, true);
        $container->setClass(ReflectionTransformer::class, StubsReflectionTransformer::class, true);
        $container->setClass(ReflectionProvider::class, IndexReflectionProvider::class);
        $container->setClass(ClassResolver::class);
        $container->setClass(ClassResolverExtension::class, InheritPhpDocClassResolverExtension::class, true);
        $container->setClass(ClassResolverExtension::class, MembersFromAnnotationClassResolverExtension::class, true);
        $container->setClass(ConstExprEvaluator::class);

        $container->setClass(NodeFinder::class);

        $container->setClass(SymbolExtractor::class);
        $container->setClass(DocCommentSymbolExtractor::class);
        $container->setAlias(NodePathSymbolExtractor::class, DocCommentSymbolExtractor::class, true);
        $container->setClass(GlobalSymbolExtractor::class);
        $container->setAlias(NodePathSymbolExtractor::class, GlobalSymbolExtractor::class, true);
        $container->setClass(MemberSymbolExtractor::class);
        $container->setAlias(NodePathSymbolExtractor::class, MemberSymbolExtractor::class, true);

        $container->setClass(SymbolReflection::class);
        $container->setClass(Importer::class);

        $container->setCallable(DocBlockFactoryInterface::class, [DocBlockFactory::class, 'createInstance'], false, [new Value([])]);
        $container->setClass(PhpDocFormatter::class);
        $container->setClass(HtmlConverter::class, null, false, [new Value([])]);

        $container->setClass(CodeActionProvider::class, ImportCodeActionProvider::class, true);

        $container->setClass(CommandProvider::class, WorkspaceEditCommandProvider::class, true);

        $container->setClass(CompletionProvider::class, VariableCompletionProvider::class, true);
        $container->setClass(CompletionProvider::class, SymbolCompletionProvider::class, true);
        $container->setClass(SymbolCompleter::class, GlobalSymbolCompleter::class, true);
        $container->setClass(SymbolCompleter::class, ImportSymbolCompleter::class, true);
        $container->setClass(SymbolCompleter::class, MemberSymbolCompleter::class, true);

        $container->setClass(DocumentSymbolsProvider::class, ReflectionDocumentSymbolsProvider::class, true);

        $container->setClass(GoToDefinitionProvider::class, SymbolGoToDefinitionProvider::class, true);

        $container->setClass(HoverProvider::class, SymbolHoverProvider::class, true);
        $container->setClass(HoverProvider::class, ExpressionTypeHoverProvider::class, true);
        $container->setClass(HoverFormatter::class);

        $container->setClass(IndexDataProvider::class, GlobalReferencesIndexDataProvider::class, true);

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

        $container->setClass(WorkspaceDiagnosticsProvider::class, PhpStanDiagnosticsProvider::class, true);
        $container->setClass(PropertiesClassReflectionExtension::class, UniversalObjectCratesClassReflectionExtension::class, true, [new Value(['stdClass'])]);
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
        $container->setClass(Rule::class, Rules\Arrays\DuplicateKeysInLiteralArraysRule::class, true);
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
        $container->setClass(Rule::class, Rules\Constants\ConstantRule::class, true);
        $container->setClass(Rule::class, Rules\Exceptions\CaughtExceptionExistenceRule::class, true);
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
}
