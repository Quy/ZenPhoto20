<?php

// force UTF-8 Ø

class ThemeOptions {

	function ThemeOptions() {
		/* put any setup code needed here */
		setThemeOptionDefault('Allow_search', true);
		setThemeOptionDefault('Mini_slide_selector', 'Recent images');
		setThemeOption('albums_per_page', 9);
		setThemeOption('albums_per_row', 3);
		setThemeOption('images_per_page', 24);
		setThemeOption('images_per_row', 6);
		setThemeOption('image_size', 480);
		setThemeOption('image_use_side', 'longest');
		setThemeOption('thumb_size',89);
		setThemeOptionDefault('thumb_transition', 1);
		setOptionDefault('colorbox_stopdesign_album', 1);
		setOptionDefault('colorbox_stopdesign_image', 1);
		setOptionDefault('colorbox_stopdesign_search', 1);
	}

	function getOptionsSupported() {
		return array(	gettext('Allow search') => array('key' => 'Allow_search', 'type' => OPTION_TYPE_CHECKBOX,
													'desc' => gettext('Check to enable search form.')),
									gettext('Mini slide selector') => array('key' => 'Mini_slide_selector', 'type' => OPTION_TYPE_SELECTOR,
													'selections' => array(gettext('Recent images') => 'Recent images', gettext('Random images') => 'Random images'),
													'desc' => gettext('Select what you want for the six special slides.'))
									);
	}

	function getOptionsDisabled() {
		return array('thumb_size','thumb_crop','albums_per_row','images_per_row','image_size','custom_index_page');
	}

	function handleOption($option, $currentValue) {
	}

}
?>
