(function() {
	
	CKEDITOR.plugins.add('pwimage', {
		
		requires: 'dialog',
		
		init: function(editor) {
			
			var pluginName = 'pwimage';
			
			// These are the allowed and required elements & attributes for images. 
			// It should clean all other classes but hidpi and three align classes that are generated by ProcessWire
			var allowed = 'img[alt,id,!src,title,width]{class}(align_left,align_center,align_right,hidpi,align-left,align-center,align-right);a[!href]';
			var required = 'img[alt,src]';
			
			// add pwimage command (opens the ProcessWire image selection iFrame)
			editor.addCommand(pluginName, {
				allowedContent: allowed,
				requiredContent: required,
				exec: loadIframeImagePicker
			}); 

			editor.ui.addButton('PWImage', {
				label: editor.lang.common.image,
				command: pluginName, 
				hidpi: true,
				icon: (CKEDITOR.env.hidpi ? this.path + 'images/hidpi/pwimage.png' : this.path + 'images/pwimage.png')
			}); 

			// On double click we execute the command (= we open the pw image selection iFrame defined above)
			editor.on( 'doubleclick', function( evt ) {
				var element = evt.data.element;
				if ( element.is( 'img' ) && !element.data( 'cke-realelement' ) && !element.isReadOnly() ) {
					evt.cancel(); // prevent CKE's link dialog from showing up (if image is linked)
					editor.commands.pwimage.exec();
				}
			});
		
			// If the "menu" plugin is loaded, register the menu items.
			if ( editor.addMenuItems ) {
				editor.addMenuItems({
					image: {
						label: editor.lang.image.menu,
						command: 'pwimage',
						group: 'image'
					}
				});
			}
		
			// If the "contextmenu" plugin is loaded, register the listeners.
			if ( editor.contextMenu ) {
				editor.contextMenu.addListener( function( element, selection ) {
					if ( getSelectedImage( editor, element ) )
						return { image: CKEDITOR.TRISTATE_OFF };
				});
			}
		
		}
	}); 

	function getSelectedImage( editor, element ) {
		if ( !element ) {
			var sel = editor.getSelection();
			element = sel.getSelectedElement();
		}

		if ( element && element.is( 'img' ) && !element.data( 'cke-realelement' ) && !element.isReadOnly() )
			return element;
	}

	function loadIframeImagePicker(editor) {

		var page_id = $("#Inputfield_id").val(); 
		var edit_page_id = page_id; 
		var file = '';
		var imgClass = '';
		var imgWidth = 0;
		var imgHeight = 0;
		var imgDescription = '';
		var imgLink = '';
		var hidpi = false;
		var selection = editor.getSelection();
		var se = selection.getSelectedElement();
		var node = selection.getStartElement();
		var $node = $(node);
		var nodeParent = node.getParent();
		var src = $node.attr('src');
		var $linkWrapper = null; // if img is wrapped in link, this is it
	
		if(nodeParent.$.nodeName.toUpperCase() === 'A') {
			$linkWrapper = $(nodeParent.getOuterHtml()); 
			$linkWrapper.find('img').remove();
		}
		
		if(src) { 
			
			var parts = src.split('/'); 
			file = parts.pop();
			imgClass = $node.attr('class'); 
			hidpi = imgClass && imgClass.indexOf('hidpi') > -1;
			imgWidth = $node.attr('width');
			imgHeight = $node.attr('height'); 
			imgDescription = $node.attr('alt'); 
			imgLink = (nodeParent.$.nodeName.toUpperCase() === "A") ? nodeParent.$.href : '';

			parts = parts.reverse();
			page_id = 0; 

			// pull page_id out of img[src]
			for(var n = 0; n < parts.length; n++) {
				page_id = parseInt(parts[n]); 
				if(page_id > 0) break;
			}
		}

		var modalUri = config.urls.admin + 'page/image/';
		var queryString = '?id=' + page_id + '&edit_page_id=' + edit_page_id + '&modal=1';

		if(file.length) queryString += "&file=" + file; 
		if(imgWidth) queryString += "&width=" + imgWidth; 
		if(imgHeight) queryString += "&height=" + imgHeight; 
		if(imgClass && imgClass.length) queryString += "&class=" + encodeURIComponent(imgClass); 
		queryString += '&hidpi=' + (hidpi ? '1' : '0'); 
		if(imgDescription && imgDescription.length) {
			queryString += "&description=" + encodeURIComponent(imgDescription);
		}
		if(imgLink && imgLink.length) queryString += "&link=" + encodeURIComponent(imgLink);
		queryString += ("&winwidth=" + ($(window).width() - 30));

		// create iframe dialog box
		// var $iframe = $('<iframe id="pwimage_iframe" width="100%" frameborder="0" src="' + modalUri + queryString + '"></iframe>'); 
		var modalSettings = {
			title: "<i class='fa fa-fw fa-folder-open'></i> " + config.InputfieldCKEditor.pwimage.selectLabel, // "Select Image", 
		};
		var $iframe = pwModalWindow(modalUri + queryString, modalSettings, 'large');
		$iframe.load(function() {

			// when iframe loads, pull the contents into $i 
			var $i = $iframe.contents();

			if($i.find("#selected_image").size() > 0) {
				// if there is a #selected_image element on the page...

				var buttons = [
					{ 
						html: "<i class='fa fa-camera'></i> " + config.InputfieldCKEditor.pwimage.insertBtn, // "Insert This Image",
						click:  function() {

							function insertImage(src) {

								var $i = $iframe.contents();
								var $img = $("#selected_image", $i); 
								var width = $img.attr('width');
								var height = $img.attr('height'); 
								var alt = $("#selected_image_description", $i).val();
								var hidpi = $("#selected_image_hidpi", $i).is(":checked") ? true : false;
								var cls = $img.removeClass('ui-resizable').attr('class');
								if(hidpi) cls += (cls.length > 0 ? ' ' : '') + 'hidpi';
								var $linkToLarger = $('#selected_image_link', $i); 
								var link = $linkToLarger.is(":checked") ? $linkToLarger.val() : ''; // link to larger version
								var html = '<img class="' + cls + '" src="' + src + '" '; 

								if(alt && alt.length > 0) alt = $("<div />").text(alt).html().replace(/"/g, '&quot;'); 

								if(width > 0) html += 'width="' + width + '" '; 
								// next line removed to prevent non-proportional scaling when width/height attributes removed
								// and front-end output code specifies max-width or min-width
								// if(height > 0) html += 'height="' + height + '" '; 
								html += 'alt="' + alt + '" />';
							
								if($linkWrapper) {	
									// img was wrapped in an <a>...</a>
									if(link && link.length > 0) {
										$linkWrapper.attr('href', link).attr('data-cke-saved-href', link); // populate existing link with new href
									} else if($linkToLarger.attr('data-was-checked') == 1) {
										// box was checked but no longer is
										$linkWrapper = null;
									}
									if($linkWrapper === null) $linkWrapper = $(html); 
										else $linkWrapper.append($(html)); 
									html = $linkWrapper.wrap($("<div>")).parent().html(); // outerHTML
								
								} else if(link && link.length > 0) {
									html = "<a href='" + link + "'>" + html + "</a>";
								}
								
								if(nodeParent && nodeParent.$.nodeName.toUpperCase() === "A") {
									// if parent node is already a link, we'll replace it too
									// @todo, omit if other text?
									selection.selectElement(nodeParent);
								}
								
								editor.insertHtml(html); 
								$iframe.dialog("close"); 
							}

							var $i = $iframe.contents();
							var $img = $("#selected_image", $i); 

							$iframe.dialog("disable");
							$iframe.setTitle("<i class='fa fa-fw fa-spin fa-spinner'></i> " + config.InputfieldCKEditor.pwimage.savingNote); // Saving Image
							$img.removeClass("resized"); 

							var width = $img.attr('width');
							if(!width) width = $img.width();
							var height = $img.attr('height'); 
							if(!height) height = $img.height();
							var file = $img.attr('src'); 
							var page_id = $("#page_id", $i).val();
							var hidpi = $("#selected_image_hidpi", $i).is(":checked") ? 1 : 0;
							file = file.substring(file.lastIndexOf('/')+1); 

							var resizeURL = modalUri + 'resize?id=' + page_id + 
								'&file=' + file + 
								'&width=' + width + 
								'&height=' + height + 
								'&hidpi=' + hidpi;
							$.get(resizeURL, function(data) {
								var $div = $("<div></div>").html(data); 
								var src = $div.find('#selected_image').attr('src');
								insertImage(src); 
							}); 

						}
					}, {

						html: "<i class='fa fa-folder-open'></i> " + config.InputfieldCKEditor.pwimage.selectBtn, // "Select Another Image", 
						class: 'ui-priority-secondary',
						click: function() {
							var $i = $iframe.contents();
							var page_id = $("#page_id", $i).val();
							$iframe.attr('src', modalUri + '?id=' + page_id + '&modal=1'); 
							$iframe.setButtons({}); 
						}
					}, {
						html: "<i class='fa fa-times-circle'></i> " + config.InputfieldCKEditor.pwimage.cancelBtn, // "Cancel",
						class: 'ui-priority-secondary',
						click: function() { $iframe.dialog("close"); }
					}
					
				];
				
				$iframe.setButtons(buttons); 
				$iframe.setTitle("<i class='fa fa-fw fa-picture-o'></i> " + $i.find('title').html());

			} else {
				var buttons = [];
				$("button.pw-modal-button, button[type=submit]:visible", $i).each(function() {
					var $button = $(this);
					var button = {
						html: $button.html(),
						click: function() {
							$button.click();
						}
					}
					buttons.push(button);
					$button.hide();
				});
				var cancelButton = {
					html: "<i class='fa fa-times-circle'></i> " + config.InputfieldCKEditor.pwimage.cancelBtn, // "Cancel",
					class: "ui-priority-secondary", 
					click: function() { $iframe.dialog("close"); }
				};
				buttons.push(cancelButton);
				$iframe.setButtons(buttons);
			}
		});
	}
	
})();
