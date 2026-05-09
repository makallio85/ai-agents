<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class LabelsFixture extends TestFixture
{
    public array $records = [
        [
            'id' => 1,
            'name' => 'bug',
            'slug' => 'bug',
            'color' => '#d73a4a',
            'description' => 'Something is not working',
            'keywords' => '["error","crash","exception","bug","broken","fail","failure"]',
            'created' => '2026-01-01 00:00:00',
            'modified' => '2026-01-01 00:00:00',
        ],
        [
            'id' => 2,
            'name' => 'enhancement',
            'slug' => 'enhancement',
            'color' => '#a2eeef',
            'description' => 'New feature or request',
            'keywords' => '["feature","improvement","enhance","add","new","request"]',
            'created' => '2026-01-01 00:00:00',
            'modified' => '2026-01-01 00:00:00',
        ],
    ];
}
