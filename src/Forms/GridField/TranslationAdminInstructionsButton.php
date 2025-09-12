<?php

namespace S2Hub\TextAssistant\Forms\GridField;

use SilverStripe\Forms\GridField\GridField_HTMLProvider;

class TranslationAdminInstructionsButton implements GridField_HTMLProvider
{

    private $fragment = 'buttons-before-right';

    /**
     * @param string $fragment the fragment to render the button in
     */
    public function __construct($fragment = 'buttons-before-right')
    {
        $this->setFragment($fragment);
    }

    public function setFragment($fragment)
    {
        $this->fragment = $fragment;
        return $this;
    }
    
    public function getHTMLFragments($gridField)
    {
        return [
            $this->fragment => sprintf(
                '<button data-translations=\'%s\' type="button" aria-label="%s" title="%s"' .
                ' class="btn btn-info font-icon-info-circled btn--icon-large btn-translations-instructions-button">%s</button>',
                $this->getFirstPageTranslations(),
                _t(self::class.'.INSTRUCTIONS', "Instructions"),
                _t(self::class.'.INSTRUCTIONS', "Instructions"),
                _t(self::class.'.INSTRUCTIONS', "Instructions")
            )
        ];
    }

    public function getFirstPageTranslations(): string
    {
        return json_encode([
            'STEP_1' => _t(self::class.'.STEP_1', '1'),
            'STEP_2' => _t(self::class.'.STEP_2', '2'),
            'NEXT' => _t(self::class.'.NEXT', 'Next'),
        ]);
    }

    public static function getSecondPageTranslations()
    {
        return json_encode([
            'STEP_3' => _t(self::class.'.STEP_3', '3'),
            'STEP_4' => _t(self::class.'.STEP_4', '4'),
            'STEP_5' => _t(self::class.'.STEP_5', '5'),
            'STEP_6' => _t(self::class.'.STEP_6', '6'),
            'STEP_7' => _t(self::class.'.STEP_7', '7'),
            'STEP_8' => _t(self::class.'.STEP_8', '8'),
            'STEP_9' => _t(self::class.'.STEP_9', '9'),
            'STEP_10' => _t(self::class.'.STEP_10', '10'),
            'STEP_11' => _t(self::class.'.STEP_11', '11'),
            'NEXT' => _t(self::class.'.NEXT', 'Next'),
            'DONE' => _t(self::class.'.DONE', 'Done'),
        ]);
    }
} 