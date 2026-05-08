<?php
declare(strict_types=1);

namespace DevOpsOrchestrator\Test\TestCase\Service;

use Cake\TestSuite\TestCase;
use DevOpsOrchestrator\Service\IssueParserService;

class IssueParserServiceTest extends TestCase
{
    private IssueParserService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new IssueParserService();
    }

    private function fixture(string $name): string
    {
        return file_get_contents(
            dirname(__DIR__, 2) . '/Fixture/IssueBlocks/' . $name
        );
    }

    // --- extractRawBlocks ---

    public function testExtractRawBlocksReturnsSingleBlock(): void
    {
        $text = $this->fixture('single_valid.txt');
        $blocks = $this->service->extractRawBlocks($text);

        $this->assertCount(1, $blocks);
        $this->assertStringContainsString('Fix login button', $blocks[0]);
    }

    public function testExtractRawBlocksReturnsMultipleBlocks(): void
    {
        $text = $this->fixture('multiple_blocks.txt');
        $blocks = $this->service->extractRawBlocks($text);

        $this->assertCount(2, $blocks);
    }

    public function testExtractRawBlocksReturnsEmptyWhenNoBlocks(): void
    {
        $text = $this->fixture('no_blocks.txt');
        $blocks = $this->service->extractRawBlocks($text);

        $this->assertEmpty($blocks);
    }

    public function testExtractRawBlocksIgnoresTextOutsideBlocks(): void
    {
        $text = $this->fixture('single_valid.txt');
        $blocks = $this->service->extractRawBlocks($text);

        $this->assertStringNotContainsString('Some conversation text before', $blocks[0]);
        $this->assertStringNotContainsString('Some text after', $blocks[0]);
    }

    // --- parseBlock ---

    public function testParseBlockExtractsTitleAndBody(): void
    {
        $text = $this->fixture('single_valid.txt');
        $blocks = $this->service->extractRawBlocks($text);
        $dto = $this->service->parseBlock($blocks[0]);

        $this->assertTrue($dto->isValid);
        $this->assertSame('Fix login button not responding on mobile', $dto->title);
        $this->assertNotEmpty($dto->body);
        $this->assertSame('bug', $dto->issueType);
    }

    public function testParseBlockReturnsInvalidDtoWhenTitleMissing(): void
    {
        $text = $this->fixture('missing_title.txt');
        $blocks = $this->service->extractRawBlocks($text);
        $dto = $this->service->parseBlock($blocks[0]);

        $this->assertFalse($dto->isValid);
        $this->assertNotEmpty($dto->validationErrors);
        $this->assertStringContainsString('title', strtolower($dto->validationErrors[0]));
    }

    public function testParseBlockUsesGeneralTypeWhenTypeNotSpecified(): void
    {
        $block = "Title: Some issue\nDescription: This is the body of the issue with enough content.";
        $dto = $this->service->parseBlock($block);

        $this->assertSame('general', $dto->issueType);
    }

    // --- parseAll ---

    public function testParseAllHandlesMultipleBlocks(): void
    {
        $text = $this->fixture('multiple_blocks.txt');
        $dtos = $this->service->parseAll($text);

        $this->assertCount(2, $dtos);
        $this->assertTrue($dtos[0]->isValid);
        $this->assertTrue($dtos[1]->isValid);
    }

    public function testParseAllReturnsEmptyArrayWhenNoBlocks(): void
    {
        $text = $this->fixture('no_blocks.txt');
        $dtos = $this->service->parseAll($text);

        $this->assertEmpty($dtos);
    }

    public function testParseAllStoresOriginalRawBlock(): void
    {
        $text = $this->fixture('single_valid.txt');
        $dtos = $this->service->parseAll($text);

        $this->assertNotEmpty($dtos[0]->rawBlock);
        $this->assertStringContainsString('Fix login button', $dtos[0]->rawBlock);
    }

    public function testIssueBlockWithWindowsLineEndings(): void
    {
        $text = "=== ISSUE START ===\r\nTitle: Windows line endings test\r\nDescription: Testing CRLF line endings in issue blocks.\r\n=== ISSUE END ===";
        $dtos = $this->service->parseAll($text);

        $this->assertCount(1, $dtos);
        $this->assertSame('Windows line endings test', $dtos[0]->title);
    }

    public function testIssueBlockWithCaseInsensitiveMarkers(): void
    {
        $text = "=== issue start ===\nTitle: Case insensitive\nDescription: Markers in lowercase should still work.\n=== issue end ===";
        $dtos = $this->service->parseAll($text);

        $this->assertCount(1, $dtos);
    }
}
