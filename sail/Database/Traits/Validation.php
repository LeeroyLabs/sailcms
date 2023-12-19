<?php

namespace SailCMS\Database\Traits;

use SailCMS\Contracts\Validator;
use SailCMS\Errors\DatabaseException;

trait Validation
{
    // Validate set fields to set rule
    protected array $validators = [];

    /**
     *
     * Run Model Validators
     *
     * @param $doc
     * @return void
     * @throws DatabaseException
     *
     */
    private function runValidators($doc): void
    {
        foreach ($this->validators as $key => $validator) {
            if (!str_contains($validator, '::')) {
                $subValidators = explode(',', $validator);

                foreach ($subValidators as $subValidator) {
                    switch ($validator) {
                        case 'not-empty':
                            if (empty($doc->{$key})) {
                                throw new DatabaseException("Property {$key} does pass validation, it should not be empty.", 0400);
                            }
                            break;

                        case 'string':
                            if (!is_string($doc->{$key})) {
                                $type = gettype($doc->{$key});
                                throw new DatabaseException("Property {$key} does pass validation, it should be a string but is a {$type}.", 0400);
                            }
                            break;

                        case 'numeric':
                            if (!is_numeric($doc->{$key})) {
                                $type = gettype($doc->{$key});
                                throw new DatabaseException("Property {$key} does pass validation, it should be a number but is a {$type}.", 0400);
                            }
                            break;

                        case 'boolean':
                            if (!is_bool($doc->{$key})) {
                                $type = gettype($doc->{$key});
                                throw new DatabaseException("Property {$key} does pass validation, it should be a boolean but is a {$type}.", 0400);
                            }
                            break;
                    }
                }
            } else {
                // Custom validator
                $impl = class_implements($validator);

                if (isset($impl[Validator::class])) {
                    $validator->validate($key, $doc->{$key});
                } else {
                    throw new DatabaseException("Cannot use {$validator} to validate {$key} because it does not implement the Validator Interface", 400);
                }
            }
        }
    }
}