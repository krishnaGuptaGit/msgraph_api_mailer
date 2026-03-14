<?php
namespace local_msgraph_api_mailer\admin;

defined('MOODLE_INTERNAL') || die();

/**
 * Admin text input with placeholder attribute support.
 */
class configtext_placeholder extends \admin_setting_configtext {
    /** @var string */
    protected $placeholder;

    public function __construct($name, $visiblename, $description, $defaultsetting,
                                $paramtype = PARAM_RAW, $size = null, $placeholder = '') {
        parent::__construct($name, $visiblename, $description, $defaultsetting, $paramtype, $size);
        $this->placeholder = $placeholder;
    }

    public function output_html($data, $query = '') {
        $html = parent::output_html($data, $query);
        if ($this->placeholder !== '') {
            $attr = 'placeholder="' . htmlspecialchars($this->placeholder, ENT_QUOTES) . '"';
            $html = str_replace('<input ', '<input ' . $attr . ' ', $html);
        }
        return $html;
    }
}

/**
 * Admin password input with placeholder attribute support.
 */
class configpassword_placeholder extends \admin_setting_configpasswordunmask {
    /** @var string */
    protected $placeholder;

    public function __construct($name, $visiblename, $description, $defaultsetting, $placeholder = '') {
        parent::__construct($name, $visiblename, $description, $defaultsetting);
        $this->placeholder = $placeholder;
    }

    public function output_html($data, $query = '') {
        $html = parent::output_html($data, $query);
        if ($this->placeholder !== '') {
            $attr = 'placeholder="' . htmlspecialchars($this->placeholder, ENT_QUOTES) . '"';
            $html = str_replace('<input ', '<input ' . $attr . ' ', $html);
        }
        return $html;
    }
}

/**
 * Enable checkbox that blocks saving when required sibling settings are empty.
 *
 * When the checkbox is ticked, write_setting() inspects the other fields
 * submitted in the same POST and returns an error string if any required
 * ones are blank, preventing the setting from being saved.
 */
class configcheckbox_with_required extends \admin_setting_configcheckbox {

    /** @var string[] Bare setting names that must be non-empty when enabled. */
    protected array $requirednames;

    /** @var string Error shown to the admin when a required field is missing. */
    protected string $errormsg;

    /**
     * @param string   $name           e.g. 'local_msgraph_api_mailer/enabled'
     * @param string   $visiblename
     * @param string   $description
     * @param int      $defaultsetting
     * @param string[] $requirednames  e.g. ['tenant_id', 'client_id', 'client_secret', 'sender_email']
     * @param string   $errormsg       Validation error shown when a required field is missing.
     */
    public function __construct($name, $visiblename, $description, $defaultsetting,
                                array $requirednames, string $errormsg) {
        parent::__construct($name, $visiblename, $description, $defaultsetting);
        $this->requirednames = $requirednames;
        $this->errormsg      = $errormsg;
    }

    /**
     * Block enabling the plugin when required fields are not filled in.
     *
     * @param  string $data  '1' = checkbox ticked, '0' = unticked.
     * @return string        '' on success, error message on failure.
     */
    public function write_setting($data) {
        if ($data == '1') {
            foreach ($this->requirednames as $fieldname) {
                // Moodle POST key for a plugin setting is s_{plugin}_{name}.
                $postkey = 's_' . str_replace('/', '_', $this->plugin) . '_' . $fieldname;
                $value   = optional_param($postkey, '', PARAM_RAW);

                // If the field was not submitted, fall back to the saved DB value.
                if ($value === '') {
                    $value = (string) get_config($this->plugin, $fieldname);
                }

                if (trim($value) === '') {
                    return $this->errormsg;
                }
            }
        }
        return parent::write_setting($data);
    }
}
