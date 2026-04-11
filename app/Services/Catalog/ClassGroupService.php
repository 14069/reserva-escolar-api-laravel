<?php

declare(strict_types=1);

namespace App\Services\Catalog;

final class ClassGroupService extends SimpleNamedCatalogService
{
    protected function table(): string { return 'class_groups'; }
    protected function idField(): string { return 'class_group_id'; }
    protected function entityLabel(): string { return 'Turma'; }
    protected function nameFieldMax(): int { return 20; }
}
