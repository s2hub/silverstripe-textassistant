import jQuery from 'jquery';

(function($) {
	
	$.entwine('ss', function($){
		$('.locale-restrictions').entwine({
			onadd: function() {
				this.update();
			},
			update: function() {
				let container = $(this).closest('fieldset');
				let checkedLocales = [];
				let uncheckedLocales = [];
				
				$(this).find('input').each(function() {
					if ($(this).is(':checked')) {
						checkedLocales.push($(this).val());
					}
					else {
						uncheckedLocales.push($(this).val());
					}
				});
				
				// Checked locales
				$.each(checkedLocales, function(i, locale) {
					container.find('.locale-tabs .translation-link[data-locale="'+locale+'"]').removeClass('disabled').show();
				});
				
				// Unchecked locales
				$.each(uncheckedLocales, function(i, locale) {
					container.find('.locale-tabs .translation-link[data-locale="'+locale+'"]').addClass('disabled').hide();
				});

				container.find('.locale-tabs .ui-state-active').each(function() {
					if ($.inArray($(this).find('.translation-link').attr('data-locale'), uncheckedLocales) >= 0) {
						// switch to first enabled locale
						$(this).closest('.ui-tabs').find('.translation-link:not(.disabled)').click();
					}
				});
				
				if (checkedLocales.length < 2) {
					container.find('.locale-tabs').hide();
				}
				else {
					container.find('.locale-tabs').show();
				}
			}
		});
		
		$('.locale-restrictions input').entwine({
			onchange: function() {
				var container = $(this).closest('fieldset');
				
				// don't allow unchecking all locales, at least one needs to be checked
				if (!$(this).is(':checked') && ($(this).hasClass('disabled') || container.find('.locale-restrictions input:checked').length < 1)) {
					$(this).attr('checked', true);
					return;
				}
				$(this).closest('.locale-restrictions').update();
			}
		});
		
		$('.translatabledataobjectgroup .LocaleGroupItemRow-remove').entwine({
			onclick: function() {
				var container = $(this).closest('.translatabledataobjectgroup');
				var itemID = parseInt($(this).parent().attr('data-item-id'),10) || 0;
				var inputField = container.find('input[name=LocaleGroupItems]');
				var newIDs = [];
				
				if (itemID && inputField.val().length) {
					var currentIDs = inputField.val().split(',');
					$.each(currentIDs, function(i,id) {
						if (itemID !== parseInt(id,10)) {
							newIDs.push(id);
						}
					});
				}
				
				inputField.val(newIDs.join(','));
				$(this).parent().remove();
			}
		});
		
		$('.translatabledataobjectgroup').entwine({
			reload: function(extraData) {
				var container = $(this);
				var field = $(this).find('input[name=LocaleGroupItems]');
				
				container.html('<div class="reloading-translationsgroup"></div>');
				
				$.ajax({
					url: field.attr('data-reload-link'),
					dataType: 'html',
					data: ((typeof extraData !== 'undefined') ? extraData : {}),
					success: function(data) {					
						console.log(data);
						container.html(data);
					},
					error: function(e) {
						console.log('error: '+e);
					}
				});
			}
		});
		
		$('.translatabledataobjectgroup input[name=LocaleGroupItemAdd]').entwine({
			onmatch: function() {
				this._super();
				
				var self = $(this);
				self.on('select2-selecting', function(e) {
					self.addNewItem((parseInt(e.val,10) || 0), e.choice);
					e.preventDefault();	
					
					self.select2('close');
				});
			},
			addNewItem: function(itemID, item) {
				var container = $(this).closest('.translatabledataobjectgroup');
				var inputField = container.find('input[name=LocaleGroupItems]');
				
				if (itemID) {
					var currentIDs = inputField.val().length ? inputField.val().split(',') : [];
					var exists = false;
					$.each(currentIDs, function(i,id) {
						if (itemID === parseInt(id,10)) {
							exists = true;
							return false;
						}
					});
					
					if (!exists) {
						currentIDs.push(itemID);
						
						var itemTitle = item.selectionContent;
						var rowsContainer = container.find('.LocaleGroupItemRows');
						rowsContainer.append('<div class="LocaleGroupItemRow" data-item-id="' + itemID + '">'+itemTitle+' <span class="LocaleGroupItemRow-remove ui-icon">&nbsp;</span>');
					}
					inputField.val(currentIDs.join(','));					
				}
			}
		});
	});
	
}(jQuery));