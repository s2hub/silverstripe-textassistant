<span class="text-assistant">
    <button id="text-assistant-button-$ID" class="font-icon font-icon-translatable text-assistant-button" data-url="$Link('TextAssistantForm')" data-dialog="text-assistant-dialog-$ID">
        Text assistant
    </button>
    <% if wasTranslatedWithAI %>
        <span style="float:right">
            <%t S2Hub\TextAssistant\Extensions\FormFieldExtension.TRANSLATED_VIA_AI "This text was translated with AI." %>
        </span>
    <% end_if %>
    <input class="no-change-track" type="hidden" name="TextAssistantData[$FieldName]">

    <template>
        <div
            id="text-assistant-dialog-$ID"
            class="text-assistant-dialog"
            aria-label="Text Assistant"
            aria-hidden="true"
            data-button="text-assistant-button-$ID"
        >
            <div class="dialog-overlay" data-a11y-dialog-hide></div>
            <div class="dialog-content" role="document">
                <button class="dialog-close close modal__close-button" type="button" data-a11y-dialog-hide aria-label="Close dialog"></button>
                <div class="dialog-form">

                </div>
            </div>
        </div>
    </template>
</span>