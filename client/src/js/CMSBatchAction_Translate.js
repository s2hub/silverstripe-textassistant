import jQuery from 'jquery';
import IntroJs from 'intro.js';
import A11yDialog from 'a11y-dialog';

(function($) {
	$.entwine('ss', function($) {

        $('#Form_BatchActionsForm').entwine({
            onmatch: function() {
                if (window.location.hash === "#trt") {

                    // Remove hash
                    history.pushState("", document.title, window.location.pathname + window.location.search);

                    let tutorial = IntroJs();

                    let transl = JSON.parse($("#tutorial-translate-strings").attr('data-translations'));

                    tutorial.setOptions({
                        disableInteraction: true,
                        exitOnOverlayClick: false,
                        showStepNumbers: false,
                        nextLabel: transl.NEXT,
                        doneLabel: transl.DONE,
                        showBullets: false,
                        steps: [
                            {
                                element: ".cms-content-batchactions-button",
                                intro: transl.STEP_3
                            },
                            {
                                element: "#Form_BatchActionsForm_Action_Holder",
                                intro: transl.STEP_4
                            },
                            {
                                element: ".cms-tree",
                                intro: transl.STEP_5
                            },
                            {
                                element: "#Form_BatchActionsForm_action_submit",
                                intro: transl.STEP_6
                            },
                            {
                                element: "#Form_batchactions_translateform",
                                intro: transl.STEP_7
                            },
                            {
                                element: "#translate-option-wrapper",
                                intro: transl.STEP_8
                            },
                            {
                                element: ".page-container",
                                intro: transl.STEP_9
                            },
                            {
                                element: "#Form_batchactions_translateform_action_StartTranslation",
                                intro: transl.STEP_10
                            },
                            {
                                intro: transl.STEP_11
                            }
                            
                        ]
                    });
    
                    tutorial.onbeforechange(function() {

    
                        if (this._currentStep === 1) {
                            $('.cms-content-batchactions-button').click();
                            // Find the option that ends on batchactions/translate
                            $('#Form_BatchActionsForm_Action option').each(function() {
                                if ($(this).val().includes("batchactions/translate")) {
                                    $(this).prop('selected', true);
                                }
                            });

                        } else if (this._currentStep === 2) {
                            $('.cms-tree .jstree-checkbox').first().click()
                        } else if (this._currentStep === 4) {
                            $('#Form_BatchActionsForm_action_submit').click();
                            return new Promise((resolve) => {

                                $('.introjs-nextbutton').addClass('introjs-disabled');

                                let selector = "#Form_batchactions_translateform";

                                if (document.querySelector(selector)) {
                                    return resolve(document.querySelector(selector));
                                }

                                const observer = new MutationObserver(mutations => {
                                    if (document.querySelector(selector)) {
                                        observer.disconnect();
                                        tutorial.refresh(true);
                                        setTimeout(function() {
                                            window.dispatchEvent(new Event('resize'));
                                        }, 0)
                                        console.log(tutorial)
                                        resolve(document.querySelector(selector));
                                    }
                                });

                                observer.observe(document.body, {
                                    childList: true,
                                    subtree: true
                                });
                        
                            });
                        }
                    });

                    tutorial.oncomplete(function() {

                        // Close dialog
                        $('#Form_batchactions_translateform_action_close').click();

                        // Close batch actions
                        $('.cms-content-batchactions-button').click();


                    });

                    tutorial.start();

                }

            },
            onsubmit: function(e) {
                let actionURL = this.find(':input[name=Action]').val();

                if (actionURL.includes("batchactions/translate")) {
                    let params = "";
                    let ids = this.find(':input[name=csvIDs]').val();

                    if (ids.length) {
                        params += "?ids=" + ids;
                    } else {
                        return;
                    }

                    let dialog = document.getElementById('text-assistant-dialog-batch-translate');
                    if (!dialog) {
                        dialog = document.createElement('div');
                        dialog.id = 'text-assistant-dialog-batch-translate';
                        dialog.classList.add('text-assistant-dialog');
                        dialog.setAttribute('aria-label', 'Translation dialog');
                        dialog.setAttribute('aria-hidden', 'true');
                        document.body.appendChild(dialog);
                        dialog.innerHTML = '<div class="dialog-overlay" data-a11y-dialog-hide></div><div class="dialog-content" role="document"><button class="dialog-close close modal__close-button" type="button" data-a11y-dialog-hide aria-label="Close dialog"></button><div class="dialog-form"></div></div>';
                        // init a11y dialog
                        dialog.textAssistantDialog = new A11yDialog(dialog);
                        dialog.textAssistantDialog.on('hide', function() {
                            dialog.querySelector('.dialog-form').innerHTML = '';
                        });
                    }

                    $('.cms-content').first().addClass('loading');
                    fetch(window.location.href.split("admin")[0] + 'admin/batchactions_translateform' + params, {
                        credentials: 'include',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }).then(function(response) {
                        if (!response.ok) {
                            $('.cms-content').first().removeClass('loading');
                            throw new Error('HTTP error, status = ' + response.status);
                        }
                        return response.text();
                    }).then(function(data) {
                        dialog.querySelector('.dialog-form').innerHTML = data;
                        $('.cms-content').first().removeClass('loading');
                    });
                    dialog.textAssistantDialog.show();

                    e.preventDefault();
                    return false;
                } else {
                    this._super();
                }

            }
        })

    });
}(jQuery));