<?php
class i18nYMLConverter {
	
	/**
	 * @var String
	 * The absolute base path to a SilverStripe web root.
	 */
	public $basePath;

	/**
	 * @var String
	 * Needs to have the same directory structure as the actual project.
	 * @todo Create matching directory structure automatically.
	 */
	public $baseSavePath;
	
	public $langFolder = 'lang';
	
	/**
	 * @var String YML indentation
	 */
	public $indent = '  ';
	
	/**
	 * @var Array Replaces certain locales,
	 * e.g. to make them more generic.
	 * In addition to built-in logic which replaces "double"
	 * locales with their simpler main locale, e.g "fr_FR" becomes "fr".
	 */
	public $localeMap = array(
		'en_US' => 'en',
		'eo_XX' => 'eo',
		'af_ZA' => 'af',
		'ar_SA' => 'ar',
		'ast_ES' => 'ast',
		'bs_BA' => 'bs',
		'ca_AD' => 'ca',
		'cs_CZ' => 'cs',
		'cy_GB' => 'cy',
		'da_DK' => 'da',
		'el_GR' => 'el',
		'ja-JP' => 'ja',
		"km_KH" => "km",
		"ku_TR" => "ku",
		"kxm_TH" => "kxm",
		"lt_LT" => "lt",
		"lv_LV" => "lv",
		"mn_MN" => "mn",
		"ml_IN" => "ml",
		"ms_MY" => "ms",
		"nb_NO" => "nb",
		"ne_NP" => "ne",
		"nl_NL" => "nl",
		"pa_IN" => "pa",
		"pl_PL" => "pl",
		"ro_RO" => "ro",
		"ru_RU" => "ru",
		"si_LK" => "si",
		"sk_SK" => "sk",
		"sl_SI" => "sl",
		"sr_RS" => "sr",
		"sv_SE" => "sv",
		"th_TH" => "th",
		"tr_TR" => "tr",
		"uk_UA" => "uk",
		"ur_PK" => "ur",
		"uz_UZ" => "uz",		
	);
	
	public $retainTranslationsForMissingMaster = false;
	
	/**
	 * @param $locale
	 */
	function __construct() {
		$this->basePath = Director::baseFolder();
		$this->baseSavePath = Director::baseFolder();
	}

	function run($restrictToModules = null) {
		$modules = array();
		
		// A master string tables array (one mst per module)
		$entitiesByModule = array();
		
		//Search for and process existent modules, or use the passed one instead
		if($restrictToModules && count($restrictToModules)) {
			foreach($restrictToModules as $restrictToModule) {
				$modules[] = basename($restrictToModule);
			}
		} else {
			$modules = scandir($this->basePath);
		}
		
		foreach($modules as $module) {
			// Only search for calls in folder with a _config.php file 
			// (which means they are modules, including themes folder)  
			$isValidModuleFolder = (
				is_dir("$this->basePath/$module") 
				&& is_file("$this->basePath/$module/_config.php") 
				&& substr($module,0,1) != '.'
			);
			
			if(!$isValidModuleFolder) continue;
			
			// we store the master string tables 
			$processedEntities = $this->processModule($module);
		}
	}
	
	/**
	 * We can't simply query the already loaded $lang global as that doesn't
	 * distinguish between modules any longer.
	 * 
	 * @param String
	 * @return array
	 */
	function getTranslationsByModule($module) {
		global $lang;
		
		$return = array();
		$files = new GlobIterator("$this->basePath/$module/$this->langFolder/*.php");
		foreach($files as $file) {
			$fileLocale = preg_replace('/\.php/', '', $file->getFileName());
			
			// Overwrite global, but needs the en_US key because of the array_merge() call in the lang files.
			$lang = array('en_US' => array());
			$lang[$fileLocale] = array();
			require($file->getPathName());
			if($fileLocale != 'en_US') unset($lang['en_US']);
			
			$return[$fileLocale] = $lang[$fileLocale];
		}

		return $return;
	}
	
	function processModule($module) {		
		$translations = $this->getTranslationsByModule($module);
		$master = $translations['en_US'];
		
		// Special case: Move translations according to location of master entity,
		// mainly to fix up the file migration between modules during the 3.0 release.
		// Adds a bit of duplicated parsing effort, but its a one off process anyway.
		if(in_array($module, array('sapphire', 'cms')) ) {
			$otherModule = ($module == 'cms') ? 'sapphire' : 'cms';
			$otherTranslations = $this->getTranslationsByModule($otherModule);
			$otherMaster = $otherMasterLocales['en_US'];
			foreach($otherTranslations as $locale => $namespaces) {
				if($locale == 'en_US') continue;
				foreach($namespaces as $namespace => $entities) {
					foreach($entities as $id => $entity) {
						// If translated entity in other module is contained in master lang for 
						// currently processed module, move it there instead.
						if(isset($master[$namespace][$id])) {
							if(!isset($translations[$locale][$namespace])) $translations[$locale][$namespace] = array();
							$translations[$locale][$namespace][$id] = $entity;
							// Note: Translations for non-existent master keys are cleaned up further down
						}
						
						// Check for duplicates: If a translation exists in both sapphire and cms,
						// remove it from the cms module. We assume entity values are the same,
						// otherwise they'd override each other anyway. Technically it doesn't matter
						// for SilverStripe in which module the translation is contained, but we avoid
						// unnecessary work for translators by removing duplicates.
						 
						// TODO Removes too many entities (e.g. TableListField_PageControls.ss from both modules)
						// if($module == 'cms' && $otherModule == 'sapphire') {
						// 	if(
						// 		isset($translations[$locale][$namespace][$id]) 
						// 		&& isset($otherTranslations[$locale][$namespace][$id])
						// 	) {
						// 		unset($translations[$locale][$namespace][$id]);
						// 		if(!$translations[$locale][$namespace]) unset($translations[$locale][$namespace]);
						// 	}
						// }
					}
				}
			}
		} 
		
		foreach($translations as $locale => $namespaces) {
			$yml = '';
			
			if(@$this->localeMap[$locale]) {
				$locale = $this->localeMap[$locale];
			} else {
				// if first part matches second part, assume base locale
				$parts = explode('_', $locale);
				if(count($parts) == 2 && strtolower($parts[0]) == strtolower($parts[1])) {
					$locale = strtolower($parts[0]);
				} else {
					// Use IETF notation (en-US instead of en_US)
					$locale = implode('-', $parts);
				}
			}
			
			ksort($namespaces);

			$yml = "$locale:\n";
			foreach($namespaces as $namespace => $entities) {
				if(!$namespace) continue;
				if(!$entities) continue;
				
				// Skip namespaces missing in master lang
				if(!$this->retainTranslationsForMissingMaster && !isset($master[$namespace])) continue;
				
				$yml .= str_repeat($this->indent, 1) . "$namespace:\n";
			
				ksort($entities);
				foreach($entities as $id => $entity) {
					// Skip entity identifiers missing in master lang
					if(!$this->retainTranslationsForMissingMaster && !isset($master[$namespace][$id])) continue;
					
					if(is_array($entity)) {
						$trans = $entity[0];
						$context = $entity[2];
					} else {
						$trans = $entity;
						$context = null;
					}
					if($context) $yml .= str_repeat($this->indent, 2) . "# " . str_replace(array("\n", "\r"), '', $context) . "\n";
					
					if(preg_match('/[\n\r]/', $trans)) {
						$yml .= str_repeat($this->indent, 2) . "$id: |\n";
						$blockIndent = str_repeat($this->indent, 2) . str_pad('', strlen($id)+3, ' ');
						$yml .= $blockIndent . implode("\n" . $blockIndent, explode("\n", $trans));
					} else {
						$trans = str_replace(array('"'), array('\"'), $trans); // quote strings
						$yml .= str_repeat($this->indent, 2) . "$id: \"$trans\"\n";
					}
				}
			}
			file_put_contents("$this->baseSavePath/$module/$this->langFolder/$locale.yml", $yml);
		}
	}
	
}