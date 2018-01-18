<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Protocol\Server\TextDocument;

/**
 * The kind of a completion entry.
 */
class CompletionItemKind
{
    const TEXT = 1;
    const METHOD = 2;
    const FUNCTION_ = 3;
    const CONSTRUCTOR = 4;
    const FIELD = 5;
    const VARIABLE = 6;
    const CLASS_ = 7;
    const INTERFACE_ = 8;
    const MODULE = 9;
    const PROPERTY = 10;
    const UNIT = 11;
    const VALUE = 12;
    const ENUM = 13;
    const KEYWORD = 14;
    const SNIPPET = 15;
    const COLOR = 16;
    const FILE = 17;
    const REFERENCE = 18;
    const FOLDER = 19;
    const ENUM_MEMBER = 20;
    const CONSTANT = 21;
    const STRUCT = 22;
    const EVENT = 23;
    const OPERATOR = 24;
    const TYPE_PARAMETER = 25;
}
