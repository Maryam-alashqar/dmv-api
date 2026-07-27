<?php

namespace App\Dto;

use Anthropic\Core\Attributes\Required;
use Anthropic\Lib\Attributes\Constrained;
use Anthropic\Lib\Concerns\StructuredOutputModelTrait;
use Anthropic\Lib\Contracts\StructuredOutputModel;

class ExtractedQuestion implements StructuredOutputModel
{
    use StructuredOutputModelTrait;

    #[Constrained(description: 'The question text in Arabic, exactly as it should appear to the user')]
    public string $question_text_ar;

    #[Constrained(description: 'English version of the question text, only if the source document actually contains it')]
    public ?string $question_text_en = null;

    #[Constrained(description: 'Answer option A in Arabic')]
    public string $option_a_ar;

    #[Constrained(description: 'Answer option B in Arabic')]
    public string $option_b_ar;

    #[Constrained(description: 'Answer option C in Arabic')]
    public string $option_c_ar;

    #[Constrained(description: 'Answer option D in Arabic')]
    public string $option_d_ar;

    public ?string $option_a_en = null;

    public ?string $option_b_en = null;

    public ?string $option_c_en = null;

    public ?string $option_d_en = null;

    #[Required(enum: ['a', 'b', 'c', 'd'])]
    #[Constrained(description: 'The correct option')]
    public string $correct_answer;

    #[Constrained(description: 'Arabic explanation of why the correct answer is correct')]
    public string $explanation_ar;

    #[Constrained(description: 'Confidence 0-100 that this question was extracted accurately from the source; lower it for unclear, poorly scanned, or ambiguous source content')]
    public int $confidence;
}
