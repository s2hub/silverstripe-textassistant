<div class="translation-information-container">
    <table class="translation-information-table">
        <tr>
            <td>
                <%t S2Hub\TextAssistant\Models\TranslationAction.TYPE 'Type' %>
            </td>
            <td>
                $Record.TypeNice
            </td>
        </tr>
        <tr>
            <td>
                <%t S2Hub\TextAssistant\Models\TranslationAction.CREATOR 'Created by' %>
            </td>
            <td>
                $Record.Creator.Name
            </td>
        </tr>
        <tr>
            <td>
                <%t S2Hub\TextAssistant\Models\TranslationAction.LOCALE 'Locale' %>
            </td>
            <td>
                $Record.FromToLocale
            </td>
        </tr>
    </table>

    $Record.TranslationActionObjectInformation
</div>