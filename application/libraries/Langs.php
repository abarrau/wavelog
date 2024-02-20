<?php defined('BASEPATH') or exit('No direct script access allowed');

// class //
class Langs {
	static public $folder = APPPATH.'locale';
    static public $main_domain = "wavelog";
    static public $app_lang = "english";

    static public $list = array(
        'bulgarian' => '',
        'chinese_simplified' => '',
        'czech' => '',
        'dutch' => '',
        'english' => 'en_EN',   // not used //
        'finnish' => '',
        'french' => 'fr_FR',
        'german' => '',
        'greek' => '',
        'italian' => '',
        'polish' => '',
        'russian' => '',
        'spanish' => '',
        'swedish' => '',
        'turkish' => ''
    ); 

    // class was initiated with the "autoload" //
    public function __construct() {
        self::initLang();
    }

    // initialise language, and return json file name // 
    static function initLang() {
        $ci =& get_instance();
        $user_lang = $ci->config->item('language');

        if ($user_lang != self::$app_lang) {   // not action, because source code/text is english language //
            if (array_key_exists($user_lang, self::$list)) {
                $locale = self::$list[$user_lang];
                if (!empty($locale)) {
                    log_message('debug', '[Langs] locale='.$locale);
                    // test if mo file exist //
                    $json_file = self::$folder.'/'.$locale.'/'.self::$main_domain.'.json';
                    if (file_exists($json_file)) {
                        $ci->config->set_item('lang_json_file',$json_file);
                        log_message('error', '[Langs] initialized for , with file='.$json_file);
                    } else {
                        log_message('error', '[Langs] files ('.$json_file.') not exist, default language is use.');
                    }
                } else {
                    log_message('error', '[Langs] Locale not define on language list ('.$user_lang.'), default language is use.');
                }
            } else {
                log_message('error', '[Langs] Language not exist on language list ('.$user_lang.'), default language is use.');
            }
        }
    }

    // return json content file for specific file to view //
    static function getJsonContent() {
        log_message('debug', '[Langs::getTranslateFile] Load json translate file.');
        $ci =& get_instance();
        // 1- check if not application language //
        $user_lang = $ci->config->item('language');
        if ($user_lang == self::$app_lang) {
            return array();
        }
        // 2- check if content is in "cache" (config array) //
        $json_content = $ci->config->item('lang_json_content');
        if (is_array($json_content) && (count($json_content)>0)) {
            return $json_content;
        }
        // 3-check if name is defined in config //
        $json_file = $ci->config->item('lang_json_file');
        if (file_exists($json_file) && (substr($json_file,-5)=='.json')) {
            $json_content = file_get_contents($json_file);
            if (!empty($json_content)) {
                $ci->config->set_item('lang_json_content',$json_content);
                return json_decode($json_content,true);
            }
        } 
        return array();
    }

    // translate content //
    static function translateContent($content, $file='') {
        log_message('debug', '[Langs::translateContent] Get translation content ('.$content.') for file "'.$file.'"');
        if (empty(trim($content))) {
            return $content;
        }
        $ci =& get_instance();
        $user_lang = $ci->config->item('language');
        if ($user_lang == self::$app_lang) {
            return $content;
        }
        $json_content = self::getJsonContent();
        if (!is_array($json_content) || (count($json_content)==0)) {
            return $content;
        }
        if (!empty($file)) { $file = str_replace(APPPATH,'',$file); }
        if (isset($json_content[$file][$content]) && !empty($json_content[$file][$content])) {
            return $json_content[$file][$content];
        }
        if (isset($json_content['generic'][$content]) && !empty($json_content['generic'][$content])) {
            return $json_content['generic'][$content];
        }
        return $content;
    }

    // translate content //
    static function scanfile($dir) {
        log_message('debug', '[Langs::scanfile] Start scan file ('.$dir.')');
        //foreach()
    }
    
}

// generic function //
if (!function_exists('__t')) {
    function __t($content, $file='') {
        return Langs::translateContent($content, $file);
    }
}
