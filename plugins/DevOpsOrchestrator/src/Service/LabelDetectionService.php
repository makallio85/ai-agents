<?php
declare(strict_types=1);

namespace DevOpsOrchestrator\Service;

use App\Model\Entity\Label;
use Cake\ORM\TableRegistry;
use DevOpsOrchestrator\Dto\ParsedIssueDto;

class LabelDetectionService
{
    /**
     * Detect applicable labels for a parsed issue.
     *
     * @return list<string> Label slugs
     */
    public function detect(ParsedIssueDto $dto): array
    {
        /** @var list<Label> $labels */
        $labels = TableRegistry::getTableLocator()->get('Labels')->find()->all()->toList();

        $contentToSearch = strtolower($dto->title . ' ' . $dto->body . ' ' . $dto->issueType);
        $matched = [];

        foreach ($labels as $label) {
            $keywords = $label->getKeywordsArray();
            foreach ($keywords as $keyword) {
                if (str_contains($contentToSearch, strtolower((string)$keyword))) {
                    $matched[] = $label->slug;
                    break;
                }
            }
        }

        return array_values(array_unique($matched));
    }
}
