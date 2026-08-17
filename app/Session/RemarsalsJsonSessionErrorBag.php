<?php

namespace App\Session;

use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

/**
 * Re-hydrate ViewErrorBag after JSON session save().
 *
 * Laravel's Store::prepareErrorBagForSerialization() mutates in-memory session
 * attributes to plain arrays so they can be json_encoded. That leaves same-request
 * consumers (notably TestResponseAssert::injectResponseContext) calling ->all() on
 * an array after the response is finalized.
 */
trait RemarsalsJsonSessionErrorBag
{
    public function save(): void
    {
        $hadErrorBag = ($this->attributes['errors'] ?? null) instanceof ViewErrorBag;

        parent::save();

        if (! $hadErrorBag || $this->serialization !== 'json') {
            return;
        }

        $raw = $this->attributes['errors'] ?? null;
        if (! is_array($raw)) {
            return;
        }

        $errorBag = new ViewErrorBag;

        foreach ($raw as $key => $value) {
            if (! is_array($value) || ! isset($value['messages']) || ! is_array($value['messages'])) {
                continue;
            }

            $messageBag = new MessageBag($value['messages']);
            $format = is_string($value['format'] ?? null) ? $value['format'] : ':message';
            $errorBag->put($key, $messageBag->setFormat($format));
        }

        $this->attributes['errors'] = $errorBag;
    }
}
