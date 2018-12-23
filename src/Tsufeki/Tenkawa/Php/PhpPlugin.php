<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php;

use League\HTMLToMarkdown\HtmlConverter;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\DocBlockFactoryInterface;
use PhpParser\PrettyPrinter\Standard;
use PHPStan\Analyser\NodeScopeResolver;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\ScopeFactory;
use PHPStan\Analyser\TypeSpecifier;
use PHPStan\Analyser\TypeSpecifierAwareExtension;
use PHPStan\Broker\Broker;
use PHPStan\File\FileHelper;
use PHPStan\Parser\Parser as PhpStanParser;
use PHPStan\PhpDoc\PhpDocNodeResolver;
use PHPStan\PhpDoc\PhpDocStringResolver;
use PHPStan\PhpDoc\TypeNodeResolver;
use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Reflection\Php\UniversalObjectCratesClassReflectionExtension;
use PHPStan\Reflection\PropertiesClassReflectionExtension;
use PHPStan\Reflection\SignatureMap\SignatureMapParser;
use PHPStan\Reflection\SignatureMap\SignatureMapProvider;
use PHPStan\Rules;
use PHPStan\Rules\ClassCaseSensitivityCheck;
use PHPStan\Rules\Comparison\ConstantConditionRuleHelper;
use PHPStan\Rules\Comparison\ImpossibleCheckTypeHelper;
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
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\FileTypeMapper;
use PHPStan\Type\FunctionTypeSpecifyingExtension;
use PHPStan\Type\MethodTypeSpecifyingExtension;
use PHPStan\Type\Php\ArgumentBasedFunctionReturnTypeExtension;
use PHPStan\Type\Php\ArrayFillFunctionReturnTypeExtension;
use PHPStan\Type\Php\ArrayFillKeysFunctionReturnTypeExtension;
use PHPStan\Type\Php\ArrayFilterFunctionReturnTypeReturnTypeExtension;
use PHPStan\Type\Php\ArrayKeyExistsFunctionTypeSpecifyingExtension;
use PHPStan\Type\Php\ArrayKeyFirstDynamicReturnTypeExtension;
use PHPStan\Type\Php\ArrayKeyLastDynamicReturnTypeExtension;
use PHPStan\Type\Php\ArrayKeysFunctionDynamicReturnTypeExtension;
use PHPStan\Type\Php\ArrayMapFunctionReturnTypeExtension;
use PHPStan\Type\Php\ArrayMergeFunctionDynamicReturnTypeExtension;
use PHPStan\Type\Php\ArrayPointerFunctionsDynamicReturnTypeExtension;
use PHPStan\Type\Php\ArrayPopFunctionReturnTypeExtension;
use PHPStan\Type\Php\ArrayReduceFunctionReturnTypeExtension;
use PHPStan\Type\Php\ArraySearchFunctionDynamicReturnTypeExtension;
use PHPStan\Type\Php\ArrayShiftFunctionReturnTypeExtension;
use PHPStan\Type\Php\ArrayValuesFunctionDynamicReturnTypeExtension;
use PHPStan\Type\Php\AssertFunctionTypeSpecifyingExtension;
use PHPStan\Type\Php\CountFunctionReturnTypeExtension;
use PHPStan\Type\Php\CurlInitReturnTypeExtension;
use PHPStan\Type\Php\DefineConstantTypeSpecifyingExtension;
use PHPStan\Type\Php\DefinedConstantTypeSpecifyingExtension;
use PHPStan\Type\Php\DioStatDynamicFunctionReturnTypeExtension;
use PHPStan\Type\Php\ExplodeFunctionDynamicReturnTypeExtension;
use PHPStan\Type\Php\GetParentClassDynamicFunctionReturnTypeExtension;
use PHPStan\Type\Php\GettimeofdayDynamicFunctionReturnTypeExtension;
use PHPStan\Type\Php\InArrayFunctionTypeSpecifyingExtension;
use PHPStan\Type\Php\IsAFunctionTypeSpecifyingExtension;
use PHPStan\Type\Php\IsArrayFunctionTypeSpecifyingExtension;
use PHPStan\Type\Php\IsBoolFunctionTypeSpecifyingExtension;
use PHPStan\Type\Php\IsCallableFunctionTypeSpecifyingExtension;
use PHPStan\Type\Php\IsCountableFunctionTypeSpecifyingExtension;
use PHPStan\Type\Php\IsFloatFunctionTypeSpecifyingExtension;
use PHPStan\Type\Php\IsIntFunctionTypeSpecifyingExtension;
use PHPStan\Type\Php\IsIterableFunctionTypeSpecifyingExtension;
use PHPStan\Type\Php\IsNullFunctionTypeSpecifyingExtension;
use PHPStan\Type\Php\IsNumericFunctionTypeSpecifyingExtension;
use PHPStan\Type\Php\IsObjectFunctionTypeSpecifyingExtension;
use PHPStan\Type\Php\IsResourceFunctionTypeSpecifyingExtension;
use PHPStan\Type\Php\IsScalarFunctionTypeSpecifyingExtension;
use PHPStan\Type\Php\IsStringFunctionTypeSpecifyingExtension;
use PHPStan\Type\Php\IsSubclassOfFunctionTypeSpecifyingExtension;
use PHPStan\Type\Php\JsonThrowOnErrorDynamicReturnTypeExtension;
use PHPStan\Type\Php\MbStrlenFunctionReturnTypeExtension;
use PHPStan\Type\Php\MethodExistsTypeSpecifyingExtension;
use PHPStan\Type\Php\MicrotimeFunctionReturnTypeExtension;
use PHPStan\Type\Php\MinMaxFunctionReturnTypeExtension;
use PHPStan\Type\Php\PathinfoFunctionDynamicReturnTypeExtension;
use PHPStan\Type\Php\PropertyExistsTypeSpecifyingExtension;
use PHPStan\Type\Php\RangeFunctionReturnTypeExtension;
use PHPStan\Type\Php\ReplaceFunctionsDynamicReturnTypeExtension;
use PHPStan\Type\Php\StatDynamicReturnTypeExtension;
use PHPStan\Type\Php\StrSplitFunctionReturnTypeExtension;
use PHPStan\Type\Php\StrtotimeFunctionReturnTypeExtension;
use PHPStan\Type\Php\TypeSpecifyingFunctionsDynamicReturnTypeExtension;
use PHPStan\Type\Php\VarExportFunctionDynamicReturnTypeExtension;
use PHPStan\Type\Php\VersionCompareFunctionDynamicReturnTypeExtension;
use PHPStan\Type\StaticMethodTypeSpecifyingExtension;
use Tsufeki\HmContainer\Container;
use Tsufeki\HmContainer\Definition\Value;
use Tsufeki\Tenkawa\Php\Composer\ComposerService;
use Tsufeki\Tenkawa\Php\Feature\Completion\GlobalSymbolCompleter;
use Tsufeki\Tenkawa\Php\Feature\Completion\ImportSymbolCompleter;
use Tsufeki\Tenkawa\Php\Feature\Completion\MemberSymbolCompleter;
use Tsufeki\Tenkawa\Php\Feature\Completion\SymbolCompleter;
use Tsufeki\Tenkawa\Php\Feature\Completion\SymbolCompletionProvider;
use Tsufeki\Tenkawa\Php\Feature\Completion\VariableCompletionProvider;
use Tsufeki\Tenkawa\Php\Feature\Completion\WholeFileSnippetCompletionProvider;
use Tsufeki\Tenkawa\Php\Feature\DefinitionSymbolExtractor;
use Tsufeki\Tenkawa\Php\Feature\DocCommentSymbolExtractor;
use Tsufeki\Tenkawa\Php\Feature\DocumentSymbols\SymbolDocumentSymbolsProvider;
use Tsufeki\Tenkawa\Php\Feature\GlobalSymbolExtractor;
use Tsufeki\Tenkawa\Php\Feature\GoToDefinition\SymbolGoToDefinitionProvider;
use Tsufeki\Tenkawa\Php\Feature\Hover\ExpressionTypeHoverProvider;
use Tsufeki\Tenkawa\Php\Feature\Hover\HoverFormatter;
use Tsufeki\Tenkawa\Php\Feature\Hover\SymbolHoverProvider;
use Tsufeki\Tenkawa\Php\Feature\MemberSymbolExtractor;
use Tsufeki\Tenkawa\Php\Feature\NodeFinder;
use Tsufeki\Tenkawa\Php\Feature\NodePathSymbolExtractor;
use Tsufeki\Tenkawa\Php\Feature\PhpDocFormatter;
use Tsufeki\Tenkawa\Php\Feature\Refactoring\Differ;
use Tsufeki\Tenkawa\Php\Feature\Refactoring\EditHelper;
use Tsufeki\Tenkawa\Php\Feature\Refactoring\FineDiffer;
use Tsufeki\Tenkawa\Php\Feature\Refactoring\FixAutoloadClassNameRefactoring;
use Tsufeki\Tenkawa\Php\Feature\Refactoring\ImportCodeActionProvider;
use Tsufeki\Tenkawa\Php\Feature\Refactoring\Importer;
use Tsufeki\Tenkawa\Php\Feature\Refactoring\RefactoringExecutor;
use Tsufeki\Tenkawa\Php\Feature\Refactoring\WorkspaceEditCommandProvider;
use Tsufeki\Tenkawa\Php\Feature\References\GlobalReferenceFinder;
use Tsufeki\Tenkawa\Php\Feature\References\GlobalReferencesIndexDataProvider;
use Tsufeki\Tenkawa\Php\Feature\References\MemberReferenceFinder;
use Tsufeki\Tenkawa\Php\Feature\References\ReferenceFinder;
use Tsufeki\Tenkawa\Php\Feature\References\SymbolReferencesProvider;
use Tsufeki\Tenkawa\Php\Feature\SignatureHelp\ReflectionSignatureFinder;
use Tsufeki\Tenkawa\Php\Feature\SignatureHelp\SignatureFinder;
use Tsufeki\Tenkawa\Php\Feature\SignatureHelp\SymbolSignatureHelpProvider;
use Tsufeki\Tenkawa\Php\Feature\SymbolExtractor;
use Tsufeki\Tenkawa\Php\Feature\SymbolReflection;
use Tsufeki\Tenkawa\Php\Feature\WorkspaceSymbols\ReflectionWorkspaceSymbolsProvider;
use Tsufeki\Tenkawa\Php\Index\StubsIndexer;
use Tsufeki\Tenkawa\Php\Parser\Parser;
use Tsufeki\Tenkawa\Php\Parser\ParserDiagnosticsProvider;
use Tsufeki\Tenkawa\Php\Parser\PhpParserAdapter;
use Tsufeki\Tenkawa\Php\PhpStan\Analyser;
use Tsufeki\Tenkawa\Php\PhpStan\AstPruner;
use Tsufeki\Tenkawa\Php\PhpStan\DocumentParser;
use Tsufeki\Tenkawa\Php\PhpStan\ErrorTolerantPrettyPrinter;
use Tsufeki\Tenkawa\Php\PhpStan\IndexBroker;
use Tsufeki\Tenkawa\Php\PhpStan\PhpDocResolver;
use Tsufeki\Tenkawa\Php\PhpStan\PhpStanDiagnosticsProvider;
use Tsufeki\Tenkawa\Php\PhpStan\PhpStanSignatureFinder;
use Tsufeki\Tenkawa\Php\PhpStan\PhpStanTypeInference;
use Tsufeki\Tenkawa\Php\PhpStan\SignatureVariantFactory;
use Tsufeki\Tenkawa\Php\Reflection\ClassResolver;
use Tsufeki\Tenkawa\Php\Reflection\ClassResolverExtension;
use Tsufeki\Tenkawa\Php\Reflection\ConstExprEvaluator;
use Tsufeki\Tenkawa\Php\Reflection\IndexReflectionProvider;
use Tsufeki\Tenkawa\Php\Reflection\InheritanceTreeTraverser;
use Tsufeki\Tenkawa\Php\Reflection\InheritPhpDocClassResolverExtension;
use Tsufeki\Tenkawa\Php\Reflection\MembersFromAnnotationClassResolverExtension;
use Tsufeki\Tenkawa\Php\Reflection\ReflectionIndexDataProvider;
use Tsufeki\Tenkawa\Php\Reflection\ReflectionProvider;
use Tsufeki\Tenkawa\Php\Reflection\ReflectionTransformer;
use Tsufeki\Tenkawa\Php\Reflection\StubsReflectionTransformer;
use Tsufeki\Tenkawa\Php\TypeInference\TypeInference;
use Tsufeki\Tenkawa\Server\Event\OnFileChange;
use Tsufeki\Tenkawa\Server\Feature\CodeAction\CodeActionProvider;
use Tsufeki\Tenkawa\Server\Feature\Command\CommandProvider;
use Tsufeki\Tenkawa\Server\Feature\Completion\CompletionProvider;
use Tsufeki\Tenkawa\Server\Feature\Diagnostics\DiagnosticsProvider;
use Tsufeki\Tenkawa\Server\Feature\Diagnostics\WorkspaceDiagnosticsProvider;
use Tsufeki\Tenkawa\Server\Feature\DocumentSymbols\DocumentSymbolsProvider;
use Tsufeki\Tenkawa\Server\Feature\GoToDefinition\GoToDefinitionProvider;
use Tsufeki\Tenkawa\Server\Feature\Hover\HoverProvider;
use Tsufeki\Tenkawa\Server\Feature\References\ReferencesProvider;
use Tsufeki\Tenkawa\Server\Feature\SignatureHelp\SignatureHelpProvider;
use Tsufeki\Tenkawa\Server\Feature\WorkspaceSymbols\WorkspaceSymbolsProvider;
use Tsufeki\Tenkawa\Server\Index\FileFilterFactory;
use Tsufeki\Tenkawa\Server\Index\GlobalIndexer;
use Tsufeki\Tenkawa\Server\Index\IndexDataProvider;
use Tsufeki\Tenkawa\Server\Io\FileLister\FileFilter;
use Tsufeki\Tenkawa\Server\Io\FileLister\GlobFileFilter;
use Tsufeki\Tenkawa\Server\Io\FileLister\GlobRejectDirectoryFilter;
use Tsufeki\Tenkawa\Server\Plugin;
use Tsufeki\Tenkawa\Server\Utils\StringTemplate;

class PhpPlugin extends Plugin
{
    public function configureContainer(Container $container, array $options): void
    {
        $container->setClass(Parser::class, PhpParserAdapter::class);
        $container->setClass(DiagnosticsProvider::class, ParserDiagnosticsProvider::class, true);

        if ($options['index.stubs'] ?? true) {
            $container->setClass(GlobalIndexer::class, StubsIndexer::class, true);
            $container->setValue(FileFilter::class, new GlobRejectDirectoryFilter('../../jetbrains/phpstorm-stubs/tests'), true);
        }

        $container->setValue(FileFilter::class, new GlobFileFilter('**/*.php', 'php'), true);
        $container->setValue(FileFilter::class, new GlobRejectDirectoryFilter('{var,app/cache,cache,.git}'), true);
        $container->setClass(IndexDataProvider::class, ReflectionIndexDataProvider::class, true);
        $container->setClass(ReflectionTransformer::class, StubsReflectionTransformer::class, true);
        $container->setClass(ReflectionProvider::class, IndexReflectionProvider::class);
        $container->setClass(InheritanceTreeTraverser::class);
        $container->setClass(ClassResolver::class);
        $container->setClass(ClassResolverExtension::class, InheritPhpDocClassResolverExtension::class, true);
        $container->setClass(ClassResolverExtension::class, MembersFromAnnotationClassResolverExtension::class, true);
        $container->setClass(ConstExprEvaluator::class);

        $container->setClass(ComposerService::class);
        $container->setClass(FileFilterFactory::class, ComposerService::class, true);
        $container->setClass(OnFileChange::class, ComposerService::class, true);

        $container->setClass(NodeFinder::class);
        $container->setClass(RefactoringExecutor::class);
        $container->setClass(Differ::class, FineDiffer::class);

        $container->setClass(SymbolExtractor::class);
        $container->setClass(DocCommentSymbolExtractor::class);
        $container->setAlias(NodePathSymbolExtractor::class, DocCommentSymbolExtractor::class, true);
        $container->setClass(GlobalSymbolExtractor::class);
        $container->setAlias(NodePathSymbolExtractor::class, GlobalSymbolExtractor::class, true);
        $container->setClass(MemberSymbolExtractor::class);
        $container->setAlias(NodePathSymbolExtractor::class, MemberSymbolExtractor::class, true);
        $container->setClass(DefinitionSymbolExtractor::class);
        $container->setAlias(NodePathSymbolExtractor::class, DefinitionSymbolExtractor::class, true);

        $container->setClass(SymbolReflection::class);
        $container->setClass(Importer::class);
        $container->setClass(EditHelper::class);

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

        $container->setClass(CompletionProvider::class, WholeFileSnippetCompletionProvider::class, true, ['wholeFileSnippets']);
        $container->setValue('wholeFileSnippets', [
            'key' => 'class',
            'description' => 'Class snippet',
            'template' => new StringTemplate("<?php\n\nnamespace {{namespace}};\n\nclass {{class}}\n{\n    \n}"),
        ], true);
        $container->setValue('wholeFileSnippets', [
            'key' => 'interface',
            'description' => 'Interface snippet',
            'template' => new StringTemplate("<?php\n\nnamespace {{namespace}};\n\ninterface {{class}}\n{\n    \n}"),
        ], true);
        $container->setValue('wholeFileSnippets', [
            'key' => 'trait',
            'description' => 'Trait snippet',
            'template' => new StringTemplate("<?php\n\nnamespace {{namespace}};\n\ntrait {{class}}\n{\n    \n}"),
        ], true);

        $container->setClass(DocumentSymbolsProvider::class, SymbolDocumentSymbolsProvider::class, true);

        $container->setClass(GoToDefinitionProvider::class, SymbolGoToDefinitionProvider::class, true);

        $container->setClass(HoverProvider::class, SymbolHoverProvider::class, true);
        $container->setClass(HoverProvider::class, ExpressionTypeHoverProvider::class, true);
        $container->setClass(HoverFormatter::class);

        $container->setClass(FixAutoloadClassNameRefactoring::class);
        $container->setAlias(DiagnosticsProvider::class, FixAutoloadClassNameRefactoring::class, true);
        $container->setAlias(CodeActionProvider::class, FixAutoloadClassNameRefactoring::class, true);
        $container->setAlias(CommandProvider::class, FixAutoloadClassNameRefactoring::class, true);

        $container->setClass(ReferencesProvider::class, SymbolReferencesProvider::class, true);
        $container->setClass(GlobalReferenceFinder::class);
        $container->setAlias(ReferenceFinder::class, GlobalReferenceFinder::class, true);
        $container->setClass(IndexDataProvider::class, GlobalReferencesIndexDataProvider::class, true);
        $container->setClass(MemberReferenceFinder::class);
        $container->setAlias(ReferenceFinder::class, MemberReferenceFinder::class, true);

        $container->setClass(SignatureHelpProvider::class, SymbolSignatureHelpProvider::class, true);
        $container->setClass(SignatureFinder::class, PhpStanSignatureFinder::class, true);
        $container->setClass(SignatureFinder::class, ReflectionSignatureFinder::class, true);

        $container->setClass(WorkspaceSymbolsProvider::class, ReflectionWorkspaceSymbolsProvider::class, true);

        $container->setValue('checkAlwaysTrueCheckTypeFunctionCall', true);
        $container->setValue('checkAlwaysTrueInstanceof', true);
        $container->setValue('checkAlwaysTrueStrictComparison', true);
        $container->setValue('checkArgumentTypes', true);
        $container->setValue('checkArgumentsPassedByReference', true);
        $container->setValue('checkClassCaseSensitivity', true);
        $container->setValue('checkFunctionNameCase', true);
        $container->setValue('checkMaybeUndefinedVariables', true);
        $container->setValue('checkNullables', true);
        $container->setValue('checkThisOnly', false);
        $container->setValue('checkUnionTypes', true);
        $container->setValue('cliArgumentsVariablesRegistered', true);
        $container->setValue('earlyTerminatingMethodCalls', []);
        $container->setValue('polluteCatchScopeWithTryAssignments', false);
        $container->setValue('polluteScopeWithLoopInitialAssignments', false);
        $container->setValue('reportMagicProperties', true);
        $container->setValue('reportMagicMethods', true);
        $container->setValue('reportMaybes', true);
        $container->setValue('universalObjectCratesClasses', ['stdClass', 'SimpleXMLElement']);
        $container->setValue('dynamicConstantNames', [
            'ICONV_IMPL',
            'PHP_VERSION',
            'PHP_EXTRA_VERSION',
            'PHP_OS',
            'PHP_OS_FAMILY',
            'PHP_SAPI',
            'DEFAULT_INCLUDE_PATH',
            'PEAR_INSTALL_DIR',
            'PEAR_EXTENSION_DIR',
            'PHP_EXTENSION_DIR',
            'PHP_PREFIX',
            'PHP_BINDIR',
            'PHP_BINARY',
            'PHP_MANDIR',
            'PHP_LIBDIR',
            'PHP_DATADIR',
            'PHP_SYSCONFDIR',
            'PHP_LOCALSTATEDIR',
            'PHP_CONFIG_FILE_PATH',
            'PHP_CONFIG_FILE_SCAN_DIR',
            'PHP_SHLIB_SUFFIX',
            'PHP_FD_SETSIZE',
            'PHP_MAJOR_VERSION',
            'PHP_MINOR_VERSION',
            'PHP_RELEASE_VERSION',
            'PHP_VERSION_ID',
            'PHP_ZTS',
            'PHP_DEBUG',
            'PHP_MAXPATHLEN',
        ]);

        $container->setClass(TypeInference::class, PhpStanTypeInference::class);
        $container->setClass(AstPruner::class);
        $container->setClass(NodeScopeResolver::class, null, false,
            [null, null, null, null, null, 'polluteScopeWithLoopInitialAssignments', 'polluteCatchScopeWithTryAssignments', 'earlyTerminatingMethodCalls']
        );
        $container->setClass(ScopeFactory::class, null, false, [new Value(Scope::class), null, null, null, 'dynamicConstantNames']);
        $container->setClass(DocumentParser::class);
        $container->setAlias(PhpStanParser::class, DocumentParser::class);
        $container->setClass(IndexBroker::class, null, false, ['universalObjectCratesClasses']);
        $container->setAlias(Broker::class, IndexBroker::class);
        $container->setClass(Standard::class, ErrorTolerantPrettyPrinter::class, false, [new Value([])]);
        $container->setCallable(TypeSpecifier::class, [$this, 'createTypeSpecifier']);
        $container->setClass(PhpDocResolver::class);
        $container->setAlias(FileTypeMapper::class, PhpDocResolver::class);
        $container->setClass(PhpDocStringResolver::class);
        $container->setClass(TypeStringResolver::class);
        $container->setClass(Lexer::class);
        $container->setClass(PhpDocParser::class);
        $container->setClass(PhpDocNodeResolver::class);
        $container->setClass(TypeNodeResolver::class);
        $container->setClass(TypeParser::class);
        $container->setClass(ConstExprParser::class);
        $container->setClass(FileHelper::class, null, false, [new Value(getcwd())]);
        $container->setClass(Analyser::class);
        $container->setClass(SignatureVariantFactory::class);
        $container->setClass(SignatureMapProvider::class);
        $container->setClass(SignatureMapParser::class);

        $container->setClass(WorkspaceDiagnosticsProvider::class, PhpStanDiagnosticsProvider::class, true);
        $container->setClass(PropertiesClassReflectionExtension::class, UniversalObjectCratesClassReflectionExtension::class, true, ['universalObjectCratesClasses']);
        $container->setClass(Registry::class);
        $container->setClass(RuleLevelHelper::class, null, false, [null, 'checkNullables', 'checkThisOnly', 'checkUnionTypes']);
        $container->setClass(ClassCaseSensitivityCheck::class);
        $container->setClass(FunctionCallParametersCheck::class, null, false, [null, 'checkArgumentTypes', 'checkArgumentsPassedByReference']);
        $container->setClass(FunctionDefinitionCheck::class, null, false, [null, null, 'checkClassCaseSensitivity', 'checkThisOnly']);
        $container->setClass(FunctionReturnTypeCheck::class);
        $container->setClass(UnusedFunctionParametersCheck::class);
        $container->setClass(PropertyReflectionFinder::class);
        $container->setClass(PropertyDescriptor::class);
        $container->setClass(ConstantConditionRuleHelper::class);
        $container->setClass(ImpossibleCheckTypeHelper::class);

        $container->setClass(DynamicFunctionReturnTypeExtension::class, ArgumentBasedFunctionReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, ArrayFillFunctionReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, ArrayFillKeysFunctionReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, ArrayFilterFunctionReturnTypeReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, ArrayKeyFirstDynamicReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, ArrayKeyLastDynamicReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, ArrayKeysFunctionDynamicReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, ArrayMapFunctionReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, ArrayMergeFunctionDynamicReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, ArrayPointerFunctionsDynamicReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, ArrayPopFunctionReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, ArrayReduceFunctionReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, ArraySearchFunctionDynamicReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, ArrayShiftFunctionReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, ArrayValuesFunctionDynamicReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, CountFunctionReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, CurlInitReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, DioStatDynamicFunctionReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, ExplodeFunctionDynamicReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, GetParentClassDynamicFunctionReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, GettimeofdayDynamicFunctionReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, JsonThrowOnErrorDynamicReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, MbStrlenFunctionReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, MicrotimeFunctionReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, MinMaxFunctionReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, PathinfoFunctionDynamicReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, RangeFunctionReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, ReplaceFunctionsDynamicReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, StatDynamicReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, StrSplitFunctionReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, StrtotimeFunctionReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, TypeSpecifyingFunctionsDynamicReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, VarExportFunctionDynamicReturnTypeExtension::class, true);
        $container->setClass(DynamicFunctionReturnTypeExtension::class, VersionCompareFunctionDynamicReturnTypeExtension::class, true);
        $container->setClass(FunctionTypeSpecifyingExtension::class, ArrayKeyExistsFunctionTypeSpecifyingExtension::class, true);
        $container->setClass(FunctionTypeSpecifyingExtension::class, AssertFunctionTypeSpecifyingExtension::class, true);
        $container->setClass(FunctionTypeSpecifyingExtension::class, DefineConstantTypeSpecifyingExtension::class, true);
        $container->setClass(FunctionTypeSpecifyingExtension::class, DefinedConstantTypeSpecifyingExtension::class, true);
        $container->setClass(FunctionTypeSpecifyingExtension::class, InArrayFunctionTypeSpecifyingExtension::class, true);
        $container->setClass(FunctionTypeSpecifyingExtension::class, IsAFunctionTypeSpecifyingExtension::class, true);
        $container->setClass(FunctionTypeSpecifyingExtension::class, IsArrayFunctionTypeSpecifyingExtension::class, true);
        $container->setClass(FunctionTypeSpecifyingExtension::class, IsBoolFunctionTypeSpecifyingExtension::class, true);
        $container->setClass(FunctionTypeSpecifyingExtension::class, IsCallableFunctionTypeSpecifyingExtension::class, true);
        $container->setClass(FunctionTypeSpecifyingExtension::class, IsCountableFunctionTypeSpecifyingExtension::class, true);
        $container->setClass(FunctionTypeSpecifyingExtension::class, IsFloatFunctionTypeSpecifyingExtension::class, true);
        $container->setClass(FunctionTypeSpecifyingExtension::class, IsIntFunctionTypeSpecifyingExtension::class, true);
        $container->setClass(FunctionTypeSpecifyingExtension::class, IsIterableFunctionTypeSpecifyingExtension::class, true);
        $container->setClass(FunctionTypeSpecifyingExtension::class, IsNullFunctionTypeSpecifyingExtension::class, true);
        $container->setClass(FunctionTypeSpecifyingExtension::class, IsNumericFunctionTypeSpecifyingExtension::class, true);
        $container->setClass(FunctionTypeSpecifyingExtension::class, IsObjectFunctionTypeSpecifyingExtension::class, true);
        $container->setClass(FunctionTypeSpecifyingExtension::class, IsResourceFunctionTypeSpecifyingExtension::class, true);
        $container->setClass(FunctionTypeSpecifyingExtension::class, IsScalarFunctionTypeSpecifyingExtension::class, true);
        $container->setClass(FunctionTypeSpecifyingExtension::class, IsStringFunctionTypeSpecifyingExtension::class, true);
        $container->setClass(FunctionTypeSpecifyingExtension::class, IsSubclassOfFunctionTypeSpecifyingExtension::class, true);
        $container->setClass(FunctionTypeSpecifyingExtension::class, MethodExistsTypeSpecifyingExtension::class, true);
        $container->setClass(FunctionTypeSpecifyingExtension::class, PropertyExistsTypeSpecifyingExtension::class, true);

        $container->setClass(Rule::class, Rules\Arrays\AppendedArrayItemTypeRule::class, true);
        $container->setClass(Rule::class, Rules\Arrays\AppendedArrayKeyTypeRule::class, true, [null, 'checkUnionTypes']);
        $container->setClass(Rule::class, Rules\Arrays\DuplicateKeysInLiteralArraysRule::class, true);
        $container->setClass(Rule::class, Rules\Arrays\InvalidKeyInArrayDimFetchRule::class, true, ['reportMaybes']);
        $container->setClass(Rule::class, Rules\Arrays\InvalidKeyInArrayItemRule::class, true, ['reportMaybes']);
        $container->setClass(Rule::class, Rules\Arrays\IterableInForeachRule::class, true);
        $container->setClass(Rule::class, Rules\Arrays\NonexistentOffsetInArrayDimFetchRule::class, true);
        // $container->setClass(Rule::class, Rules\Cast\InvalidCastRule::class, true);
        $container->setClass(Rule::class, Rules\Cast\InvalidPartOfEncapsedStringRule::class, true);
        $container->setClass(Rule::class, Rules\Cast\UselessCastRule::class, true);
        $container->setClass(Rule::class, Rules\Classes\ClassConstantRule::class, true);
        $container->setClass(Rule::class, Rules\Classes\ExistingClassInClassExtendsRule::class, true);
        $container->setClass(Rule::class, Rules\Classes\ExistingClassInInstanceOfRule::class, true, [null, null, 'checkClassCaseSensitivity']);
        $container->setClass(Rule::class, Rules\Classes\ExistingClassInTraitUseRule::class, true);
        $container->setClass(Rule::class, Rules\Classes\ExistingClassesInClassImplementsRule::class, true);
        $container->setClass(Rule::class, Rules\Classes\ExistingClassesInInterfaceExtendsRule::class, true);
        $container->setClass(Rule::class, Rules\Classes\ImpossibleInstanceOfRule::class, true, ['checkAlwaysTrueInstanceof']);
        $container->setClass(Rule::class, Rules\Classes\InstantiationRule::class, true);
        // $container->setClass(Rule::class, Rules\Classes\RequireParentConstructCallRule::class, true);
        $container->setClass(Rule::class, Rules\Classes\UnusedConstructorParametersRule::class, true);
        $container->setClass(Rule::class, Rules\Comparison\BooleanAndConstantConditionRule::class, true);
        $container->setClass(Rule::class, Rules\Comparison\BooleanNotConstantConditionRule::class, true);
        $container->setClass(Rule::class, Rules\Comparison\BooleanOrConstantConditionRule::class, true);
        $container->setClass(Rule::class, Rules\Comparison\ElseIfConstantConditionRule::class, true);
        $container->setClass(Rule::class, Rules\Comparison\IfConstantConditionRule::class, true);
        $container->setClass(Rule::class, Rules\Comparison\ImpossibleCheckTypeFunctionCallRule::class, true, [null, 'checkAlwaysTrueCheckTypeFunctionCall']);
        $container->setClass(Rule::class, Rules\Comparison\ImpossibleCheckTypeMethodCallRule::class, true, [null, 'checkAlwaysTrueCheckTypeFunctionCall']);
        $container->setClass(Rule::class, Rules\Comparison\ImpossibleCheckTypeStaticMethodCallRule::class, true, [null, 'checkAlwaysTrueCheckTypeFunctionCall']);
        $container->setClass(Rule::class, Rules\Comparison\StrictComparisonOfDifferentTypesRule::class, true, ['checkAlwaysTrueStrictComparison']);
        $container->setClass(Rule::class, Rules\Comparison\TernaryOperatorConstantConditionRule::class, true);
        $container->setClass(Rule::class, Rules\Constants\ConstantRule::class, true);
        // $container->setClass(Rule::class, Rules\Exceptions\CaughtExceptionExistenceRule::class, true, [null, null, 'checkClassCaseSensitivity']);
        $container->setClass(Rule::class, Rules\Functions\CallCallablesRule::class, true, [null, null, 'reportMaybes']);
        $container->setClass(Rule::class, Rules\Functions\CallToFunctionParametersRule::class, true);
        $container->setClass(Rule::class, Rules\Functions\CallToNonExistentFunctionRule::class, true, [null, 'checkFunctionNameCase']);
        $container->setClass(Rule::class, Rules\Functions\ClosureReturnTypeRule::class, true);
        $container->setClass(Rule::class, Rules\Functions\ExistingClassesInClosureTypehintsRule::class, true);
        $container->setClass(Rule::class, Rules\Functions\ExistingClassesInTypehintsRule::class, true);
        $container->setClass(Rule::class, Rules\Functions\InnerFunctionRule::class, true);
        $container->setClass(Rule::class, Rules\Functions\NonExistentDefinedFunctionRule::class, true);
        $container->setClass(Rule::class, Rules\Functions\PrintfParametersRule::class, true);
        // $container->setClass(Rule::class, Rules\Functions\ReturnTypeRule::class, true);
        $container->setClass(Rule::class, Rules\Functions\UnusedClosureUsesRule::class, true);
        $container->setClass(Rule::class, Rules\Methods\CallMethodsRule::class, true, [null, null, null, 'checkFunctionNameCase', 'reportMagicMethods']);
        $container->setClass(Rule::class, Rules\Methods\CallStaticMethodsRule::class, true, [null, null, null, null, 'checkFunctionNameCase', 'reportMagicMethods']);
        $container->setClass(Rule::class, Rules\Methods\ExistingClassesInTypehintsRule::class, true);
        // $container->setClass(Rule::class, Rules\Methods\ReturnTypeRule::class, true);
        $container->setClass(Rule::class, Rules\Namespaces\ExistingNamesInGroupUseRule::class, true, [null, null, 'checkFunctionNameCase']);
        $container->setClass(Rule::class, Rules\Namespaces\ExistingNamesInUseRule::class, true, [null, null, 'checkFunctionNameCase']);
        $container->setClass(Rule::class, Rules\Operators\InvalidBinaryOperationRule::class, true);
        $container->setClass(Rule::class, Rules\Operators\InvalidIncDecOperationRule::class, true, ['checkThisOnly']);
        $container->setClass(Rule::class, Rules\Operators\InvalidUnaryOperationRule::class, true);
        $container->setClass(Rule::class, Rules\PhpDoc\IncompatiblePhpDocTypeRule::class, true);
        $container->setClass(Rule::class, Rules\PhpDoc\InvalidPhpDocTagValueRule::class, true);
        $container->setClass(Rule::class, Rules\PhpDoc\InvalidThrowsPhpDocValueRule::class, true);
        $container->setClass(Rule::class, Rules\Properties\AccessPropertiesRule::class, true, [null, null, 'reportMagicProperties']);
        $container->setClass(Rule::class, Rules\Properties\AccessStaticPropertiesRule::class, true);
        $container->setClass(Rule::class, Rules\Properties\DefaultValueTypesAssignedToPropertiesRule::class, true);
        $container->setClass(Rule::class, Rules\Properties\ExistingClassesInPropertiesRule::class, true, [null, null, 'checkClassCaseSensitivity']);
        $container->setClass(Rule::class, Rules\Properties\ReadingWriteOnlyPropertiesRule::class, true, [null, null, null, 'checkThisOnly']);
        $container->setClass(Rule::class, Rules\Properties\TypesAssignedToPropertiesRule::class, true);
        $container->setClass(Rule::class, Rules\Properties\WritingToReadOnlyPropertiesRule::class, true, [null, null, null, 'checkThisOnly']);
        $container->setClass(Rule::class, Rules\Variables\DefinedVariableInAnonymousFunctionUseRule::class, true, ['checkMaybeUndefinedVariables']);
        $container->setClass(Rule::class, Rules\Variables\DefinedVariableRule::class, true, ['cliArgumentsVariablesRegistered', 'checkMaybeUndefinedVariables']);
        $container->setClass(Rule::class, Rules\Variables\ThisVariableRule::class, true);
        $container->setClass(Rule::class, Rules\Variables\ThrowTypeRule::class, true);
        $container->setClass(Rule::class, Rules\Variables\VariableCertaintyInIssetRule::class, true);
        $container->setClass(Rule::class, Rules\Variables\VariableCloningRule::class, true);
    }

    /**
     * @param FunctionTypeSpecifyingExtension[]        $functionTypeSpecifyingExtensions
     * @param MethodTypeSpecifyingExtension[]          $methodTypeSpecifyingExtensions
     * @param StaticMethodTypeSpecifyingExtension[]    $staticMethodTypeSpecifyingExtensions
     * @param PropertiesClassReflectionExtension[]     $propertiesClassReflectionExtensions
     * @param MethodsClassReflectionExtension[]        $methodsClassReflectionExtensions
     * @param DynamicMethodReturnTypeExtension[]       $dynamicMethodReturnTypeExtensions
     * @param DynamicStaticMethodReturnTypeExtension[] $dynamicStaticMethodReturnTypeExtensions
     * @param DynamicFunctionReturnTypeExtension[]     $dynamicFunctionReturnTypeExtensions
     */
    public function createTypeSpecifier(
        Standard $printer,
        Broker $broker,
        array $functionTypeSpecifyingExtensions,
        array $methodTypeSpecifyingExtensions,
        array $staticMethodTypeSpecifyingExtensions,
        array $propertiesClassReflectionExtensions,
        array $methodsClassReflectionExtensions,
        array $dynamicMethodReturnTypeExtensions,
        array $dynamicStaticMethodReturnTypeExtensions,
        array $dynamicFunctionReturnTypeExtensions
    ): TypeSpecifier {
        $typeSpecifier = new TypeSpecifier(
            $printer,
            $broker,
            $functionTypeSpecifyingExtensions,
            $methodTypeSpecifyingExtensions,
            $staticMethodTypeSpecifyingExtensions
        );

        foreach (array_merge(
            $functionTypeSpecifyingExtensions,
            $methodTypeSpecifyingExtensions,
            $staticMethodTypeSpecifyingExtensions,
            $propertiesClassReflectionExtensions,
            $methodsClassReflectionExtensions,
            $dynamicMethodReturnTypeExtensions,
            $dynamicStaticMethodReturnTypeExtensions,
            $dynamicFunctionReturnTypeExtensions
        ) as $extension) {
            if ($extension instanceof TypeSpecifierAwareExtension) {
                $extension->setTypeSpecifier($typeSpecifier);
            }
        }

        return $typeSpecifier;
    }
}
