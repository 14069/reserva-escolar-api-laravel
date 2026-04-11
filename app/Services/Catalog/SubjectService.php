<?php

declare(strict_types=1);

namespace App\Services\Catalog;

final class SubjectService extends SimpleNamedCatalogService
{
    protected function table(): string
    {
        return 'subjects';
    }

    protected function idField(): string
    {
        return 'subject_id';
    }

    protected function entityLabel(): string
    {
        return 'Disciplina';
    }

    protected function nameFieldMax(): int
    {
        return 100;
    }
}
