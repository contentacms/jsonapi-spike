<?php

namespace Drupal\jsonapi;

class DocumentContext {
    public function __construct($data, $meta, $options) {
        $this->data = $data;
        $this->meta = $meta;
        $this->options = $options;
        $this->included = [];
    }
    public function addIncluded($record) {
        $type = $record['type'];
        $id = $record['id'];
        if (!isset($this->included[$type])) {
            $this->included[$type] = [];
        }
        if (!isset($this->included[$type][$id])) {
            $this->included[$type][$id] = $record;
        }
    }
    public function shouldInclude($path) {
        return true;
    }
    public function allIncluded() {
        $output = [];
        foreach($this->included as $type => $values) {
            foreach($values as $id => $value) {
                $output[] = $value;
            }
        }
        return $output;
    }
    public function debugEnabled() {
        return $this->options['debug'];
    }
    public function addMeta($key, $value) {
        if (!$this->meta) {
            $this->meta = [];
        }
        $this->meta[$key] = $value;
    }
    public function meta() {
        if ($this->debugEnabled()) {
            $this->addMeta('options', $this->options);
        }
        return $this->meta;
    }
}