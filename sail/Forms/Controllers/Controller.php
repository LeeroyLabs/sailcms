<?php

namespace SailCMS\Forms\Controllers;

use SailCMS\Contracts\AppController;
use SailCMS\Errors\FormException;
use SailCMS\Forms;
use SailCMS\Models\Form;
use SailCMS\Security;

class Controller extends AppController
{
    public function formIntake(string $name): void
    {
        $form = Form::getBy('name', $name);

        $this->response->setType('json');

        // Form cannot be found
        if (!$form) {
            $this->response->set('code', 404);
            $this->response->set('message', "Cannot find form named '{$name}'");
            $this->response->set('missing', []);
            return;
        }

        // Fetch all posted data
        $post = $this->request->post('*');
        $missing = [];

        // Remove CSRF from the post object
        $csrfName = setting('CSRF.fieldName', '_csrf_');
        unset($post->{$csrfName});

        // Validate CSRF
        if (!$_ENV['CSRF_VALID']) {
            $this->response->set('code', 403);
            $this->response->set('message', "Permission denied");
            $this->response->set('missing', []);
            return;
        }

        // Is everything required provided?
        foreach ($form->elements as $element) {
            if ($element->required && !isset($post->{$element->name})) {
                $missing[] = $element->name;
            }
        }

        // We are missing things, error out!
        if (count($missing) > 0) {
            $glued = implode("', '", $missing);
            $this->response->set('code', 400);
            $this->response->set('message', "Missing required field(s) '{$glued}'.");
            $this->response->set('missing', $missing);
            return;
        }

        // Initialize adapter
        $formHandler = new Forms($form);
        $result = $formHandler->process($post);

        $this->response->set('code', ($result->success) ? 200 : 500);
        $this->response->set('message', $result->message);
        $this->response->set('missing', []);
    }
}