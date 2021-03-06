<?php

namespace Drupal\jsonapi;


class JsonApiEntityReference {
  public function __construct($record) {
    $this->record = $record;
  }

  public function normalize() {
    return [
      "id" => $this->record["id"],
      "type" => $this->record["type"]
    ];
  }
}