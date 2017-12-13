<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__);

return PhpCsFixer\Config::create()
    ->setRules([
        '@Symfony' => true,
        'align_multiline_comment' => [
            'comment_type' => 'all_multiline',
        ],
        'array_syntax' => [
            'syntax' => 'short',
        ],
        'blank_line_after_opening_tag' => false,
        'blank_line_before_statement' => [
            'statements' => ['return', 'throw', 'try'],
        ],
        'braces' => [
            'allow_single_line_closure' => true,
        ],
        'cast_spaces' => [
            'space' => 'none',
        ],
        'concat_space' => [
            'spacing' => 'one',
        ],
        'declare_strict_types' => true,
        'increment_style' => [
            'style' => 'post',
        ],
        'is_null' => [
            'use_yoda_style' => false,
        ],
        'modernize_types_casting' => true,
        'no_alias_functions' => true,
        'no_break_comment' => false,
        'no_superfluous_elseif' => true,
        'no_useless_else' => true,
        'ordered_imports' => true,
        'php_unit_construct' => true,
        'php_unit_dedicate_assert' => true,
        'php_unit_expectation' => true,
        'php_unit_mock' => true,
        'php_unit_namespaced' => true,
        'php_unit_no_expectation_annotation' => true,
        'php_unit_test_class_requires_covers' => true,
        'phpdoc_annotation_without_dot' => false,
        'phpdoc_summary' => false,
        'phpdoc_to_comment' => false,
        'pow_to_exponentiation' => true,
        'random_api_migration' => true,
        'space_after_semicolon' => [
            'remove_in_empty_for_expressions' => true,
        ],
        'ternary_to_null_coalescing' => true,
        'yoda_style' => false,
    ])
    ->setFinder($finder);
