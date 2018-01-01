<?php declare(strict_types=1);

namespace Tests\Tsufeki\Tenkawa\Document;

use PHPUnit\Framework\TestCase;
use Recoil\React\ReactKernel;
use Tsufeki\Tenkawa\Document\Document;
use Tsufeki\Tenkawa\Document\DocumentStore;
use Tsufeki\Tenkawa\Document\Project;
use Tsufeki\Tenkawa\Event\Document\OnChange;
use Tsufeki\Tenkawa\Event\Document\OnClose;
use Tsufeki\Tenkawa\Event\Document\OnOpen;
use Tsufeki\Tenkawa\Event\Document\OnProjectClose;
use Tsufeki\Tenkawa\Event\Document\OnProjectOpen;
use Tsufeki\Tenkawa\Event\EventDispatcher;
use Tsufeki\Tenkawa\Exception\DocumentNotOpenException;
use Tsufeki\Tenkawa\Exception\ProjectNotOpenException;
use Tsufeki\Tenkawa\Uri;

/**
 * @covers \Tsufeki\Tenkawa\Document\DocumentStore
 * @covers \Tsufeki\Tenkawa\Document\Document
 * @covers \Tsufeki\Tenkawa\Document\Project
 */
class DocumentStoreTest extends TestCase
{
    public function test_document()
    {
        ReactKernel::start(function () {
            $dispatcher = $this->createMock(EventDispatcher::class);
            $dispatcher
                ->expects($this->exactly(4))
                ->method('dispatch')
                ->withConsecutive(
                    [$this->identicalTo(OnProjectOpen::class), $this->isInstanceOf(Project::class)],
                    [$this->identicalTo(OnOpen::class), $this->isInstanceOf(Document::class)],
                    [$this->identicalTo(OnChange::class), $this->isInstanceOf(Document::class)],
                    [$this->identicalTo(OnClose::class), $this->isInstanceOf(Document::class)]
                );

            $uri = Uri::fromString('file:///foo');
            $store = new DocumentStore($dispatcher);
            $project = yield $store->openProject(Uri::fromString('file:///'));

            $document = yield $store->open($uri, 'php', '<?php', 42);

            $this->assertSame($uri, $document->getUri());
            $this->assertSame('php', $document->getLanguage());
            $this->assertSame('<?php', $document->getText());
            $this->assertSame(42, $document->getVersion());
            $this->assertSame($project, $document->getProject());

            $this->assertSame($document, $store->get($uri));

            yield $store->update($document, '<?php 43;', 43);

            $this->assertSame('<?php 43;', $document->getText());
            $this->assertSame(43, $document->getVersion());

            yield $store->close($document);

            $this->assertTrue($document->isClosed());
        });
    }

    public function test_project()
    {
        ReactKernel::start(function () {
            $dispatcher = $this->createMock(EventDispatcher::class);
            $dispatcher
                ->expects($this->exactly(2))
                ->method('dispatch')
                ->withConsecutive(
                    [$this->identicalTo(OnProjectOpen::class), $this->isInstanceOf(Project::class)],
                    [$this->identicalTo(OnProjectClose::class), $this->isInstanceOf(Project::class)]
                );

            $uri = Uri::fromString('file:///foo');
            $store = new DocumentStore($dispatcher);
            $project = yield $store->openProject($uri);

            $this->assertSame($uri, $project->getRootUri());
            $this->assertSame($project, $store->getProject($uri));

            yield $store->closeProject($project);

            $this->assertTrue($project->isClosed());
        });
    }

    public function test_close_all()
    {
        ReactKernel::start(function () {
            $dispatcher = $this->createMock(EventDispatcher::class);
            $dispatcher
                ->expects($this->exactly(4))
                ->method('dispatch')
                ->withConsecutive(
                    [$this->identicalTo(OnProjectOpen::class), $this->isInstanceOf(Project::class)],
                    [$this->identicalTo(OnOpen::class), $this->isInstanceOf(Document::class)],
                    [$this->identicalTo(OnClose::class), $this->isInstanceOf(Document::class)],
                    [$this->identicalTo(OnProjectClose::class), $this->isInstanceOf(Project::class)]
                );

            $store = new DocumentStore($dispatcher);
            $project = yield $store->openProject(Uri::fromString('file:///'));
            $document = yield $store->open(Uri::fromString('file:///foo'), 'php', '<?php', 42);

            yield $store->closeAll();
        });
    }

    public function test_document_load()
    {
        ReactKernel::start(function () {
            $dispatcher = $this->createMock(EventDispatcher::class);
            $dispatcher
                ->expects($this->once())
                ->method('dispatch')
                ->with($this->identicalTo(OnProjectOpen::class), $this->isInstanceOf(Project::class));

            $uri = Uri::fromString('file:///foo');
            $store = new DocumentStore($dispatcher);
            $project = yield $store->openProject(Uri::fromString('file:///'));

            $document = yield $store->load($uri, 'php', '<?php');

            $this->assertSame($uri, $document->getUri());
            $this->assertSame('php', $document->getLanguage());
            $this->assertSame('<?php', $document->getText());
            $this->assertNull($document->getVersion());
            $this->assertSame($project, $document->getProject());
        });
    }

    public function test_document_open_without_project()
    {
        $this->expectException(ProjectNotOpenException::class);

        ReactKernel::start(function () {
            $dispatcher = $this->createMock(EventDispatcher::class);

            $uri = Uri::fromString('file:///foo');
            $store = new DocumentStore($dispatcher);

            $document = yield $store->open($uri, 'php', '<?php', 42);
        });
    }

    public function test_project_not_open()
    {
        $dispatcher = $this->createMock(EventDispatcher::class);

        $uri = Uri::fromString('file:///foo');
        $store = new DocumentStore($dispatcher);

        $this->expectException(ProjectNotOpenException::class);
        $store->getProject($uri);
    }

    public function test_document_not_open()
    {
        $dispatcher = $this->createMock(EventDispatcher::class);

        $uri = Uri::fromString('file:///foo');
        $store = new DocumentStore($dispatcher);

        $this->expectException(DocumentNotOpenException::class);
        $store->get($uri);
    }
}
