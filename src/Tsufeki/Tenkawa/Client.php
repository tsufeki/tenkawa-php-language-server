<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa;

use Tsufeki\BlancheJsonRpc\MappedJsonRpc;
use Tsufeki\Tenkawa\Protocol\LanguageClient;

class Client extends LanguageClient
{
    /**
     * @var MappedJsonRpc
     */
    private $rpc;

    public function __construct(MappedJsonRpc $rpc)
    {
        $this->rpc = $rpc;
    }

    public function publishDiagnostics(Uri $uri, array $diagnostics): \Generator
    {
        yield $this->rpc->notify('textDocument/publishDiagnostics', compact('uri', 'diagnostics'));
    }
}
