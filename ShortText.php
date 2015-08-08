<?php

class ShortText {
    private $db;
    private $long2short = null;

    public function __construct($options) {
        $this->db = $options['db'];
    }

    private function loadReplacements() {
        $replacements = $this->db->query('SELECT `find`, `replace` FROM shorter_text');

        if (!$replacements->num_rows) {
            $this->long2short = array();
            return;
        }

        while ($replacement = $replacements->fetch_assoc()) {
            $long = mb_strtolower($replacement['find'], 'UTF-8');
            $this->long2short[$long] = $replacement['replace'];
        }
    }

    public function shorten($long) {
        if (is_null($this->long2short)) {
            $this->loadReplacements();
        }

        if (empty($this->long2short)) {
            return $long;
        }

        $lowerCase = mb_strtolower($long, 'UTF-8');

        return isset($this->long2short[$lowerCase]) ? $this->long2short[$lowerCase] : $long;
    }
}
