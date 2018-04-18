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
     * @var string
     */
    private $prefixNormalized;

    /**
     * @var string
     */
    private $prefixWithSlash;

    /**
     * @var string
     */
    private $prefixNormalizedWithSlash;

    /**
     * @var UriMapper
     */
    private $uriMapper;

    public function __construct(string $prefix)
    {
        $uri = Uri::fromString($prefix);
        $this->prefix = (string)$uri;
        $this->prefixNormalized = $uri->getNormalized();
        $this->prefixWithSlash = $uri->getWithSlash();
        $this->prefixNormalizedWithSlash = $uri->getNormalizedWithSlash();

        $this->uriMapper = new UriMapper();
    }

    public function getPrefix(): string
    {
        return $this->prefix;
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
        if (StringUtils::startsWith($uri, $this->prefixWithSlash)) {
            return substr($uri, strlen($this->prefixWithSlash));
        }

        if ($uri === $this->prefix) {
            return '';
        }

        return $uri;
    }

    public function stripPrefixNormalized(string $uri): string
    {
        if (StringUtils::startsWith($uri, $this->prefixNormalizedWithSlash)) {
            return substr($uri, strlen($this->prefixNormalizedWithSlash));
        }

        if ($uri === $this->prefixNormalized) {
            return '';
        }

        return $uri;
    }

    public function restorePrefix(string $uri): string
    {
        if (strpos($uri, '://') === false) {
            return $this->prefixWithSlash . $uri;
        }

        return $uri;
    }

    public function restorePrefixNormalized(string $uri): string
    {
        if (strpos($uri, '://') === false) {
            return $this->prefixNormalizedWithSlash . $uri;
        }

        return $uri;
    }
}
