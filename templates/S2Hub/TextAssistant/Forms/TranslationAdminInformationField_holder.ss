<div class="translation-information" data-url="$Link('update')">

    <% with TranslationJobsData %>

        <div class="translation-data" <% if not Display %>style="display:none"<% end_if %> data-running="<% if IsRunning %>1<% else %>0<% end_if %>">

            <div class="translation-box">
                <div class="data">
                    <% if InitializationJobsExists %>
                        <div role="status" class="spinner-border text-info"><span class="sr-only">Loading...</span></div>
                    <% else %>
                        $TranslatedPercentage%
                    <% end_if %>
                </div>
                <div class="title">
                    <%t S2Hub\TextAssistant\Forms\TranslationAdminInformationField.READY_TRANSLATIONS "Finished translations" %>
                </div>
                <% if IsRunning %>
                    <div role="status" class="spinner-border text-info spinner-border-sm "><span class="sr-only">Loading...</span></div>
                <% end_if %>
            </div>

            <div class="translation-box">
                <div class="data">
                    $TranslationActions
                </div>
                <div class="title">
                    <%t S2Hub\TextAssistant\Forms\TranslationAdminInformationField.ITEMS_WAITING "Items waiting to be published" %>
                </div>
            </div>

        </div>

    <% end_with %>

</div>

