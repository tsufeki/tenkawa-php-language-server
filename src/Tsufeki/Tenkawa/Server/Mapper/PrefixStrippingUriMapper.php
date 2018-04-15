<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Mapper;

use phpDocumentor\Reflection\Type;
use Tsufeki\KayoJsonMapper\Context\Context;
use Tsufeki\KayoJsonMapper\Dumper\Dumper;
use Tsufeki\KayoJsonMapper\Loader\Loader;
use Tsufeki\Tenkawa\Server\Uri;
use Tsufeki\Tenkawa\Server\Utils\StringUtils;

class PrefixStrippingUriMapper implements Loader, Dumper
{
    /**
     * @var string
     */
    private $prefix;

    /**
     * @var int
     */
    private $prefixLength;

    /**
     * @var string
     */
    private $prefixNormalized;

    /**
     * @var UriMapper
     */
    private $uriMapper;

    public function __construct(string $prefix)
    {
        $this->prefix = $prefix === '' ? '' : rtrim($prefix, '/') . '/';
        $this->prefixLength = strlen($this->prefix);
        $this->prefixNormalized = rtrim(Uri::fromString($this->prefix)->getNormalized(), '/') . '/';

        $this->uriMapper = new UriMapper();
    }

    public function getSupportedTypes(): array
    {
        return $this->uriMapper->getSupportedTypes();
    }

    public function dump($value, Context $context)
    {
        return $this->stripPrefix($this->uriMapper->dump($value, $context));
    }

    public function load($data, Type $type, Context $context)
    {
        return $this->uriMapper->load($this->restorePrefix($data), $type, $context);
    }

    public function stripPrefix(string $uri): string
    {
        if ($this->prefixLength !== 0 && StringUtils::startsWith($uri, $this->prefix)) {
            return substr($uri, $this->prefixLength);
        }

        return $uri;
    }

    public function restorePrefix(string $uri, bool $normalized = false): string
    {
        if ($this->prefixLength !== 0 && strpos($uri, '://') === false) {
            return ($normalized ? $this->prefixNormalized : $this->prefix) . $uri;
        }

        return $uri;
    }
}
