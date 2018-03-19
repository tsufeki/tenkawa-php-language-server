<?php declare(strict_types=1);

namespace Tsufeki\Tenkawa\Php\Index;

use Tsufeki\BlancheJsonRpc\Exception\JsonException;
use Tsufeki\BlancheJsonRpc\Json;
use Tsufeki\Tenkawa\Server\Document\Project;
use Tsufeki\Tenkawa\Server\Exception\IoException;
use Tsufeki\Tenkawa\Server\Index\FileFilterFactory;
use Tsufeki\Tenkawa\Server\Io\FileReader;
use Tsufeki\Tenkawa\Server\Uri;

class ComposerFileFilterFactory implements FileFilterFactory
{
    /**
     * @var FileReader
     */
    private $fileReader;

    public function __construct(FileReader $fileReader)
    {
        $this->fileReader = $fileReader;
    }

    public function getFilter(Project $project): \Generator
    {
        $rejectGlobs = [];
        $acceptGlobs = [];
        $forceRejectGlobs = [];

        $root = (string)$project->getRootUri();
        // TODO: Handle custom vendor dir
        $vendorDir = "$root/vendor";
        $installed = null;

        try {
            // Check if this is a composer project
            yield $this->fileReader->read(Uri::fromString("$root/composer.json"));

            $installed = Json::decode(yield $this->fileReader->read(
                Uri::fromString("$vendorDir/composer/installed.json"
            )));
        } catch (IoException $e) {
        } catch (JsonException $e) {
        }

        if (is_array($installed)) {
            foreach ($installed as $package) {
                if (!is_object($package) || !($package instanceof \stdClass)) {
                    continue;
                }
                $this->processPackage($vendorDir, $package, $rejectGlobs, $acceptGlobs, $forceRejectGlobs);
            }
        }

        return new ComposerFileFilter($rejectGlobs, $acceptGlobs, $forceRejectGlobs);
    }

    /**
     * @param string[] $rejectGlobs
     * @param string[] $acceptGlobs
     * @param string[] $forceRejectGlobs
     */
    private function processPackage(
        string $vendorDir,
        \stdClass $package,
        array &$rejectGlobs,
        array &$acceptGlobs,
        array &$forceRejectGlobs
    ) {
        $name = $package->name ?? null;
        if (!is_string($name)) {
            return;
        }

        $packageRoot = "$vendorDir/$name";
        $rejectGlobs[] = "$packageRoot/**/*";

        $autoload = $package->autoload ?? null;
        if (!is_object($autoload) || !($autoload instanceof \stdClass)) {
            return;
        }

        if (is_array($autoload->files ?? null)) {
            foreach ($autoload->files as $file) {
                if (is_string($file)) {
                    $file = trim($file, '/');
                    $acceptGlobs[] = "$packageRoot/$file";
                }
            }
        }

        if (is_array($autoload->classmap ?? null)) {
            foreach ($autoload->classmap as $path) {
                if (is_string($path)) {
                    $path = trim($path, '/');
                    $acceptGlobs[] = "$packageRoot/$path"; // if regular file
                    $acceptGlobs[] = "$packageRoot/$path/**/*"; // if directory
                }
            }
        }

        foreach (['psr-0', 'psr-4'] as $psr) {
            if (is_object($autoload->$psr ?? null)) {
                foreach (get_object_vars($autoload->$psr) as $ns => $dir) {
                    if (is_string($dir)) {
                        $dir = trim($dir, '/');
                        $dir = ($dir === '' ? '' : '/') . $dir;
                        $acceptGlobs[] = "$packageRoot$dir/**/*";
                    }
                }
            }
        }

        if (is_array($autoload->{'exclude-from-classmap'} ?? null)) {
            foreach ($autoload->{'exclude-from-classmap'} as $path) {
                if (is_string($path)) {
                    $path = trim($path, '/');
                    $forceRejectGlobs[] = "$packageRoot/$path"; // if regular file
                    $forceRejectGlobs[] = "$packageRoot/$path/**/*"; // if directory
                }
            }
        }
    }
}
