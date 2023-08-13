<?php

$finder = PhpCsFixer\Finder::create()->in(dirname(__DIR__) . '/sail');
$config = new PhpCsFixer\Config();

return $config->setRules([
    '@PSR12' => true,
    'array_indentation' => true,
    'array_syntax' => ['syntax' => 'short'],
    'blank_line_after_namespace' => true,
    'blank_line_after_opening_tag' => true,
    'blank_line_before_statement' => [
        'statements' => [
            'continue',
            'return',
        ],
    ],
    'increment_style' => ['style' => 'post'],
    'blank_line_between_import_groups' => true,
    'blank_lines_before_namespace' => true,
    'control_structure_braces' => true,
    'control_structure_continuation_position' => [
        'position' => 'same_line',
    ],
    'ordered_imports' => ['sort_algorithm' => 'alpha', 'imports_order' => ['const', 'class', 'function']],
    'curly_braces_position' => [
        'control_structures_opening_brace' => 'same_line',
        'functions_opening_brace' => 'next_line_unless_newline_at_signature_end',
        'anonymous_functions_opening_brace' => 'next_line_unless_newline_at_signature_end',
        'classes_opening_brace' => 'next_line_unless_newline_at_signature_end',
        'anonymous_classes_opening_brace' => 'next_line_unless_newline_at_signature_end',
        'allow_single_line_empty_anonymous_classes' => false,
        'allow_single_line_anonymous_functions' => false,
    ],
    'cast_spaces' => ['space' => 'none'],
    'single_quote' => true,
])->setFinder($finder);