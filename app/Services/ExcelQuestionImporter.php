<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\BaseDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
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

    /**
     * Header names for columns where the admin embeds an actual picture in
     * the cell (Excel "Insert Picture") rather than typing text — only
     * meaningful for real .xlsx files, since CSV can't carry embedded media.
     */
    private const IMAGE_COLUMN_MAP = [
        'question_image' => 'image_url',
        'image' => 'image_url',
        'option_a_image' => 'option_a_image',
        'a_image' => 'option_a_image',
        'option_b_image' => 'option_b_image',
        'b_image' => 'option_b_image',
        'option_c_image' => 'option_c_image',
        'c_image' => 'option_c_image',
        'option_d_image' => 'option_d_image',
        'd_image' => 'option_d_image',
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
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        if (empty($rows)) {
            return [];
        }

        // Keep original 0-indexed array keys (don't array_shift) so each
        // row's array key still equals (its real Excel row number - 1) —
        // needed to correlate embedded images, which are addressed by
        // sheet coordinate, back to the right data row.
        $fieldByColumnIndex = $this->mapHeaderRow($rows[0]);
        $imageFieldByColumnLetter = $this->mapImageHeaderRow($rows[0]);
        $imagesByRow = $imageFieldByColumnLetter
            ? $this->extractImagesByRow($sheet, $imageFieldByColumnLetter)
            : [];

        $questions = [];

        foreach ($rows as $rowIndex => $row) {
            if ($rowIndex === 0) {
                continue;
            }

            if ($this->isBlankRow($row) && empty($imagesByRow[$rowIndex + 1])) {
                continue;
            }

            $question = $this->mapRow($row, $fieldByColumnIndex);
            $question = array_merge($question, $imagesByRow[$rowIndex + 1] ?? []);

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
            $normalized = $this->normalizeHeader($header);

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
     * @param array<int, mixed> $headerRow
     * @return array<string, string> column letter (e.g. "F") => question field
     */
    private function mapImageHeaderRow(array $headerRow): array
    {
        $fieldByColumnLetter = [];

        foreach ($headerRow as $index => $header) {
            $normalized = $this->normalizeHeader($header);

            if (isset(self::IMAGE_COLUMN_MAP[$normalized])) {
                // toArray()'s column index is 0-based; sheet coordinates are 1-based.
                $fieldByColumnLetter[Coordinate::stringFromColumnIndex($index + 1)] = self::IMAGE_COLUMN_MAP[$normalized];
            }
        }

        return $fieldByColumnLetter;
    }

    private function normalizeHeader(mixed $header): string
    {
        return strtolower(str_replace([' ', '-'], '_', trim((string) $header)));
    }

    /**
     * Reads every picture embedded in the sheet, keeps only the ones
     * anchored in a recognized image column, saves each to the "public"
     * disk (same as the manual dashboard form and AI import use), and
     * groups the resulting paths by the Excel row number they belong to.
     *
     * @param array<string, string> $imageFieldByColumnLetter
     * @return array<int, array<string, string>> row number => [field => stored path]
     */
    private function extractImagesByRow(Worksheet $sheet, array $imageFieldByColumnLetter): array
    {
        $imagesByRow = [];

        foreach ($sheet->getDrawingCollection() as $drawing) {
            [$columnLetter, $rowNumber] = Coordinate::coordinateFromString($drawing->getCoordinates());

            $field = $imageFieldByColumnLetter[$columnLetter] ?? null;

            if (! $field) {
                continue;
            }

            $path = $this->storeDrawing($drawing, $field === 'image_url' ? 'questions' : 'questions/options');

            if ($path) {
                $imagesByRow[$rowNumber][$field] = $path;
            }
        }

        return $imagesByRow;
    }

    private function storeDrawing(BaseDrawing $drawing, string $directory): ?string
    {
        if ($drawing instanceof MemoryDrawing) {
            // Generated in-memory rather than a real embedded picture file —
            // not the normal "insert picture into a cell" case; skip it
            // rather than guess at re-encoding it without ext-gd available.
            return null;
        }

        if (! $drawing instanceof Drawing || $drawing->getIsURL()) {
            return null;
        }

        // Embedded pictures are read back via a "zip://…" stream wrapper
        // (the xlsx's own media file inside the zip archive), not a real
        // filesystem path — is_file()/file_exists() are unreliable against
        // stream wrappers on some platforms, so read directly instead and
        // check the read itself for failure.
        $contents = @file_get_contents($drawing->getPath());

        if ($contents === false) {
            return null;
        }

        $filename = Str::uuid() . '.' . ($drawing->getExtension() ?: 'png');
        $path = "{$directory}/{$filename}";

        Storage::disk('public')->put($path, $contents);

        return $path;
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
