<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\Tests\Unit\Indexer;

use Maispace\MaiEvents\Domain\Model\EventRecord;
use Maispace\MaiEvents\Indexer\EventIndexer;
use Maispace\MaiSearch\Domain\Service\SearchBackendInterface;
use Maispace\MaiSearch\Service\BackendRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class EventIndexerTest extends TestCase
{
    private EventIndexer $subject;
    private BackendRegistry&MockObject $backendRegistry;
    private SearchBackendInterface&MockObject $activeBackend;

    protected function setUp(): void
    {
        $this->subject = new EventIndexer();

        $this->activeBackend = $this->createMock(SearchBackendInterface::class);
        $this->backendRegistry = $this->createMock(BackendRegistry::class);
        $this->backendRegistry->method('getActive')->willReturn($this->activeBackend);
        $this->subject->injectBackendRegistry($this->backendRegistry);
    }

    #[Test]
    public function removeRecordDelegatesToActiveBackend(): void
    {
        $this->activeBackend
            ->expects(self::once())
            ->method('removeDocument')
            ->with('events', 42);

        $this->subject->removeRecord(42, 'tx_maievents_event');
    }

    #[Test]
    public function removeRecordIsNoOpForUnsupportedTable(): void
    {
        $this->activeBackend->expects(self::never())->method('removeDocument');

        $this->subject->removeRecord(42, 'tx_mainews_news');
    }

    #[Test]
    public function getTypeReturnsEvents(): void
    {
        self::assertSame('events', $this->subject->getType());
    }

    #[Test]
    public function supportsEventsTable(): void
    {
        self::assertTrue($this->subject->supports('tx_maievents_event'));
    }

    #[Test]
    public function doesNotSupportOtherTables(): void
    {
        self::assertFalse($this->subject->supports('tx_mainews_news'));
        self::assertFalse($this->subject->supports('tx_maifaq_faq'));
        self::assertFalse($this->subject->supports('pages'));
        self::assertFalse($this->subject->supports('tt_content'));
    }

    #[Test]
    public function getIconReturnsExpectedValue(): void
    {
        self::assertSame('content-events', $this->subject->getIcon('events'));
    }

    #[Test]
    public function buildContentIncludesTitleLocationAndDescription(): void
    {
        $event = new EventRecord();
        $event->setTitle('Summer Festival');
        $event->setLocation('City Park');
        $event->setDescription('<p>An amazing <strong>summer</strong> event.</p>');

        $content = $this->invokeBuildContent($event);

        self::assertStringContainsString('Summer Festival', $content);
        self::assertStringContainsString('City Park', $content);
        self::assertStringContainsString('An amazing', $content);
        self::assertStringContainsString('summer', $content);
        self::assertStringNotContainsString('<p>', $content);
        self::assertStringNotContainsString('<strong>', $content);
    }

    #[Test]
    public function buildContentReturnsEmptyStringForNonEventRecord(): void
    {
        $content = $this->invokeBuildContent(new \stdClass());

        self::assertSame('', $content);
    }

    #[Test]
    public function formatResultReturnsSearchResultWithCorrectType(): void
    {
        $solrDoc = [
            'title_s' => 'Summer Festival',
            'content_t' => 'An amazing summer event at City Park.',
            'url_s' => '/events',
            'score' => 2.5,
        ];

        $result = $this->subject->formatResult($solrDoc);

        self::assertSame('events', $result->type);
        self::assertSame('Summer Festival', $result->title);
        self::assertSame('/events', $result->url);
        self::assertSame('content-events', $result->icon);
        self::assertSame(2.5, $result->score);
    }

    #[Test]
    public function formatResultDefaultsToEmptyStringsWhenFieldsAreMissing(): void
    {
        $result = $this->subject->formatResult([]);

        self::assertSame('', $result->title);
        self::assertSame('', $result->url);
        self::assertSame(0.0, $result->score);
        self::assertNull($result->date);
    }

    private function invokeBuildContent(object $record): string
    {
        $reflection = new \ReflectionMethod($this->subject, 'buildContent');
        $reflection->setAccessible(true);

        /** @var string $result */
        return $reflection->invoke($this->subject, $record);
    }
}
