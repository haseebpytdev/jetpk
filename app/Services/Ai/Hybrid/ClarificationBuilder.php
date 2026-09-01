<?php

namespace App\Services\Ai\Hybrid;

final class ClarificationBuilder
{
    /**
     * @param  list<array{label: string, value: string}>  $options
     */
    public function location(string $city, array $options): array
    {
        return [
            'message' => 'Which '.$city.' airport would you like?',
            'options' => $options,
        ];
    }

    public function monthForDay(int $day): array
    {
        return [
            'message' => 'What month do you mean by the '.$day.'th?',
            'options' => [],
        ];
    }

    public function passengers(): array
    {
        return [
            'message' => 'How many adults, children, and infants are travelling?',
            'options' => [],
        ];
    }

    public function route(): array
    {
        return [
            'message' => 'Please share origin and destination cities (for example Lahore to Dubai).',
            'options' => [],
        ];
    }
}
