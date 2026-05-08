<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class Label extends Entity
{
    protected array $_accessible = [
        'name' => true,
        'slug' => true,
        'color' => true,
        'description' => true,
        'keywords' => true,
    ];

    public function getKeywordsArray(): array
    {
        if (empty($this->keywords)) {
            return [];
        }
        $decoded = json_decode($this->keywords, true);
        return is_array($decoded) ? $decoded : [];
    }
}
