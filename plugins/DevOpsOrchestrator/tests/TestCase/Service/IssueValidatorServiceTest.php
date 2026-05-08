<?php
declare(strict_types=1);

namespace DevOpsOrchestrator\Test\TestCase\Service;

use Cake\TestSuite\TestCase;
use DevOpsOrchestrator\Dto\ParsedIssueDto;
use DevOpsOrchestrator\Service\IssueValidatorService;

class IssueValidatorServiceTest extends TestCase
{
    private IssueValidatorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new IssueValidatorService();
    }

    private function validDto(array $overrides = []): ParsedIssueDto
    {
        return new ParsedIssueDto(
            rawBlock: 'raw block text',
            title: $overrides['title'] ?? 'Valid issue title',
            body: $overrides['body'] ?? 'This is a valid body with enough content to pass validation.',
            issueType: $overrides['issue_type'] ?? 'bug',
            isValid: $overrides['is_valid'] ?? true
        );
    }

    public function testValidatesSuccessfullyWithCompleteData(): void
    {
        $errors = $this->service->validate($this->validDto());
        $this->assertEmpty($errors);
        $this->assertTrue($this->service->isValid($this->validDto()));
    }

    public function testReturnsErrorForEmptyTitle(): void
    {
        $dto = $this->validDto(['title' => '']);
        $errors = $this->service->validate($dto);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Title', $errors[0]);
    }

    public function testReturnsErrorForTitleTooLong(): void
    {
        $dto = $this->validDto(['title' => str_repeat('a', 300)]);
        $errors = $this->service->validate($dto);
        $this->assertNotEmpty($errors);
    }

    public function testReturnsErrorForEmptyBody(): void
    {
        $dto = $this->validDto(['body' => '']);
        $errors = $this->service->validate($dto);
        $this->assertNotEmpty($errors);
    }

    public function testReturnsErrorForBodyTooShort(): void
    {
        $dto = $this->validDto(['body' => 'short']);
        $errors = $this->service->validate($dto);
        $this->assertNotEmpty($errors);
    }

    public function testPassthroughsInvalidDtoErrors(): void
    {
        $dto = ParsedIssueDto::invalid('raw', ['pre-existing error']);
        $errors = $this->service->validate($dto);
        $this->assertContains('pre-existing error', $errors);
    }

    public function testIsValidReturnsFalseForInvalidDto(): void
    {
        $dto = $this->validDto(['title' => '']);
        $this->assertFalse($this->service->isValid($dto));
    }
}
