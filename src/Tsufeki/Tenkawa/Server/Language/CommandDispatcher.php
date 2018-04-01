<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Server\Language;

use Tsufeki\KayoJsonMapper\Mapper;
use Tsufeki\Tenkawa\Server\Exception\UnknownCommandException;

class CommandDispatcher
{
    /**
     * @var array<string,CommandProvider>
     */
    private $providers;

    /**
     * @var Mapper
     */
    private $mapper;

    /**
     * @param CommandProvider[] $providers
     */
    public function __construct(array $providers, Mapper $mapper)
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->getCommand()] = $provider;
        }

        $this->mapper = $mapper;
    }

    public function execute(string $command, array $args): \Generator
    {
        $provider = $this->providers[$command] ?? null;

        if ($provider === null) {
            throw new UnknownCommandException($command);
        }

        $mappedArgs = $this->mapper->loadArguments($args, [$provider, 'execute']);

        return yield $provider->execute(...$mappedArgs);
    }

    public function hasProviders(): bool
    {
        return !empty($this->providers);
    }

    public function getCommands(): array
    {
        return array_keys($this->providers);
    }
}
