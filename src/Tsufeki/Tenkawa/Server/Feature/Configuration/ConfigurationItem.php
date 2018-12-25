<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Feature\Configuration;

class ConfigurationItem
{
    /**
     * The scope to get the configuration section for.
     *
     * @var string|null
     */
    public $scopeUri;

    /**
     * The configuration section asked for.
     *
     * @var string|null
     */
    public $section;
}
