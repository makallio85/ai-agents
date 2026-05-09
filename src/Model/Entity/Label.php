<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $color
 * @property string|null $description
 * @property string|null $keywords
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 */
class Label extends Entity
{
    protected array $_accessible = [
        'name' => true,
        'slug' => true,
        'color' => true,
        'description' => true,
        'keywords' => true,
    ];

    /** @return list<string> */
    public function getKeywordsArray(): array
    {
        if (empty($this->keywords)) {
            return [];
        }
        $decoded = json_decode($this->keywords, true);
        if (!is_array($decoded)) {
            return [];
        }
        return array_values(array_filter(array_map('strval', $decoded)));
    }
}
