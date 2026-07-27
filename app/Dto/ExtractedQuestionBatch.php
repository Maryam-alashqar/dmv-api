<?php

namespace App\Dto;

use Anthropic\Lib\Attributes\Constrained;
use Anthropic\Lib\Concerns\StructuredOutputModelTrait;
use Anthropic\Lib\Contracts\StructuredOutputModel;

class ExtractedQuestionBatch implements StructuredOutputModel
{
    use StructuredOutputModelTrait;

    /** @var ExtractedQuestion[] */
    #[Constrained(description: 'Every DMV exam question found in the source document', itemClass: ExtractedQuestion::class)]
    public array $questions;
}
