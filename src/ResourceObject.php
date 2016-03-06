<?php

namespace Drupal\jsonapi;

/*
   Represents a resource that has been sent to us by the client.
 */
class ResourceObject {
  public function __construct($sources, $drupal, $storage) {
    $this->sources = $sources;
    $this->drupal = $drupal;
    $this->storage = $storage;
  }

  public function toEntity() {
    return $this->storage->create($this->drupal);
  }
}
