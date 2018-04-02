<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Mapper;

use phpDocumentor\Reflection\Type;
use phpDocumentor\Reflection\Types;
use Tsufeki\KayoJsonMapper\Context\Context;
use Tsufeki\KayoJsonMapper\Dumper\Dumper;
use Tsufeki\KayoJsonMapper\Exception\InvalidDataException;
use Tsufeki\KayoJsonMapper\Exception\TypeMismatchException;
use Tsufeki\KayoJsonMapper\Exception\UnsupportedTypeException;
use Tsufeki\KayoJsonMapper\Loader\Loader;
use Tsufeki\Tenkawa\Server\Exception\UriException;
use Tsufeki\Tenkawa\Server\Uri;

class UriMapper implements Loader, Dumper
{
    public function getSupportedTypes(): array
    {
        return ['\\' . Uri::class];
    }

    public function dump($value, Context $context)
    {
        if (!is_object($value) || !($value instanceof Uri)) {
            throw new UnsupportedTypeException();
        }

        return (string)$value;
    }

    public function load($data, Type $type, Context $context)
    {
        if (!($type instanceof Types\Object_) || (string)$type !== '\\' . Uri::class) {
            throw new UnsupportedTypeException();
        }

        if (!is_string($data)) {
            throw new TypeMismatchException('string', $data, $context);
        }

        try {
            return Uri::fromString($data);
        } catch (UriException $e) {
            throw new InvalidDataException($e->getMessage());
        }
    }
}
