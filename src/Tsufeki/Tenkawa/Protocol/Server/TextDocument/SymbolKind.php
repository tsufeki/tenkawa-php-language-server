<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Protocol\Server\TextDocument;

/**
 * A symbol kind.
 */
class SymbolKind
{
    const FILE = 1;
    const MODULE = 2;
    const NAMESPACE = 3;
    const PACKAGE = 4;
    const CLASS_ = 5;
    const METHOD = 6;
    const PROPERTY = 7;
    const FIELD = 8;
    const CONSTRUCTOR = 9;
    const ENUM = 10;
    const INTERFACE_ = 11;
    const FUNCTION_ = 12;
    const VARIABLE = 13;
    const CONSTANT = 14;
    const STRING_ = 15;
    const NUMBER = 16;
    const BOOLEAN_ = 17;
    const ARRAY_ = 18;
    const OBJECT_ = 19;
    const KEY = 20;
    const NULL_ = 21;
    const ENUM_MEMBER = 22;
    const STRUCT = 23;
    const EVENT = 24;
    const OPERATOR = 25;
    const TYPE_PARAMETER = 26;
}
