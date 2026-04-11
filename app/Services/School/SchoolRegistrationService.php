<?php

declare(strict_types=1);

namespace App\Services\School;

use App\Support\ApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

final class SchoolRegistrationService
{
    public function register(array $payload): array
    {
        if (DB::table('schools')->where('school_code', $payload['school_code'])->exists()) {
            throw new HttpResponseException(
                ApiResponse::error('Já existe uma escola com esse código.', 409, 'SCHOOL_CODE_CONFLICT')
            );
        }

        try {
            return DB::transaction(function () use ($payload): array {
                $schoolId = (int) DB::table('schools')->insertGetId([
                    'school_name' => $payload['school_name'],
                    'school_code' => $payload['school_code'],
                    'password' => Hash::make($payload['school_password']),
                ]);

                DB::table('users')->insert([
                    'school_id' => $schoolId,
                    'name' => $payload['technician_name'],
                    'email' => $payload['technician_email'],
                    'password' => Hash::make($payload['technician_password']),
                    'role' => 'technician',
                    'active' => 1,
                ]);

                $categories = DB::table('resource_categories')
                    ->get(['id', 'name'])
                    ->mapWithKeys(fn (object $row) => [$row->name => (int) $row->id]);

                $resourceRows = [];

                for ($i = 1; $i <= (int) ($payload['chromebooks_count'] ?? 0); $i++) {
                    if ($categories->has('chromebooks')) {
                        $resourceRows[] = [
                            'school_id' => $schoolId,
                            'category_id' => $categories['chromebooks'],
                            'name' => "Carrinho de Chromebooks $i",
                            'active' => 1,
                        ];
                    }
                }

                for ($i = 1; $i <= (int) ($payload['audiovisual_count'] ?? 0); $i++) {
                    if ($categories->has('audiovisual')) {
                        $resourceRows[] = [
                            'school_id' => $schoolId,
                            'category_id' => $categories['audiovisual'],
                            'name' => "Recurso Audiovisual $i",
                            'active' => 1,
                        ];
                    }
                }

                for ($i = 1; $i <= (int) ($payload['spaces_count'] ?? 0); $i++) {
                    if ($categories->has('espacos')) {
                        $resourceRows[] = [
                            'school_id' => $schoolId,
                            'category_id' => $categories['espacos'],
                            'name' => "Espaço $i",
                            'active' => 1,
                        ];
                    }
                }

                if ($resourceRows !== []) {
                    DB::table('resources')->insert($resourceRows);
                }

                $classGroupRows = array_map(static fn (string $group): array => [
                    'school_id' => $schoolId,
                    'name' => $group,
                    'active' => 1,
                ], $payload['class_groups'] ?? []);

                if ($classGroupRows !== []) {
                    DB::table('class_groups')->insert($classGroupRows);
                }

                $subjectRows = array_map(static fn (string $subject): array => [
                    'school_id' => $schoolId,
                    'name' => $subject,
                    'active' => 1,
                ], $payload['subjects'] ?? []);

                if ($subjectRows !== []) {
                    DB::table('subjects')->insert($subjectRows);
                }

                $lessonRows = [];
                for ($i = 1; $i <= (int) $payload['lesson_count']; $i++) {
                    $lessonRows[] = [
                        'school_id' => $schoolId,
                        'lesson_number' => $i,
                        'label' => $i.'ª aula',
                        'active' => 1,
                    ];
                }

                DB::table('lesson_slots')->insert($lessonRows);

                return [
                    'school_id' => $schoolId,
                    'school_name' => $payload['school_name'],
                    'school_code' => $payload['school_code'],
                ];
            });
        } catch (HttpResponseException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new HttpResponseException(
                ApiResponse::error('Erro ao cadastrar escola.', 500, 'INTERNAL_SERVER_ERROR')
            );
        }
    }
}
