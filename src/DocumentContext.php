<?php

namespace Drupal\jsonapi;

class DocumentContext {
    public function __construct($data, $meta) {
        $this->data = $data;
        $this->meta = $meta;
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
}