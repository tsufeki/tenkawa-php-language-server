<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Event;

use Tsufeki\Tenkawa\Server\Uri;

interface OnFileChange
{
    /**
     * @param Uri[] $uris
     */
    public function onFileChange(array $uris): \Generator;
}
