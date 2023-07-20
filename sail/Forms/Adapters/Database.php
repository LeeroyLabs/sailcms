<?php

namespace SailCMS\Forms\Adapters;

use SailCMS\Contracts\FormAdapter;
use SailCMS\Errors\DatabaseException;
use SailCMS\Http\Request;
use SailCMS\Model\ReceivedForm;
use SailCMS\Models\Form;
use SailCMS\Types\FormProcessingResult;
use stdClass;

class Database implements FormAdapter
{
    /**
     *
     * Receive the form and save the data
     *
     * @param  Form      $form
     * @param  stdClass  $post
     * @return FormProcessingResult
     * @throws DatabaseException
     *
     */
    public function receive(Form $form, stdClass $post): FormProcessingResult
    {
        $received = new ReceivedForm();
        $request = new Request();

        $received->received_date = time();
        $received->received_from = $request->ipAddress();
        $received->form_data = $post;
        $received->form_name = $form->name;
        $received->save();

        return new FormProcessingResult(true, 'OK');
    }
}