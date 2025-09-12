<div class="translatabledataobjectfield translationGroup ss-tabset">
	<% if $HasTranslations %>
		<span class="locale-tabs cms-content-header-tabs">
			<ul class="font-icon-globe-1">
				<li><a href='#{$DefaultField.ID}_Holder' data-locale="$DefaultField.getAttribute('data-locale')" class="translation-link">$DefaultField.getAttribute('data-tab-title')</a></li>
				<% loop $FieldList %>
					<% if $getAttribute('data-locale') == $Up.DefaultLocale %><% else %>
						<li><a href='#{$ID}_Holder' data-locale="$getAttribute('data-locale')" class="translation-link">$getAttribute('data-tab-title')</a></li>
					<% end_if %>
				<% end_loop %>
			</ul>
		</span>
	<% end_if %>
	$DefaultField.FieldHolder
	<% if $HasTranslations %>
		<% loop $FieldList %>
			<% if $getAttribute('data-locale') != $Up.DefaultLocale %>
				$FieldHolder
			<% end_if %>
		<% end_loop %>
	<% end_if %>
</div>