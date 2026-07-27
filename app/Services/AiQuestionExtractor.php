<?php

namespace App\Services;

use Anthropic\Client;
use Anthropic\Messages\Base64ImageSource;
use Anthropic\Messages\Base64PDFSource;
use Anthropic\Messages\DocumentBlockParam;
use Anthropic\Messages\ImageBlockParam;
use App\Dto\ExtractedQuestionBatch;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\IOFactory;
use RuntimeException;

class AiQuestionExtractor
{
    private const WORD_MIME_TYPES = [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/msword',
    ];

    private Client $client;

    public function __construct()
    {
        $this->client = new Client(apiKey: config('services.anthropic.api_key'));
    }

    /**
     * Send the uploaded file to Claude and return the extracted questions as
     * plain associative arrays (FR-A13).
     *
     * @return array<int, array<string, mixed>>
     */
    public function extract(UploadedFile $file): array
    {
        $message = $this->client->messages->create(
            maxTokens: 8000,
            model: 'claude-opus-5',
            system: $this->systemPrompt(),
            messages: [[
                'role' => 'user',
                'content' => [
                    $this->buildContentBlock($file),
                    [
                        'type' => 'text',
                        'text' => 'Extract every DMV exam question from this document, following the schema exactly.',
                    ],
                ],
            ]],
            outputConfig: ['format' => ExtractedQuestionBatch::class],
        );

        $batch = $message->parsedOutput();

        if (! $batch instanceof ExtractedQuestionBatch) {
            return [];
        }

        return array_map(
            fn ($question) => json_decode($question->toJson(), true),
            $batch->questions,
        );
    }

    private function buildContentBlock(UploadedFile $file): DocumentBlockParam|ImageBlockParam|array
    {
        $mime = $file->getMimeType();

        return match (true) {
            $mime === 'application/pdf' => DocumentBlockParam::with(
                source: Base64PDFSource::with(
                    data: base64_encode(file_get_contents($file->getRealPath())),
                ),
            ),
            str_starts_with((string) $mime, 'image/') => ImageBlockParam::with(
                source: Base64ImageSource::with(
                    data: base64_encode(file_get_contents($file->getRealPath())),
                    mediaType: $mime,
                ),
            ),
            in_array($mime, self::WORD_MIME_TYPES, true) => [
                'type' => 'text',
                'text' => $this->extractDocxText($file),
            ],
            default => throw new RuntimeException("Unsupported file type for AI import: {$mime}"),
        };
    }

    private function extractDocxText(UploadedFile $file): string
    {
        $document = IOFactory::load($file->getRealPath());

        $text = '';

        foreach ($document->getSections() as $section) {
            $text .= $this->extractElementsText($section->getElements());
        }

        return trim($text);
    }

    /**
     * @param array<int, mixed> $elements
     */
    private function extractElementsText(array $elements): string
    {
        $text = '';

        foreach ($elements as $element) {
            if ($element instanceof Text) {
                $text .= $element->getText() . "\n";
            } elseif ($element instanceof Table) {
                foreach ($element->getRows() as $row) {
                    foreach ($row->getCells() as $cell) {
                        $text .= $this->extractElementsText($cell->getElements());
                    }
                }
            } elseif ($element instanceof AbstractContainer) {
                $text .= $this->extractElementsText($element->getElements());
            }
        }

        return $text;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        You are extracting U.S. DMV written-test questions from a source document for a bilingual Arabic/English driving-exam preparation app.

        Rules:
        - Extract every multiple-choice question you can identify with reasonable confidence.
        - question_text_ar and all four Arabic options are required for every question, even when the source document is in English (translate faithfully into Arabic).
        - Only populate the _en fields when the source document genuinely contains English text for that field; otherwise leave them empty.
        - correct_answer must be exactly one of: a, b, c, d.
        - explanation_ar must briefly explain, in Arabic, why the correct answer is correct.
        - confidence is your own 0-100 estimate of how reliably this specific question was extracted; lower it when the source is unclear, poorly scanned, or ambiguous.
        - Do not invent questions that are not present in the source document. If none can be found, return an empty list.
        PROMPT;
    }
}
