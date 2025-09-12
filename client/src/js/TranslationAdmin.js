import jQuery from 'jquery';
import IntroJs from 'intro.js';

(function($) {

	$.entwine('ss', function($) {

        $('.btn-translations-instructions-button').entwine({
            onclick: function() {


                let transl = JSON.parse($(this).attr('data-translations'));

                let tutorial = IntroJs();

                tutorial.setOptions({
                    disableInteraction: true,
                    exitOnOverlayClick: false,
                    showStepNumbers: false,
                    nextLabel: transl.NEXT,
                    showBullets: false,
                    steps: [
                        {
                            intro: transl.STEP_1,
                        },
                        {
                            element: "#Menu-SilverStripe-CMS-Controllers-CMSPagesController",
                            intro: transl.STEP_2,
                        },
                        {
                            element: "body",
                        }
                        
                    ]
                });

                tutorial.onchange(function() {

                    if (this._currentStep === 2) {
                        window.location.href = $("#Menu-SilverStripe-CMS-Controllers-CMSPagesController").find('a').attr('href') + "#trt"
                    }
                });

                tutorial.start();



            }
        })

        $('.translation-information').entwine({
            onmatch: function() {
                let self = $(this);
                let url = $(this).attr('data-url');
                let identifier = Date.now()

                // Used to make sure only one loop is running
                self.attr('data-identifier', identifier);


                loopUpdateTranslationInformation($(this).find('.translation-data').attr('data-running') == '1', identifier);

            }
        });

        function loopUpdateTranslationInformation(isRunning, identifier) {

            isRunning = isRunning || false;
            let timeoutSleep;


            if (isRunning) {
                timeoutSleep = 2000;
            } else {
                timeoutSleep = 6000;
            }

            setTimeout(function() {
                let self = $('.translation-information');
                let url = self.attr('data-url');
                let template;

                if (self.attr('data-identifier') != identifier) {
                    return;
                }

                if (typeof url === 'undefined' || url === '') {
                    return;
                }

                $.ajax({
                    url: url,
                    success: function(data) {

                        self.find('.translation-data').css('display', 'block');
                        template = $(data.Template).find('.translation-data');
                        self.html(template);

                        loopUpdateTranslationInformation(data.IsRunning, identifier);
                    }
                });

            }, timeoutSleep);
        }

    });
}(jQuery));