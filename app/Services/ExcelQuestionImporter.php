<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Parses a deterministic, admin-authored Excel/CSV file of questions —
 * unlike AiQuestionExtractor, this never calls an external API, so it works
 * immediately with no account/billing setup and gives exact, predictable
 * results for well-formed spreadsheets.
 */
class ExcelQuestionImporter
{
    /**
     * Header names (case/space/underscore-insensitive) mapped to the
     * question field they fill. Required columns must be present in every
     * row; the rest are optional.
     */
    private const COLUMN_MAP = [
        'question_ar' => 'question_text_ar',
        'question' => 'question_text_ar',
        'question_text_ar' => 'question_text_ar',
        'question_en' => 'question_text_en',
        'question_text_en' => 'question_text_en',
        'option_a_ar' => 'option_a_ar',
        'optionaar' => 'option_a_ar',
        'a_ar' => 'option_a_ar',
        'option_b_ar' => 'option_b_ar',
        'b_ar' => 'option_b_ar',
        'option_c_ar' => 'option_c_ar',
        'c_ar' => 'option_c_ar',
        'option_d_ar' => 'option_d_ar',
        'd_ar' => 'option_d_ar',
        'option_a_en' => 'option_a_en',
        'a_en' => 'option_a_en',
        'option_b_en' => 'option_b_en',
        'b_en' => 'option_b_en',
        'option_c_en' => 'option_c_en',
        'c_en' => 'option_c_en',
        'option_d_en' => 'option_d_en',
        'd_en' => 'option_d_en',
        'correct_answer' => 'correct_answer',
        'answer' => 'correct_answer',
        'correct' => 'correct_answer',
        'explanation_ar' => 'explanation_ar',
        'explanation' => 'explanation_ar',
        'difficulty' => 'difficulty_level',
        'difficulty_level' => 'difficulty_level',
    ];

    private const REQUIRED_FIELDS = [
        'question_text_ar', 'option_a_ar', 'option_b_ar', 'option_c_ar', 'option_d_ar',
        'correct_answer', 'explanation_ar',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function import(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        if (empty($rows)) {
            return [];
        }

        $fieldByColumnIndex = $this->mapHeaderRow(array_shift($rows));

        $questions = [];

        foreach ($rows as $row) {
            if ($this->isBlankRow($row)) {
                continue;
            }

            $question = $this->mapRow($row, $fieldByColumnIndex);

            if (blank($question['question_text_ar'] ?? null)) {
                continue;
            }

            $questions[] = $question;
        }

        return $questions;
    }

    /**
     * @param array<int, mixed> $headerRow
     * @return array<int, string>
     */
    private function mapHeaderRow(array $headerRow): array
    {
        $fieldByColumnIndex = [];

        foreach ($headerRow as $index => $header) {
            $normalized = strtolower(str_replace([' ', '-'], '_', trim((string) $header)));

            if (isset(self::COLUMN_MAP[$normalized])) {
                $fieldByColumnIndex[$index] = self::COLUMN_MAP[$normalized];
            }
        }

        $missing = array_diff(self::REQUIRED_FIELDS, $fieldByColumnIndex);

        if (! empty($missing)) {
            throw new RuntimeException(
                'The file is missing required column(s): ' . implode(', ', $missing)
                . '. Download the example file for the expected format.'
            );
        }

        return $fieldByColumnIndex;
    }

    /**
     * @param array<int, mixed> $row
     * @param array<int, string> $fieldByColumnIndex
     * @return array<string, mixed>
     */
    private function mapRow(array $row, array $fieldByColumnIndex): array
    {
        $question = [
            'correct_answer' => 'a',
            'difficulty_level' => 'medium',
        ];

        foreach ($fieldByColumnIndex as $index => $field) {
            $value = trim((string) ($row[$index] ?? ''));

            if ($field === 'correct_answer' && $value !== '') {
                $value = strtolower(substr($value, 0, 1));
            }

            if ($field === 'difficulty_level' && $value !== '') {
                $value = in_array(strtolower($value), ['easy', 'medium', 'hard'], true) ? strtolower($value) : 'medium';
            }

            $question[$field] = $value !== '' ? $value : ($question[$field] ?? null);
        }

        return $question;
    }

    /**
     * @param array<int, mixed> $row
     */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
