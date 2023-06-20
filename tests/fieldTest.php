<?php

use SailCMS\Collection;
use SailCMS\Models\Asset;
use SailCMS\Models\Entry\AssetFileField;
use SailCMS\Models\Entry\AssetImageField;
use SailCMS\Models\Entry\DateField;
use SailCMS\Models\Entry\DateTimeField;
use SailCMS\Models\Entry\EmailField;
use SailCMS\Models\Entry\EntryField;
use SailCMS\Models\Entry\EntryListField;
use SailCMS\Models\Entry\HTMLField;
use SailCMS\Models\Entry\MultipleSelectField;
use SailCMS\Models\Entry\NumberField;
use SailCMS\Models\Entry\SelectField;
use SailCMS\Models\Entry\TextareaField;
use SailCMS\Models\Entry\TextField;
use SailCMS\Models\Entry\TimeField;
use SailCMS\Models\Entry\UrlField;
use SailCMS\Models\EntryLayout;
use SailCMS\Models\EntryType;
use SailCMS\Sail;
use SailCMS\Types\Fields\Field as InputField;
use SailCMS\Types\Fields\InputDateField;
use SailCMS\Types\Fields\InputNumberField;
use SailCMS\Types\Fields\InputSelectField;
use SailCMS\Types\Fields\InputTextField;
use SailCMS\Types\Fields\InputTimeField;
use SailCMS\Types\Fields\InputUrlField;
use SailCMS\Types\LocaleField;

beforeAll(function () {
    Sail::setupForTests(__DIR__);

    $layoutModel = new EntryLayout();
    $entryLayout = $layoutModel->create(new LocaleField(['fr' => 'Test des champs', 'en' => 'Field Test']), Collection::init());

    $entryType = (new EntryType)->create('field-test', 'Field Test', new LocaleField(['en' => 'field-test', 'fr' => 'test-de-champs']), $entryLayout->_id);

    $entryType->getEntryModel($entryType)->create(false, 'fr', 'Home Field Test', 'page');
    $entryType->getEntryModel($entryType)->create(false, 'fr', 'Related Page Test', 'page');

    $asset = new Asset();
    $data = base64_decode(file_get_contents(__DIR__ . '/mock/asset/test.jpg.txt'));
    $asset->upload($data, 'field_test.jpg');
});

afterAll(function () {
    $entryModel = EntryType::getEntryModelByHandle('field-test');
    $entry = $entryModel->one([
        'title' => 'Home Field Test'
    ]);
    $entryModel->delete((string)$entry->_id, false);
    $entry = $entryModel->one([
        'title' => 'Related Page Test'
    ]);
    $entryModel->delete((string)$entry->_id, false);

    (new EntryType())->hardDelete($entryModel->entry_type_id);

    $layoutModel = new EntryLayout();
    $entryLayout = $layoutModel->one([
        'titles.fr' => 'Test des champs'
    ]);
    $layoutModel->delete((string)$entryLayout->_id, false);

    $item = Asset::getByName('field-test-webp');
    $item->remove();
});

test('Add all fields to the layout', function () {
    $layoutModel = new EntryLayout();
    $entryLayout = $layoutModel->bySlug('field-test');

    // Field with default settings
    $textField = new TextField(new LocaleField(['en' => 'Text', 'fr' => 'Texte']), [
        [
            'required' => true,
            'maxLength' => 10,
            'minLength' => 5
        ]
    ]);
    $phoneField = new TextField(new LocaleField(['en' => 'Phone', 'fr' => 'Téléphone']), [
        [
            'pattern' => "\d{3}-\d{3}-\d{4}"
        ]
    ]);
    $descriptionField = new TextareaField(new LocaleField(['en' => 'Description', 'fr' => 'Description']));
    $numberFieldInteger = new NumberField(new LocaleField(['en' => 'Integer', 'fr' => 'Entier']), [
        [
            'min' => -1,
            'max' => 11
        ]
    ]);
    $numberFieldFloat = new NumberField(new LocaleField(['en' => 'Float', 'fr' => 'Flottant']), [
        [
            'required' => true,
            'min' => 0.03
        ]
    ], false, 2);

    $entryField = new EntryField(new LocaleField(['en' => 'Related Entry', 'fr' => 'Entrée Reliée']));

    $entryListField = new EntryListField(new LocaleField(['en' => 'Entry List', 'fr' => 'Liste d\'entrées']));

    $htmlField = new HTMLField(new LocaleField(['en' => 'Wysiwyg content', 'fr' => 'Contenu Wysiwyg']));

    $emailField = new EmailField(new LocaleField(['en' => 'Email', 'fr' => 'Courriel']), [
        [
            'required' => true
        ]
    ]);

    $selectField = new SelectField(new LocaleField(['en' => 'Select', 'fr' => 'Selection']), [
        [
            'required' => false,
            'options' => new Collection([
                'test' => 'Big test',
                'test2' => 'The real big test'
            ])
        ]
    ]);

    $multipleSelectField = new MultipleSelectField(new LocaleField(['en' => 'Select', 'fr' => 'Selection']), [
        [
            'required' => false,
            'multiple' => true,
            'options' => new Collection([
                'test' => 'Big test',
                'test2' => 'The real big test'
            ])
        ]
    ]);

    $urlField = new UrlField(new LocaleField(['en' => 'Url', 'fr' => 'Url']));

    $assetField = new AssetImageField(new LocaleField(['en' => 'Image', 'fr' => 'Image']));

    $dateField = new DateField(new LocaleField(['en' => 'Date', 'fr' => 'Date']), [
        [
            'required' => true,
            'min' => "2018-01-01",
            'max' => "2025-12-31"
        ]
    ]);

    $timeField = new TimeField(new LocaleField(['en' => 'Hour', 'fr' => 'Heure']), [
        [
            'required' => true,
            'min' => "10:00"
        ]
    ]);

    $dateTimeField = new DateTimeField(new LocaleField(['en' => 'Date/Time', 'fr' => 'Date/Hour']), [
        'date' => [
            'required' => true,
            'min' => '2020-03-01'
        ]
    ]);

    $repeaterField = new TextField(new LocaleField(['en' => 'Date/Time', 'fr' => 'Date/Hour']), [
        [
            'required' => false,
            'pattern' => '\d{3}-\d{3}-\d{4}'
        ]
    ], true);

    $fields = new Collection([
        "text" => $textField,
        "phone" => $phoneField,
        "description" => $descriptionField,
        "integer" => $numberFieldInteger,
        "float" => $numberFieldFloat,
        "related" => $entryField,
        "entryList" => $entryListField,
        "wysiwyg" => $htmlField,
        "email" => $emailField,
        "select" => $selectField,
        "multipleSelect" => $multipleSelectField,
        "url" => $urlField,
        "image" => $assetField,
        "date" => $dateField,
        "time" => $timeField,
        "datetime" => $dateTimeField,
        "repeater" => $repeaterField
    ]);

    $schema = EntryLayout::generateLayoutSchema($fields);

    try {
        $updated = (new EntryLayout())->updateById($entryLayout->_id, $entryLayout->titles, $schema);
        expect($updated)->toBe(true);
    } catch (Exception $exception) {
        //print_r($exception->getMessage());
        expect(true)->toBe(false);
    }
});

test('Failed to update the entry content', function () {
    $entryModel = EntryType::getEntryModelByHandle('field-test');
    $entry = $entryModel->one([
        'title' => 'Home Field Test'
    ]);
    $relatedEntry = $entryModel->one([
        'title' => 'Related Page Test'
    ]);

    try {
        $errors = $entryModel->updateById($entry, [
            'content' => [
                'float' => '0',
                'phone' => '514-3344344',
                'related' => [
                    'id' => (string)$relatedEntry->_id
                ],
                'wysiwyg' => '<script>console.log("hacked")</script><iframe>stuff happens</iframe><p><strong>Test</strong></p>',
                'select' => 'test-failed',
                'multipleSelect' => ['test', 'test-failed'],
                'url' => 'babaganouj',
                'image' => 'bad1d12345678901234bad1d', // Bad id...
                'date' => '2027-02-02',
                'time' => '09:59',
                'datetime' => [
                    'date' => '',
                    'time' => ''
                ],
                'repeater' => ['514-514-5145', 'test']
            ]
        ], false);

        expect($errors->length)->toBeGreaterThan(0)
            ->and($errors->get('text')[0][0])->toBe(InputField::FIELD_REQUIRED)
            ->and($errors->get('float')[0][0])->toBe(sprintf(InputNumberField::FIELD_TOO_SMALL, '0.03'))
            ->and($errors->get('phone')[0][0])->toBe(sprintf(InputTextField::FIELD_PATTERN_NO_MATCH, "\d{3}-\d{3}-\d{4}"))
            ->and($errors->get('related')[0])->toBe(EntryField::ENTRY_ID_AND_HANDLE)
            ->and($errors->get('select')[0][0])->toBe(InputSelectField::OPTIONS_INVALID)
            ->and($errors->get('url')[0][0])->toBe(sprintf(InputUrlField::FIELD_PATTERN_NO_MATCH, InputUrlField::DEFAULT_REGEX))
            ->and($errors->get('image')[0][0])->toBe(AssetFileField::ASSET_DOES_NOT_EXISTS)
            ->and($errors->get('multipleSelect')[1][0])->toBe(InputSelectField::OPTIONS_INVALID)
            ->and($errors->get('image')[0][0])->toBe(AssetImageField::ASSET_DOES_NOT_EXISTS)
            ->and($errors->get('date')[0][0])->toBe(sprintf(InputDateField::FIELD_TOO_BIG, "2025-12-31"))
            ->and($errors->get('time')[0][0])->toBe(sprintf(InputTimeField::FIELD_TOO_SMALL, "10:00"))
            ->and($errors->get('datetime')[0])->toBe(DateTimeField::DATE_TIME_ARE_REQUIRED)
            ->and($errors->get('repeater')[1][0])->toBe(sprintf(InputTextField::FIELD_PATTERN_NO_MATCH, "\d{3}-\d{3}-\d{4}"));
    } catch (Exception $exception) {
        expect(true)->toBe(false);
    }
});

test('Update content with success', function () {
    $entryModel = EntryType::getEntryModelByHandle('field-test');
    $entry = $entryModel->one([
        'title' => 'Home Field Test'
    ]);
    $entryId = $entry->_id;
    $relatedEntry = $entryModel->one([
        'title' => 'Related Page Test'
    ]);
    $item = Asset::getByName('field-test-webp');

    try {
        $errors = $entryModel->updateById($entry, [
            'content' => [
                'float' => '0.03',
                'text' => 'Not empty',
                'description' => 'This text contains line returns
and must keep it through all the process',
                'phone' => '514-514-5145',
                'related' => [
                    'id' => (string)$relatedEntry->_id,
                    'typeHandle' => 'field-test'
                ],
                'entryList' => [
                    'typeHandle' => 'field-test'
                ],
                'wysiwyg' => '<p><strong>Test</strong></p>',
                'email' => 'email-test@email.com',
                'select' => 'test',
                'url' => 'https://github.com/LeeroyLabs/sailcms/blob/813a36f2655cc86dfa8f9ca0e22efe8543a5dc67/sail/Types/Fields/Field.php#L12',
                'image' => (string)$item->_id,
                'multipleSelect' => ['test', 'test2'],
                'date' => "2021-10-10",
                'time' => "10:00",
                'datetime' => [
                    'date' => '2020-03-02',
                    'time' => '10:30'
                ],
                'repeater' => ['514-514-5145', '514-514-1234', '514-123-1234']
            ]
        ], false);
        expect($errors->length)->toBe(0);

        $entryModel = EntryType::getEntryModelByHandle('field-test');

        $entryUpdated = $entryModel->one([
            '_id' => $entryId
        ]);
        $content = $entryUpdated->getContent();

        expect($content->get('float.content'))->toBe('0.03')
            ->and($content->get('text.content'))->toBe('Not empty')
            ->and($content->get('description.content'))->toContain(PHP_EOL)
            ->and((string)$content->get('related.content._id'))->toBe((string)$relatedEntry->_id)
            ->and($content->get('wysiwyg.content'))->toBe('<p><strong>Test</strong></p>')
            ->and($content->get('email.content'))->toBe('email-test@email.com')
            ->and($content->get('select.content'))->toBe('test')
            ->and($content->get('entryList.content.typeHandle'))->toBe('field-test')
            ->and($content->get('url.content'))->toBe('https://github.com/LeeroyLabs/sailcms/blob/813a36f2655cc86dfa8f9ca0e22efe8543a5dc67/sail/Types/Fields/Field.php#L12')
            ->and($content->get('image.content.name'))->toBe('field-test-webp')
            ->and($content->get('date.content'))->toBe('2021-10-10')
            ->and($content->get('time.content'))->toBe('10:00')
            ->and($content->get('datetime.content')->unwrap())->toBe([
                'date' => '2020-03-02',
                'time' => '10:30'
            ])->and($content->get('repeater.content')->length)->toBe(3);
    } catch (Exception $exception) {
        expect(true)->toBe(false);
    }
});