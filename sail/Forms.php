<?php

namespace SailCMS;

use SailCMS\Contracts\FormAdapter;
use SailCMS\Forms\Controllers\Controller;
use SailCMS\Models\Form;
use SailCMS\Routing\Router;
use SailCMS\Types\FormProcessingResult;
use stdClass;

class Forms
{
    private FormAdapter $adapter;
    private Form $currentForm;

    public static function init(): void
    {
        $router = new Router();
        $router->post('/v1/form-handling/:any', 'en', Controller::class, 'formIntake');
    }

    public function __construct(Form $form)
    {
        $adapterNS = $form->adapter;
        $this->adapter = new $adapterNS();
        $this->currentForm = $form;
    }

    public function process(stdClass $post): FormProcessingResult
    {
        $result = $this->adapter->receive($this->currentForm, $post);

        if ($result->success) {
            // Perform any reception notification (if any targets and turned on)
            if ($this->currentForm->notify && $this->currentForm->notify_targets->length > 0) {
                // TODO: Send Mail Here
            }
        }

        return $result;
    }
}